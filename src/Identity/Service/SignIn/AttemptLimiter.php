<?php

namespace YesWiki\Identity\Service\SignIn;

use Psr\Log\LogLevel;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\Journal;
use YesWiki\Kernel\Service\JournalSchema;

/**
 * Stops repeated wrong passwords, with the audit rows as the counter: no table of its own.
 * A refused attempt is written as `login.throttled`, not as a failure, so being blocked does not extend the block.
 */
class AttemptLimiter
{
    public const MAX_FAILURES = 5;
    public const WINDOW_MINUTES = 15;

    public function __construct(
        private readonly DbService $db,
        private readonly Journal $journal,
        private readonly JournalSchema $schema,
    ) {
    }

    public static function normalize(string $login): string
    {
        return mb_strtolower(trim($login));
    }

    public function isBlocked(string $who): bool
    {
        $q = fn (string $column): string => $this->db->quoteIdentifier($column);
        $count = (int)$this->db->scalar(
            "SELECT COUNT(*) FROM {$q($this->schema->table())}"
            . " WHERE {$q('channel')} = 'audit' AND {$q('action')} = 'login.failed'"
            . " AND LOWER({$q('target')}) = ? AND {$q('at')} >= {$this->db->dateSubMinutes(self::WINDOW_MINUTES)}",
            0,
            [self::normalize($who)]
        );

        return $count >= self::MAX_FAILURES;
    }

    public function recordThrottled(string $providerId, string $who): void
    {
        $this->journal->audit('login.throttled', $who, ['provider' => $providerId], null, LogLevel::WARNING);
    }
}
