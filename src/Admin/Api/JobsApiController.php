<?php

namespace YesWiki\Admin\Api;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use YesWiki\Admin\Service\JobsOverview;
use YesWiki\Core\YesWikiController;
use YesWiki\Identity\Service\RequestGuard;
use YesWiki\Kernel\Api\QueueApiController;
use YesWiki\Kernel\Queue\QueuePassOrigin;
use YesWiki\Kernel\Queue\QueuePassReport;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Kernel\Service\Journal;
use YesWiki\Kernel\Service\Queue\QueueConsumer;
use YesWiki\Kernel\Service\Queue\QueuePassLimitsFactory;
use YesWiki\Kernel\Service\Queue\QueueRecorder;
use YesWiki\Kernel\Service\Queue\QueueScheduleTicker;
use YesWiki\Kernel\Service\Queue\QueueSettings;
use YesWiki\Kernel\Service\Queue\QueueStatistics;
use YesWiki\Kernel\Service\Queue\QueueTransport;

/**
 * The background jobs page's tabs and actions, as HTML for htmx to swap (messenger / scheduler, spec §11.3).
 * Every action answers with the top of the page as it is after it, and `ywJobsChanged`, on which the open tab reloads.
 * Each POST checks the session's token itself, though RequestGuard already does.
 */
class JobsApiController extends YesWikiController
{
    private const ADMIN_ACL = ['@admins'];

    private const CHANGED = 'ywJobsChanged';

    #[Route('/api/admin/jobs/tab/{tab}', methods: ['GET'], requirements: ['tab' => 'types|failed|tasks|history|consumers'], options: ['acl' => self::ADMIN_ACL])]
    public function tab(string $tab): Response
    {
        $this->denyAccessUnlessAdmin();

        return $this->html($this->render('@core/admin/jobs-tabs.twig', ['tab' => $this->getService(JobsOverview::class)->tab($tab)]));
    }

    /** A pass from the admin's request, whatever the mode (spec §11.3), that empties the queue with no limit: it stops on an empty queue, a paused wiki or the server killing it. */
    #[Route('/api/admin/jobs/consume', methods: ['POST'], options: ['acl' => self::ADMIN_ACL])]
    public function consume(): Response
    {
        if (($refused = $this->refusal()) !== null) {
            return $refused;
        }
        // The pass has no limit: holding the admin's session that long would keep every other page of theirs waiting, and a closed tab must not cut it.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        if (function_exists('ignore_user_abort')) {
            ignore_user_abort(true);
        }

        $mode = $this->getService(QueueSettings::class)->mode()->forPassFromTheWeb();
        $report = $this->getService(QueueConsumer::class)->drain($mode, $this->getService(QueuePassLimitsFactory::class)->untilEmpty(), true, QueuePassOrigin::Manual);

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

    /** Acknowledges a failed message (or all the failures alert counts, for `all`): out of `failed`, kept in history as `ignored`. */
    #[Route('/api/admin/jobs/failed/{id}/ignore', methods: ['POST'], requirements: ['id' => '\d+|all'], options: ['acl' => self::ADMIN_ACL])]
    public function ignore(string $id): Response
    {
        if (($refused = $this->refusal()) !== null) {
            return $refused;
        }

        return $this->ignored($this->failedMessages($id));
    }

    /** Gives every failed message of one type (short class name, killed ones apart) its retries again: a module alert's offer. */
    #[Route('/api/admin/jobs/failed/of/{type}/retry', methods: ['POST'], requirements: ['type' => '\w+'], options: ['acl' => self::ADMIN_ACL])]
    public function retryType(string $type): Response
    {
        if (($refused = $this->refusal()) !== null) {
            return $refused;
        }

        return $this->requeued($this->getService(JobsOverview::class)->failedOfType($type));
    }

    /** Acknowledges every failed message of one type, tolerated types included, as `ignore` does for one. */
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

    /** Queues a run of a scheduled task now; its next run stays on its slot (ScheduleGrid). */
    #[Route('/api/admin/jobs/tasks/{taskId}/run', methods: ['POST'], options: ['acl' => self::ADMIN_ACL])]
    public function runTask(string $taskId): Response
    {
        if (($refused = $this->refusal()) !== null) {
            return $refused;
        }

        $ticker = $this->getService(QueueScheduleTicker::class);
        if (!$ticker->runNow($taskId)) {
            return $this->status(_t('ADMIN_JOBS_TASK_UNKNOWN'), 'warning');
        }
        $task = $ticker->task($taskId);

        return $this->status(_t('ADMIN_JOBS_TASK_QUEUED', [
            'task' => $task['label'] ?? $taskId,
            'next' => $this->getService(JobsOverview::class)->ago($task['nextDueAt'] ?? null) ?? '–',
        ]));
    }

    /** A new secret for the `api` mode's route, written into the wiki's configuration and shown this once. */
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
        $replayable = array_filter($messages, static fn (array $message): bool => QueueTransport::replayableByHand($message['type']));
        if ($messages !== [] && $replayable === []) {
            return $this->status(_t('ADMIN_JOBS_NOT_REPLAYABLE'), 'warning');
        }
        $transport = $this->getService(QueueTransport::class);
        $requeued = count(array_filter($replayable, static fn (array $message): bool => $transport->requeue($message['id'])));

        return $requeued === 0
            ? $this->status(_t('ADMIN_JOBS_GONE'), 'warning')
            : $this->status(_t('ADMIN_JOBS_RETRIED', ['count' => $requeued]));
    }

    /**
     * Acknowledges these failed messages: out of `failed`, kept in the history as `ignored`.
     *
     * @param list<array{id: int, type: string, attempts: int, error: ?string, killedIn: ?string, createdAt: \DateTimeImmutable}> $messages
     */
    private function ignored(array $messages): Response
    {
        foreach ($messages as $message) {
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
