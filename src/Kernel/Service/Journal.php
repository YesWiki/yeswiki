<?php

namespace YesWiki\Kernel\Service;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Psr\Log\LogLevel;
use YesWiki\Kernel\Entity\JournalChannel;

/**
 * The wiki's log, in two sinks and one call site (ADR-0025): every event reaches stderr as a JSON line, unconditionally and first, and then the Journal table, best-effort.
 *
 * The order is the whole design. The errors this records include the ones thrown from the write
 * path of the database, so an insert that may fail silently is the second sink, never the only
 * one.
 */
class Journal implements LoggerInterface
{
    use LoggerTrait;

    /** How many distinct diagnostic fingerprints one wiki may store in a day; past it, stderr only. */
    public const DIAGNOSTIC_CEILING_PER_DAY = 500;

    public const AUDIT_PURGE_SETTING = 'journal_audit_purge_time';
    public const DIAGNOSTIC_PURGE_SETTING = 'journal_diagnostic_purge_time';

    /** What a migration writes once it has run, whatever it did. */
    public const MIGRATION_APPLIED = 'migration.applied';

    /** What the legacy log pages were imported as. */
    public const LEGACY = 'legacy';

    private ContainerInterface $container;

    /** Set while an entry is being stored, so a throw from inside the write cannot re-enter. */
    private bool $writing = false;

    /** Whether a failed write has already been reported: a wiki whose Journal table is missing would otherwise say so on every save. */
    private bool $reportedWriteFailure = false;

    /** The day $storedToday and $seenToday are about. */
    private string $countedDay = '';

    /** How many diagnostic rows this wiki already holds for $countedDay. */
    private ?int $storedToday = null;

    /** @var array<string, true> fingerprints already stored today, which cost nothing to update */
    private array $seenToday = [];

    /** @var resource|null */
    private $stderr;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Where the log goes.
     *
     * A seam rather than a switch: the stream is written to unconditionally, and what a test does
     * is point it somewhere it can read back. Nothing turns it off.
     *
     * @param resource $stream
     */
    public function writeTo($stream): void
    {
        $this->stderr = $stream;
    }

    /**
     * An act somebody performed: a deletion, an ACL change, an account created, a migration applied.
     *
     * @param string               $action  a dotted code -- `content.delete`, `acl.change`. Never a
     *                                      rendered sentence: `/admin/logs` builds the phrase from a
     *                                      translation key at read time, so a wiki that changes
     *                                      language does not end up with a bilingual trail
     * @param array<string, mixed> $context
     * @param string|null          $actor   the acting user, when it is not whoever is signed in
     * @param string               $level   PSR-3, and independent of the channel: "refused
     *                                      permission to delete X" is an act at `warning`, and
     *                                      filing it under errors instead would lose the actor
     */
    public function audit(string $action, string $target = '', array $context = [], ?string $actor = null, string $level = LogLevel::INFO): void
    {
        $this->store(JournalChannel::Audit, $level, $action, $target, $context, $actor, null, null);
    }

