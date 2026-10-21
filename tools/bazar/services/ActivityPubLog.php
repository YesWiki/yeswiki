<?php

namespace YesWiki\Bazar\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Writes every ActivityPub exchange to one JSON line per event in private/activitypub/log/<date>.jsonl, when `activitypub_log` is on.
 */
class ActivityPubLog
{
    public const KEEP_DAYS = 14;
    public const MAX_BODY_BYTES = 65536;

    protected $params;
    protected $directory;

    public function __construct(ParameterBagInterface $params, string $directory = 'private/activitypub/log')
    {
        $this->params = $params;
        $this->directory = $directory;
    }

    public function isEnabled(): bool
    {
        return $this->params->has('activitypub_log') && filter_var($this->params->get('activitypub_log'), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * An activity posted to a form's inbox, with who signed it and what the inbox answered.
     */
    public function received($formId, Request $request, int $status, ?string $verifiedActor, ?string $error = null): void
    {
        $body = $request->getContent();
        $activity = json_decode($body, true);
        $this->write([
            'direction' => 'in',
            'form' => (string)$formId,
            'type' => is_array($activity) ? ($activity['type'] ?? null) : null,
            'peer' => $verifiedActor ?? (is_array($activity) ? ($activity['actor'] ?? null) : null),
            'status' => $status,
            'error' => $error,
            'request' => $this->requestSummary($request),
            'activity' => is_array($activity) ? $activity : $this->truncate($body),
        ]);
    }

    /**
     * An activity the form posted to one recipient's inbox, with the answer it got.
     */
    public function delivered($formId, string $recipient, ?string $inbox, array $activity, ?int $status, ?string $error = null, ?string $response = null): void
    {
        $this->write([
            'direction' => 'out',
            'form' => (string)$formId,
            'type' => $activity['type'] ?? null,
            'peer' => $recipient,
            'inbox' => $inbox,
            'status' => $status,
            'error' => $error,
            'response' => $response === null ? null : $this->truncate($response),
            'activity' => $activity,
        ]);
    }

    /**
     * A remote server reading one of the wiki's ActivityPub documents: an actor, a collection, an entry or a WebFinger answer.
     */
    public function served($formId, Request $request, int $status): void
    {
        $this->write([
            'direction' => 'get',
            'form' => $formId === null ? null : (string)$formId,
            'status' => $status,
            'request' => $this->requestSummary($request),
        ]);
    }

    /**
     * An object a sync could not turn into an entry, with the reason.
     */
    public function syncFailed($formId, string $actorUri, $object, string $error): void
    {
        $this->write([
            'direction' => 'sync',
            'form' => (string)$formId,
            'type' => is_array($object) ? ($object['type'] ?? null) : null,
            'peer' => $actorUri,
            'error' => $error,
            'activity' => $object,
        ]);
    }

    protected function requestSummary(Request $request): array
    {
        $keyId = null;
        if (preg_match('/keyId="([^"]*)"/', (string)$request->headers->get('Signature', ''), $matches)) {
            $keyId = $matches[1];
        }

        return [
            'method' => $request->getMethod(),
            'uri' => $request->getRequestUri(),
            'accept' => $request->headers->get('Accept'),
            'userAgent' => $request->headers->get('User-Agent'),
            'keyId' => $keyId,
            'ip' => $request->getClientIp(),
        ];
    }

    protected function truncate(string $text): string
    {
        return strlen($text) > self::MAX_BODY_BYTES ? substr($text, 0, self::MAX_BODY_BYTES) . '…' : $text;
    }

    protected function write(array $record): void
    {
        if (!$this->isEnabled()) {
            return;
        }
        if (!is_dir($this->directory) && !@mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
            error_log('ActivityPub: cannot create ' . $this->directory);

            return;
        }
        $file = $this->directory . '/' . date('Y-m-d') . '.jsonl';
        if (!is_file($file)) {
            $this->forgetOldFiles();
        }
        $line = json_encode(['time' => date('c')] + $record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if (@file_put_contents($file, $line . "\n", FILE_APPEND | LOCK_EX) === false) {
            error_log('ActivityPub: cannot write ' . $file);
        }
    }

    protected function forgetOldFiles(): void
    {
        $limit = time() - self::KEEP_DAYS * 86400;
        foreach (glob($this->directory . '/*.jsonl') ?: [] as $file) {
            if (filemtime($file) < $limit) {
                @unlink($file);
            }
        }
    }
}
