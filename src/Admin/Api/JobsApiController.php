<?php

namespace YesWiki\Admin\Api;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use YesWiki\Admin\Service\JobsOverview;
use YesWiki\Core\YesWikiController;
use YesWiki\Identity\Service\RequestGuard;
use YesWiki\Kernel\Api\QueueApiController;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Queue\QueuePassOrigin;
use YesWiki\Kernel\Queue\QueuePassReport;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Kernel\Service\Journal;
use YesWiki\Kernel\Service\Queue\QueueBudgetFactory;
use YesWiki\Kernel\Service\Queue\QueueConsumer;
use YesWiki\Kernel\Service\Queue\QueueRecorder;
use YesWiki\Kernel\Service\Queue\QueueScheduleTicker;
use YesWiki\Kernel\Service\Queue\QueueSettings;
use YesWiki\Kernel\Service\Queue\QueueStatistics;
use YesWiki\Kernel\Service\Queue\QueueTransport;

/**
 * The background jobs page's tabs and actions, as HTML for htmx to swap (messenger / scheduler, spec §11.3).
 *
 * Every action answers with the top of the page as it is after it -- mode, alerts, tiles, and a
 * line saying what was done -- and the `ywJobsChanged` event, on which the open tab reloads itself.
 * The POSTs carry the session's token like every other admin POST; RequestGuard already refuses one
 * that does not, and each action checks it again so that nothing here relies on being reached
 * through the front controller.
 */
class JobsApiController extends YesWikiController
{
    /** @var list<string> */
    private const ADMIN_ACL = ['@admins'];

    /** The longest an admin waits for "consume now", whatever the mode would allow. */
    private const CONSUME_SECONDS = 20.0;

    /** The event the open tab listens to. */
    private const CHANGED = 'ywJobsChanged';

    #[Route('/api/admin/jobs/tab/{tab}', methods: ['GET'], requirements: ['tab' => 'types|failed|tasks|history|consumers'], options: ['acl' => self::ADMIN_ACL])]
    public function tab(string $tab): Response
    {
        $this->denyAccessUnlessAdmin();

        return $this->html($this->render('@core/admin/jobs-tabs.twig', ['tab' => $this->getService(JobsOverview::class)->tab($tab)]));
    }

    /**
     * A budgeted pass from the admin's request, whatever the mode (spec §11.3): the mode's own budget when it has one, the command's otherwise.
     *
     * Recorded as a pass after the response, which it is as far as visitors go: an admin who asked
     * for it and waits is not the cost `queue-visitor-cost` measures.
     */
    #[Route('/api/admin/jobs/consume', methods: ['POST'], options: ['acl' => self::ADMIN_ACL])]
    public function consume(): Response
    {
        if (($refused = $this->refusal()) !== null) {
            return $refused;
        }

        $mode = $this->getService(QueueSettings::class)->mode();
        $mode = $mode->isBudgeted() ? $mode : QueueConsumerMode::Command;
        $budget = $this->getService(QueueBudgetFactory::class)->forMode($mode, true)->cappedTo(self::CONSUME_SECONDS);
        $report = $this->getService(QueueConsumer::class)->drain($mode, $budget, true, true, QueuePassOrigin::Manual);

        return match ($report->stopReason) {
            QueuePassReport::LOCKED => $this->status(_t('ADMIN_JOBS_CONSUME_LOCKED'), 'warning'),
            QueuePassReport::PAUSED => $this->status(_t('ADMIN_JOBS_CONSUME_PAUSED'), 'warning'),
            QueuePassReport::ERROR => $this->status(_t('ADMIN_JOBS_CONSUME_ERROR'), 'danger'),
            default => $this->status(_t('ADMIN_JOBS_CONSUMED', [
                'handled' => $report->handled,
                'remaining' => $report->remaining,
                'reason' => JobsOverview::stopReasonLabel($report->stopReason),
            ])),
        };
    }

