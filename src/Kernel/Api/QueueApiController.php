<?php

namespace YesWiki\Kernel\Api;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\YesWikiController;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Queue\QueuePassReport;
use YesWiki\Kernel\Service\Queue\QueueBudgetFactory;
use YesWiki\Kernel\Service\Queue\QueueConsumer;
use YesWiki\Kernel\Service\Queue\QueueSettings;

class QueueApiController extends YesWikiController
{
    public const SECRET_HEADER = 'X-YesWiki-Queue-Secret';
    public const SECRET_QUERY = 'secret';

    /**
     * One pass over the queue, for an online cron, a webhook or a monitor (mode `api`): the caller proves it may by sending the wiki's `queue_consume_secret`.
     *
     * Public as far as the ACL goes, like the import sync route: the secret is the whole check. A
     * wiki that has none set answers 401 to everybody, so an empty secret never lets an empty
     * header in. 409 when another pass is running: the caller did nothing wrong, it only has to
     * come back; the body is the same either way.
     *
     * GET with the secret in the `secret` query parameter works too: a deliberate departure from
     * HTTP, since some hosts only schedule GET requests, without a custom header. The header wins
     * when both are sent, and stays the form to recommend: a query string ends up in access logs.
     */
    #[Route('/api/queue/consume', methods: ['GET', 'POST'], options: ['acl' => ['public']])]
    public function consume(): ApiResponse
    {
        $expected = $this->getService(QueueSettings::class)->consumeSecret();
        $request = $this->getRequest();
        $provided = (string)$request->headers->get(self::SECRET_HEADER, '');
        if ($provided === '') {
            $query = $request->query->all()[self::SECRET_QUERY] ?? '';
            $provided = is_string($query) ? $query : '';
        }
        if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
            return new ApiResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $mode = QueueConsumerMode::Api;
        $report = $this->getService(QueueConsumer::class)->drain($mode, $this->getService(QueueBudgetFactory::class)->forMode($mode, false));

        return new ApiResponse([
            'handled' => $report->handled,
            'remaining' => $report->remaining,
            'durationMs' => $report->durationMs,
            'stopReason' => $report->stopReason,
        ], $report->stopReason === QueuePassReport::LOCKED ? Response::HTTP_CONFLICT : Response::HTTP_OK);
    }
}