    /**
     * Something that went wrong (PSR-3).
     *
     * A `Throwable` under the conventional `exception` key names the entry and fingerprints it.
     *
     * @param array<string, mixed> $context
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $throwable = ($context['exception'] ?? null) instanceof \Throwable ? $context['exception'] : null;
        unset($context['exception']);

        $action = (string)($context['action'] ?? ($throwable !== null ? $throwable::class : 'php.error'));
        $target = (string)($context['target'] ?? $this->currentTarget());
        unset($context['action'], $context['target']);

        [$file, $line] = $this->originOf($throwable);
        $context = ['message' => (string)$message] + $context + ['file' => $file, 'line' => $line];

        if ($throwable !== null) {
            $context['frames'] = $this->container->get(ThrowableFormatter::class)->frames($throwable);
        }

        // Not the message: messages carry variable data ("User 42 not found", "User 43 not
        // found"), so fingerprinting on them defeats the dedup at the exact moment a storm makes
        // it matter.
        $fingerprint = substr(hash('sha256', implode('|', [
            JournalChannel::Diagnostic->value, (string)$level, $throwable !== null ? $throwable::class : $action, $file, (string)$line,
        ])), 0, 32);

        $this->store(JournalChannel::Diagnostic, (string)$level, $action, $target, $context, null, $fingerprint, date('Y-m-d'));
    }

    /**
     * Delete what has outlived its retention: a year of audit, a fortnight of diagnostics.
     *
     * Diagnostics go by `last_at` rather than `at`, so a fault that is still firing is still on
     * the screen -- pruning it on first sighting would delete the one thing worth keeping about
     * it, which is that it started a long time ago and has not stopped.
     *
     * @return int rows removed
     */
    public function prune(): int
    {
        $table = $this->container->get(JournalSchema::class)->table();
        $db = $this->container->get(DbService::class);
        $quote = fn (string $column): string => $db->quoteIdentifier($column);
        $removed = 0;

        foreach ([
            [JournalChannel::Audit, self::AUDIT_PURGE_SETTING, 365, 'at'],
            [JournalChannel::Diagnostic, self::DIAGNOSTIC_PURGE_SETTING, 14, 'last_at'],
        ] as [$channel, $setting, $fallback, $column]) {
            $days = (int)($this->container->get(RuntimeConfig::class)[$setting] ?? $fallback);
            if ($days <= 0) {
                continue;
            }

            $statement = $db->query(
                "DELETE FROM {$quote($table)} WHERE {$quote('channel')} = ? AND {$quote($column)} < {$db->dateSubDays($days)}",
                [$channel->value]
            );
            $removed += $statement->rowCount();
        }

        return $removed;
    }