    /** Gives a failed message (or every one the failures alert counts, for `all`) its retries again. */
    #[Route('/api/admin/jobs/failed/{id}/retry', methods: ['POST'], requirements: ['id' => '\d+|all'], options: ['acl' => self::ADMIN_ACL])]
    public function retry(string $id): Response
    {
        if (($refused = $this->refusal()) !== null) {
            return $refused;
        }

        return $this->requeued($this->failedMessages($id));
    }

    /**
     * Acknowledges a failed message (or every one the failures alert counts, for `all`): out of `failed` and of the alert, kept in the history as `ignored`.
     *
     * It mutes nothing: the next failure is a new message in `failed`, and the alert comes back.
     */
    #[Route('/api/admin/jobs/failed/{id}/ignore', methods: ['POST'], requirements: ['id' => '\d+|all'], options: ['acl' => self::ADMIN_ACL])]
    public function ignore(string $id): Response
    {
        if (($refused = $this->refusal()) !== null) {
            return $refused;
        }

        return $this->ignored($this->failedMessages($id));
    }

    /**
     * Gives every failed message of one type its retries again, tolerated types included: the offer of a module's alert.
     *
     * The type is its short class name; killed messages stay with their own alert.
     */
    #[Route('/api/admin/jobs/failed/of/{type}/retry', methods: ['POST'], requirements: ['type' => '\w+'], options: ['acl' => self::ADMIN_ACL])]
    public function retryType(string $type): Response
    {
        if (($refused = $this->refusal()) !== null) {
            return $refused;
        }

        return $this->requeued($this->getService(JobsOverview::class)->failedOfType($type));
    }

    /**
     * Acknowledges every failed message of one type, tolerated types included, as `ignore` does for one: kept in the history as `ignored`.
     *
     * It mutes nothing: the next failure is a new message in `failed`, and the alert comes back.
     */
    #[Route('/api/admin/jobs/failed/of/{type}/ignore', methods: ['POST'], requirements: ['type' => '\w+'], options: ['acl' => self::ADMIN_ACL])]
    public function ignoreType(string $type): Response
    {
        if (($refused = $this->refusal()) !== null) {
            return $refused;
        }

        return $this->ignored($this->getService(JobsOverview::class)->failedOfType($type));
    }

    /** Removes a failed message and leaves no trace of it: one at a time, and only from `failed`. */
    #[Route('/api/admin/jobs/failed/{id}/delete', methods: ['POST'], requirements: ['id' => '\d+'], options: ['acl' => self::ADMIN_ACL])]
    public function delete(string $id): Response
    {
        if (($refused = $this->refusal()) !== null) {
            return $refused;
        }

        $messages = $this->failedMessages($id);
        foreach ($messages as $message) {
            $this->getService(QueueTransport::class)->deleteFailed($message['id']);
        }

        return $messages === []
            ? $this->status(_t('ADMIN_JOBS_GONE'), 'warning')
            : $this->status(_t('ADMIN_JOBS_DELETED'));
    }

    /** Queues a run of a scheduled task now; the next pass runs it, and the schedule keeps its own time. */
    #[Route('/api/admin/jobs/tasks/{taskId}/run', methods: ['POST'], options: ['acl' => self::ADMIN_ACL])]
    public function runTask(string $taskId): Response
    {
        if (($refused = $this->refusal()) !== null) {
            return $refused;
        }

        if (!$this->getService(QueueScheduleTicker::class)->runNow($taskId)) {
            return $this->status(_t('ADMIN_JOBS_TASK_UNKNOWN'), 'warning');
        }
        $label = $taskId;
        foreach ($this->getService(QueueStatistics::class)->tasks() as $task) {
            if ($task['taskId'] === $taskId) {
                $label = $task['label'];
            }
        }

        return $this->status(_t('ADMIN_JOBS_TASK_QUEUED', ['task' => $label]));
    }

