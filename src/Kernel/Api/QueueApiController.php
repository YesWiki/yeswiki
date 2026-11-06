<?php

namespace YesWiki\Kernel\Api;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\YesWikiController;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Service\Queue\QueueConsumer;
use YesWiki\Kernel\Service\Queue\QueuePassLimitsFactory;
use YesWiki\Kernel\Service\Queue\QueueSettings;

class QueueApiController extends YesWikiController
{
    public const SECRET_HEADER = 'X-YesWiki-Queue-Secret';
    public const SECRET_QUERY = 'secret';

    /** One pass over the queue (mode `api`) for a caller sending the wiki's `queue_consume_secret`; it runs alongside any other pass. */
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
        if (!$this->sharesSecret($expected, $provided)) {
            return new ApiResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $mode = QueueConsumerMode::Api;
        $report = $this->getService(QueueConsumer::class)->drain($mode, $this->getService(QueuePassLimitsFactory::class)->forPass());

        return new ApiResponse([
            'handled' => $report->handled,
            'remaining' => $report->remaining,
            'durationMs' => $report->durationMs,
            'stopReason' => $report->stopReason,
        ]);
    }
}