    /**
     * One line on stderr for something with no wiki around it yet -- a database that will not connect, an exception thrown before the container was built.
     *
     * @param array<string, mixed> $payload
     */
    public static function toStderr(string $baseUrl, array $payload): void
    {
        $line = json_encode(
            ['at' => date('c'), 'wiki' => self::wikiIdentifier($baseUrl)] + $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        $handle = fopen('php://stderr', 'wb');
        if ($handle !== false) {
            fwrite($handle, $line . "\n");
            fclose($handle);
        }
    }

    /**
     * What names this wiki in a stream carrying several of them: the base URL with its scheme stripped, which keeps `example.org/wiki1` and `example.org/wiki2` apart and is config rather than path (ADR-0022).
     */
    public static function wikiIdentifier(string $baseUrl): string
    {
        return rtrim((string)preg_replace('~^[a-z][a-z0-9+.-]*://~i', '', trim($baseUrl)), '/');
    }

    /**
     * @param array<string, mixed> $context
     */
    private function store(JournalChannel $channel, string $level, string $action, string $target, array $context, ?string $actor, ?string $fingerprint, ?string $day): void
    {
        $actor ??= $this->currentActor();

        $this->writeToStderr($channel, $level, $action, $target, $actor, $context);

        if ($this->writing) {
            return;
        }

        $this->writing = true;
        try {
            $this->writeToJournal($channel, $level, $action, $target, $actor, $context, $fingerprint, $day);
        } catch (\Throwable $failed) {
            if (!$this->reportedWriteFailure) {
                $this->reportedWriteFailure = true;
                self::toStderr($this->baseUrl(), [
                    'channel' => JournalChannel::Diagnostic->value,
                    'level' => LogLevel::ERROR,
                    'action' => 'journal.write_failed',
                    'message' => $failed->getMessage(),
                ]);
            }
        } finally {
            $this->writing = false;
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function writeToStderr(JournalChannel $channel, string $level, string $action, string $target, string $actor, array $context): void
    {
        $line = json_encode([
            'at' => date('c'),
            'wiki' => self::wikiIdentifier($this->baseUrl()),
            'channel' => $channel->value,
            'level' => $level,
            'actor' => $actor,
            'action' => $action,
            'target' => $target,
        ] + $context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!is_resource($this->stderr)) {
            $handle = fopen('php://stderr', 'wb');
            if ($handle === false) {
                return;
            }
            $this->stderr = $handle;
        }

        fwrite($this->stderr, $line . "\n");
    }

    /**
     * @param array<string, mixed> $context
     */
    private function writeToJournal(JournalChannel $channel, string $level, string $action, string $target, string $actor, array $context, ?string $fingerprint, ?string $day): void
    {
        if ($fingerprint !== null && $day !== null && !$this->underCeiling($day, $fingerprint)) {
            return;
        }

        $db = $this->container->get(DbService::class);
        $table = $this->container->get(JournalSchema::class)->table();
        $now = $db->now();

        // One statement for both channels, because an audit entry carries no fingerprint and a
        // unique index over a NULL never collides on any of the three drivers -- three deletions
        // stay three rows without a second code path saying so.
        $sql = $db->dialect()->upsert(
            $table,
            [
                'at' => $now,
                'last_at' => $now,
                'repeat' => '1',
                'channel' => '?',
                'level' => '?',
                'actor' => '?',
                'action' => '?',
                'target' => '?',
                'fingerprint' => '?',
                'day' => '?',
                'context' => '?',
            ],
            ['fingerprint', 'day'],
            [
                'last_at' => ':new.last_at',
                'level' => ':new.level',
                'target' => ':new.target',
                'context' => ':new.context',
                'repeat' => $db->quoteIdentifier('repeat') . ' + 1',
            ]
        );

        $db->query($sql, [
            $channel->value,
            $level,
            mb_substr($actor, 0, 191),
            mb_substr($action, 0, 191),
            mb_substr($target, 0, 191),
            $fingerprint,
            $day,
            $context === [] ? null : json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    /**
     * Whether one more distinct fingerprint may be stored today.
     *
     * Dedup bounds repeats, not distinct fingerprints, and a closure or an `eval` can vary them
     * without bound -- so the ceiling counts fingerprints, and one already stored always goes
     * through. A storm of the same fault keeps counting; a storm of different ones stops at the
     * ceiling and carries on to stderr.
     */
    private function underCeiling(string $day, string $fingerprint): bool
    {
        if ($this->countedDay !== $day) {
            $this->countedDay = $day;
            $this->storedToday = null;
            $this->seenToday = [];
        }

        if (isset($this->seenToday[$fingerprint])) {
            return true;
        }

        if ($this->storedToday === null) {
            $db = $this->container->get(DbService::class);
            $table = $db->quoteIdentifier($this->container->get(JournalSchema::class)->table());
            $this->storedToday = (int)$db->scalar(
                "SELECT COUNT(*) FROM {$table} WHERE {$db->quoteIdentifier('channel')} = ? AND {$db->quoteIdentifier('day')} = ?",
                0,
                [JournalChannel::Diagnostic->value, $day]
            );
        }

        if ($this->storedToday >= self::DIAGNOSTIC_CEILING_PER_DAY) {
            return false;
        }

        $this->storedToday++;
        $this->seenToday[$fingerprint] = true;

        return true;
    }

    /**
     * Where a diagnostic came from: the throwable's own position, or the call site's.
     *
     * @return array{0: string, 1: int}
     */
    private function originOf(?\Throwable $throwable): array
    {
        $formatter = $this->container->get(ThrowableFormatter::class);

        if ($throwable !== null) {
            return [$formatter->hideServerPath($throwable->getFile()), $throwable->getLine()];
        }

        $frames = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4);
        $frame = $frames[count($frames) - 1] ?? [];

        return [$formatter->hideServerPath((string)($frame['file'] ?? '')), (int)($frame['line'] ?? 0)];
    }

    /** The screen a diagnostic happened on, named without its query string: a route is not a payload. */
    private function currentTarget(): string
    {
        try {
            $context = $this->container->get(PageContext::class);
            $tag = $context->getTag();
            $method = $context->getRawMethod();

            return $tag === '' ? '' : ($method === '' ? $tag : $tag . '/' . $method);
        } catch (\Throwable $unavailable) {
            return '';
        }
    }

    private function currentActor(): string
    {
        try {
            return $this->container->get(ActorSource::class)->currentActor();
        } catch (\Throwable $unavailable) {
            return '';
        }
    }

    private function baseUrl(): string
    {
        try {
            return $this->container->get(UrlFormatter::class)->getBaseUrl();
        } catch (\Throwable $unavailable) {
            return '';
        }
    }
}