    /**
     * A new secret for the `api` mode's route, written into the wiki's configuration and shown this once.
     *
     * The settings of this request were read before it was written: the page says so until it is
     * reloaded, so the answer is only the secret, not the top of the page.
     */
    #[Route('/api/admin/jobs/secret', methods: ['POST'], options: ['acl' => self::ADMIN_ACL])]
    public function secret(): Response
    {
        if (($refused = $this->refusal()) !== null) {
            return $refused;
        }

        $secret = bin2hex(random_bytes(24));
        $file = ConfigurationFileProvider::getConfigFileFromEnv();
        $configuration = $this->getService(ConfigurationService::class);
        $config = $configuration->getConfiguration($file);
        $config->load();
        $config['queue_consume_secret'] = $secret;
        $written = $configuration->write($config);
        if ($written) {
            $this->getService(Journal::class)->audit('config.write', basename($file));
        }

        return $this->html($this->render('@core/admin/jobs-secret.twig', [
            'secret' => $written ? $secret : null,
            'header' => QueueApiController::SECRET_HEADER,
            'query' => QueueApiController::SECRET_QUERY,
        ]));
    }

    /**
     * Gives these failed messages their retries again.
     *
     * @param list<array{id: int, type: string, attempts: int, error: ?string, killedIn: ?string, createdAt: \DateTimeImmutable}> $messages
     */
    private function requeued(array $messages): Response
    {
        foreach ($messages as $message) {
            $this->getService(QueueTransport::class)->requeue($message['id']);
        }

        return $messages === []
            ? $this->status(_t('ADMIN_JOBS_GONE'), 'warning')
            : $this->status(_t('ADMIN_JOBS_RETRIED', ['count' => count($messages)]));
    }

    /**
     * Acknowledges these failed messages: out of `failed`, kept in the history as `ignored`.
     *
     * @param list<array{id: int, type: string, attempts: int, error: ?string, killedIn: ?string, createdAt: \DateTimeImmutable}> $messages
     */
    private function ignored(array $messages): Response
    {
        foreach ($messages as $message) {
            // One retried meanwhile is not in `failed` any more: nothing was ignored.
            if ($this->getService(QueueTransport::class)->deleteFailed($message['id'])) {
                $this->getService(QueueRecorder::class)->messageIgnored($message['id'], $message['type'], $message['error']);
            }
        }

        return $messages === []
            ? $this->status(_t('ADMIN_JOBS_GONE'), 'warning')
            : $this->status(_t('ADMIN_JOBS_IGNORED', ['count' => count($messages)]));
    }

    /**
     * The failed messages an action names: that one if it waits in `failed`, or every one the failures alert counts.
     *
     * @return list<array{id: int, type: string, attempts: int, error: ?string, killedIn: ?string, createdAt: \DateTimeImmutable}>
     */
    private function failedMessages(string $id): array
    {
        if ($id === 'all') {
            return $this->getService(JobsOverview::class)->failedToActOn();
        }
        $message = $this->getService(QueueStatistics::class)->failedMessage((int)$id);

        return $message === null ? [] : [$message];
    }

    /** The answer to a POST that is not an admin's, or that does not carry the session's token; null when it may go on. */
    private function refusal(): ?Response
    {
        $this->denyAccessUnlessAdmin();
        if (!$this->getService(RequestGuard::class)->carriesToken($this->getRequest())) {
            return $this->html('<p class="yw-alert yw-alert--danger" role="alert">' . htmlspecialchars(_t('CSRF_TOKEN_FAIL_ERROR')) . '</p>', Response::HTTP_FORBIDDEN);
        }

        return null;
    }

    /** The top of the page as it is now, with what was just done said above the alerts; the open tab is told to reload. */
    private function status(string $notice, string $level = 'success'): Response
    {
        $response = $this->html($this->render('@core/admin/jobs-alerts.twig', [
            'status' => $this->getService(JobsOverview::class)->status(),
            'notice' => $notice,
            'noticeLevel' => $level,
        ]));
        $response->headers->set('HX-Trigger', self::CHANGED);

        return $response;
    }

    private function html(string $html, int $status = Response::HTTP_OK): Response
    {
        return new Response($html, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }
}
