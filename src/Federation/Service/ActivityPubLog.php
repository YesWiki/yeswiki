<?php

namespace YesWiki\Federation\Service;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;

/** ActivityPub exchanges in the Journal (ADR-0025): failures always as warnings, the rest as debug lines with the activities when `activitypub_log` is on. */
class ActivityPubLog
{
    public const MAX_BODY_BYTES = 65536;

    public function __construct(private readonly ParameterBagInterface $params, private readonly LoggerInterface $journal)
    {
    }

    public function isEnabled(): bool
    {
        return $this->params->has('activitypub_log') && filter_var($this->params->get('activitypub_log'), FILTER_VALIDATE_BOOLEAN);
    }

    /** An activity posted to a form's inbox, with who signed it and what the inbox answered. */
    public function received(int|string $formId, Request $request, int $status, ?string $verifiedActor, ?string $error = null): void
    {
        $body = $request->getContent();
        $activity = json_decode($body, true);
        $type = is_array($activity) && is_string($activity['type'] ?? null) ? $activity['type'] : '?';
        $peer = $verifiedActor ?? (is_array($activity) && is_string($activity['actor'] ?? null) ? $activity['actor'] : null);

        $this->write(
            $status >= 400,
            'activitypub.received',
            (string)$formId,
            "{$type} from " . ($peer ?? 'an unknown actor') . " answered {$status}" . ($error === null ? '' : ": {$error}"),
            [
                'type' => $type,
                'peer' => $peer,
                'status' => $status,
                'error' => $error,
                'id' => is_array($activity) && is_string($activity['id'] ?? null) ? $activity['id'] : null,
                'request' => $this->requestSummary($request),
            ],
            ['activity' => is_array($activity) ? $activity : $this->truncate($body)]
        );
    }

    /**
     * An activity the form posted to one recipient's inbox, with the answer it got.
     *
     * @param array<string, mixed> $activity
     */
    public function delivered(int|string $formId, string $recipient, ?string $inbox, array $activity, ?int $status, ?string $error = null, ?string $response = null): void
    {
        $type = is_string($activity['type'] ?? null) ? $activity['type'] : '?';

        $this->write(
            $error !== null,
            $error === null ? 'activitypub.delivered' : 'activitypub.delivery_failed',
            (string)$formId,
            "{$type} to {$recipient}" . ($status === null ? '' : " answered {$status}") . ($error === null ? '' : ": {$error}"),
            [
                'type' => $type,
                'peer' => $recipient,
                'inbox' => $inbox,
                'status' => $status,
                'error' => $error,
                'id' => is_string($activity['id'] ?? null) ? $activity['id'] : null,
            ],
            ['activity' => $activity, 'response' => $response === null ? null : $this->truncate($response)]
        );
    }

    /** A remote server reading one of the wiki's ActivityPub documents: an actor, a collection, an entry or a WebFinger answer. */
    public function served(int|string|null $formId, Request $request, int $status): void
    {
        $this->write(
            false,
            'activitypub.served',
            $formId === null ? '' : (string)$formId,
            "{$request->getMethod()} {$request->getPathInfo()} answered {$status}",
            ['status' => $status, 'request' => $this->requestSummary($request)],
            []
        );
    }

    /** An object a sync could not turn into an entry, with the reason. */
    public function syncFailed(int|string $formId, string $actorUri, mixed $object, string $error): void
    {
        $id = is_string($object) ? $object : (is_array($object) && is_string($object['id'] ?? null) ? $object['id'] : null);

        $this->write(
            true,
            'activitypub.sync_failed',
            (string)$formId,
            'Sync of ' . ($id ?? 'an object') . " from {$actorUri} failed: {$error}",
            [
                'type' => is_array($object) ? ($object['type'] ?? null) : null,
                'peer' => $actorUri,
                'id' => $id,
                'error' => $error,
            ],
            ['activity' => $object]
        );
    }

    /**
     * A failure goes to the Journal whatever the setting; the rest, and the activities themselves, only when `activitypub_log` is on.
     *
     * @param array<string, mixed> $context
     * @param array<string, mixed> $bodies
     */
    private function write(bool $failed, string $action, string $formId, string $message, array $context, array $bodies): void
    {
        $enabled = $this->isEnabled();
        if (!$failed && !$enabled) {
            return;
        }
        $context = ['action' => $action, 'target' => $formId === '' ? '' : 'actors/' . $formId] + array_filter($context, fn ($value) => $value !== null);
        if ($enabled) {
            $context += array_filter($bodies, fn ($value) => $value !== null);
        }
        $this->journal->log($failed ? LogLevel::WARNING : LogLevel::DEBUG, $message, $context);
    }

    /** @return array<string, string|null> */
    private function requestSummary(Request $request): array
    {
        $keyId = null;
        if (preg_match('/keyId="([^"]*)"/', (string)$request->headers->get('Signature', ''), $matches)) {
            $keyId = $matches[1];
        }

        return array_filter([
            'method' => $request->getMethod(),
            'uri' => $request->getRequestUri(),
            'accept' => $request->headers->get('Accept'),
            'userAgent' => $request->headers->get('User-Agent'),
            'keyId' => $keyId,
            'ip' => $request->getClientIp(),
        ], fn ($value) => $value !== null);
    }

    private function truncate(string $text): string
    {
        return strlen($text) > self::MAX_BODY_BYTES ? substr($text, 0, self::MAX_BODY_BYTES) . '…' : $text;
    }
}
