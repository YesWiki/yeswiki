<?php

namespace YesWiki\Admin\Service;

use Psr\Clock\ClockInterface;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\Queue\QueueSchema;

/**
 * The backups and restores a page follows by uid, kept in `queue_history`: every replica reads them, and neither a backup
 * nor a restore touches that table (replicas 09). A run is one `archive.run` row; a STOP asked of it is one `archive.stop` row.
 */
class ArchiveRuns
{
    public const RUN = 'archive.run';
    public const STOP = 'archive.stop';

    private const TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private readonly DbService $db,
        private readonly QueueSchema $schema,
        private readonly ClockInterface $clock,
    ) {
    }

    /** Opens a run and answers its uid. */
    public function open(): string
    {
        $uid = bin2hex(random_bytes(8));
        $this->insert(self::RUN, $uid, ['output' => '']);

        return $uid;
    }

    /** @return array<string, mixed>|null what the run notes, its output included; null for a uid no run has */
    public function get(string $uid): ?array
    {
        $context = $this->db->scalar(
            "SELECT context FROM {$this->schema->historyTable()} WHERE action = ? AND target = ? ORDER BY id DESC LIMIT 1",
            null,
            [self::RUN, $uid]
        );
        if (!is_string($context)) {
            return null;
        }
        $decoded = json_decode($context, true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @return array<string, array<string, mixed>> every run, by uid */
    public function all(): array
    {
        $runs = [];
        foreach ($this->db->loadAll("SELECT target, context FROM {$this->schema->historyTable()} WHERE action = ? ORDER BY id", [self::RUN]) as $row) {
            $decoded = is_string($row['context'] ?? null) ? json_decode($row['context'], true) : null;
            $runs[(string)$row['target']] = is_array($decoded) ? $decoded : [];
        }

        return $runs;
    }

    /**
     * Merges those notes into the run's; nothing happens for a uid no run has.
     *
     * @param array<string, mixed> $notes
     */
    public function note(string $uid, array $notes): void
    {
        $run = $this->get($uid);
        if ($run !== null) {
            $this->update($uid, array_merge($run, $notes));
        }
    }

    /** Adds that text to what the run printed; false for a uid no run has. */
    public function append(string $uid, string $text): bool
    {
        $run = $this->get($uid);
        if ($run === null) {
            return false;
        }
        $run['output'] = (string)($run['output'] ?? '') . $text;
        $this->update($uid, $run);

        return true;
    }

    /** Empties what the run printed. */
    public function clearOutput(string $uid): void
    {
        $this->note($uid, ['output' => '']);
    }

    /** What the run printed so far, '' for a uid no run has. */
    public function output(string $uid): string
    {
        return (string)($this->get($uid)['output'] ?? '');
    }

    /** Asks the run to stop; false for a uid no run has. */
    public function stop(string $uid): bool
    {
        if ($this->get($uid) === null) {
            return false;
        }
        $this->insert(self::STOP, $uid, []);

        return true;
    }

    /** Whether someone asked that run to stop. */
    public function stopRequested(string $uid): bool
    {
        return $uid !== '' && (int)$this->db->scalar(
            "SELECT COUNT(*) FROM {$this->schema->historyTable()} WHERE action = ? AND target = ?",
            0,
            [self::STOP, $uid]
        ) > 0;
    }

    /** Forgets the run and its STOP. */
    public function forget(string $uid): void
    {
        $this->db->query("DELETE FROM {$this->schema->historyTable()} WHERE action IN (?, ?) AND target = ?", [self::RUN, self::STOP, $uid]);
    }

    /** @param array<string, mixed> $context */
    private function insert(string $action, string $uid, array $context): void
    {
        $now = $this->now();
        $quote = $this->db->quoteIdentifier(...);
        $this->db->query(
            "INSERT INTO {$this->schema->historyTable()} ({$quote('at')}, {$quote('last_at')}, {$quote('actor')}, {$quote('action')}, {$quote('target')}, {$quote('context')}) VALUES (?, ?, ?, ?, ?, ?)",
            [$now, $now, '', $action, $uid, self::encode($context)]
        );
    }

    /** @param array<string, mixed> $context */
    private function update(string $uid, array $context): void
    {
        $this->db->query(
            "UPDATE {$this->schema->historyTable()} SET last_at = ?, context = ? WHERE action = ? AND target = ?",
            [$this->now(), self::encode($context), self::RUN, $uid]
        );
    }

    private function now(): string
    {
        return $this->clock->now()->format(self::TIME_FORMAT);
    }

    /** @param array<string, mixed> $context */
    private static function encode(array $context): string
    {
        return (string)json_encode((object)$context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    }
}
