<?php

namespace YesWiki\Admin\Service;

use Cron\CronExpression;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/** When the nightly backup runs, what it holds and how many of its own it keeps, read from `archive[schedule]`, `archive[schedule_type]` and `archive[scheduled_keep]`. */
class ArchiveSchedule
{
    public const TASK_ID = 'archive';
    public const DEFAULT_CRON = '30 3 * * *';
    public const DEFAULT_TYPE = 'only_db';
    public const DEFAULT_KEEP = 7;
    public const OFF = 'off';
    public const TYPES = ['only_db', 'full'];

    public function __construct(
        private readonly ParameterBagInterface $params,
    ) {
    }

    /** Whether a value can be stored in `archive[schedule]`: a cron expression, or `off`. */
    public static function acceptsCron(string $cron): bool
    {
        $cron = trim($cron);

        return $cron === '' || strtolower($cron) === self::OFF || CronExpression::isValidExpression($cron);
    }

    /** The cron expression, '' when the schedule is off or not one the Scheduler reads. */
    public function cron(): string
    {
        $value = $this->setting('schedule');
        $cron = is_string($value) ? trim($value) : self::DEFAULT_CRON;
        if ($cron === '') {
            $cron = self::DEFAULT_CRON;
        }

        return strtolower($cron) !== self::OFF && CronExpression::isValidExpression($cron) ? $cron : '';
    }

    public function type(): string
    {
        $value = $this->setting('schedule_type');

        return is_string($value) && in_array($value, self::TYPES, true) ? $value : self::DEFAULT_TYPE;
    }

    public function keep(): int
    {
        $value = $this->setting('scheduled_keep');

        return is_numeric($value) && (int)$value >= 1 ? (int)$value : self::DEFAULT_KEEP;
    }

    private function setting(string $key): mixed
    {
        $archive = $this->params->has(ArchiveService::PARAMS_KEY_IN_WAKKA) ? $this->params->get(ArchiveService::PARAMS_KEY_IN_WAKKA) : [];

        return is_array($archive) ? ($archive[$key] ?? null) : null;
    }
}
