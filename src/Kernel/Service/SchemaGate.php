<?php

namespace YesWiki\Kernel\Service;

use Symfony\Component\HttpFoundation\Request;

/** Refuses a write on a replica whose code and database disagree, so an old pod drains during a rollout and a new one waits for the migrate Job (replicas 06). */
class SchemaGate
{
    public const RETRY_AFTER = 10;

    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    private const TOKEN_FIELD = 'csrf-token';

    public function __construct(private readonly SchemaStep $step, private readonly ExtensionRegistry $extensions)
    {
    }

    /** Why this request must wait for the rollout to end, or null when it may go on. */
    public function refusal(Request $request): ?string
    {
        if (!Replication::isOn() || !self::writes($request)) {
            return null;
        }
        try {
            $state = $this->step->state($this->folders());
        } catch (\Throwable) {
            return null;
        }

        return match ($state) {
            SchemaStep::BEHIND => _t('SCHEMA_BEHIND'),
            SchemaStep::AHEAD => _t('SCHEMA_AHEAD'),
            default => null,
        };
    }

    /** Whether a request changes something: any method but the safe ones, and a GET link signed with the session's token. */
    public static function writes(Request $request): bool
    {
        return !in_array($request->getMethod(), self::SAFE_METHODS, true) || $request->query->has(self::TOKEN_FIELD);
    }

    /** @return list<string> the migration folders of the running wiki, the core's first, as MigrationService reads them */
    public function folders(): array
    {
        return [YESWIKI_PROGRAM_DIR . '/src/', ...array_values($this->extensions->all())];
    }
}
