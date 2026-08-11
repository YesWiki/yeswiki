<?php

namespace YesWiki\Content\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use YesWiki\Content\Command\ContactDigestCommand;
use YesWiki\Kernel\Entity\Event;
use YesWiki\Kernel\Service\ConsoleService;

/**
 * Sends the mailing-list digests without any external cron.
 *
 * The digest used to be triggered by fetching `/PageName/sendmail&key=<contact_passphrase>&period=…`
 * from a real cron. Ticket 35 made it `./yeswicli contact:send-digest`, which is what cron should
 * have been running all along -- but plenty of YesWiki installs have no cron at all, which is why
 * `SyncScheduler` exists for imports. This is the same arrangement for digests, and deliberately
 * copies it rather than inventing a second cadence.
 *
 * ## When
 *
 * On `maintenance.after`, so at most once per `MAINTENANCE_INTERVAL`, on whichever page view
 * crosses it. Each period then has its own floor -- a day, a week, a month -- because "the wiki
 * did its housekeeping" is far more often than "a day has passed".
 *
 * A period is *claimed* by stamping its state file before anything is sent, and stamped before the
 * work rather than after: two page views crossing the interval together must not both start the
 * daily digest, and unlike a re-run import, a re-run digest is a second email in somebody's inbox.
 * If the send then fails, the period is skipped until the next interval, which is the right way
 * round -- a missed digest is recoverable, a duplicate one is not.
 *
 * ## Where the work happens
 *
 * Not here. `YesWikiRuntime::maintenance()` runs inside a page view and asks its listeners to be
 * quick, and sending mail to every subscriber of a group is not quick -- so this spawns the
 * command, exactly as SyncScheduler spawns `importer:sync`. Where that cannot happen (no
 * `proc_open`, no findable PHP binary -- common on shared hosting, which is precisely the host with
 * no cron either) it falls back to sending in this process from a shutdown function, after the
 * visitor has been given their page.
 */
class ContactDigestScheduler implements EventSubscriberInterface
{
    /** How long each period must go unsent before it is due again, in seconds. */
    private const FLOORS = [
        'day' => 82800,      // 23h, so a daily digest is not skipped by a few minutes' drift
        'week' => 604800,
        'month' => 2592000,  // 30 days
    ];

    private ParameterBagInterface $params;
    private ContainerInterface $services;

    public function __construct(ParameterBagInterface $params, ContainerInterface $services)
    {
        $this->params = $params;
        $this->services = $services;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'maintenance.after' => 'onMaintenanceDone',
        ];
    }

    public function onMaintenanceDone(Event $event): void
    {
        $this->onMaintenance();
    }

    /**
     * Never throws: this runs inside somebody's page view, and a digest that cannot be sent must
     * not be the reason a reader gets an error instead of their page.
     */
    public function onMaintenance(): void
    {
        try {
            if (!$this->isEnabled()) {
                return;
            }

            $couldNotSpawn = [];
            foreach ($this->claimDuePeriods() as $period) {
                if (!$this->spawn($period)) {
                    $couldNotSpawn[] = $period;
                }
            }

            if ($couldNotSpawn !== []) {
                register_shutdown_function(function () use ($couldNotSpawn): void {
                    $this->sendAfterResponse($couldNotSpawn);
                });
            }
        } catch (\Throwable $ignored) {
            // deliberately swallowed; see the docblock
        }
    }

    /**
     * The periods due now, each stamped as claimed before being returned.
     *
     * @return list<string>
     */
    public function claimDuePeriods(): array
    {
        $due = [];
        foreach (ContactDigestCommand::PERIODS as $period) {
            $floor = self::FLOORS[$period];
            if (time() - $this->lastRunTime($period) < $floor) {
                continue;
            }
            // claimed BEFORE sending: a duplicate email cannot be taken back
            if ($this->claim($period)) {
                $due[] = $period;
            }
        }

        return $due;
    }

    /**
     * Whether anything wants a digest at all.
     *
     * A wiki with no mailing-list groups has nothing to send, and there is no point spawning a
     * process every housekeeping pass to discover that. `contact_disable_periodic_digest` is the
     * off switch for a wiki that has groups but sends from a real cron instead, so that both do
     * not send.
     */
    private function isEnabled(): bool
    {
        if ($this->params->has('contact_disable_periodic_digest')
            && filter_var($this->params->get('contact_disable_periodic_digest'), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        try {
            $groups = $this->services->get(\YesWiki\Identity\Service\GroupOperationsService::class)->getAll();
        } catch (\Throwable $unavailable) {
            return false;
        }

        foreach ($groups as $group) {
            // the naming convention the digest itself filters on (contact.functions.php)
            if (str_starts_with((string)$group, 'mail')) {
                return true;
            }
        }

        return false;
    }

    private function spawn(string $period): bool
    {
        try {
            return $this->services->get(ConsoleService::class)
                ->startConsoleAsync('contact:send-digest', ['-p', $period]) !== null;
        } catch (\Throwable $unavailable) {
            return false;
        }
    }

    /**
     * @param list<string> $periods
     */
    private function sendAfterResponse(array $periods): void
    {
        // the visitor has their page: let go of their connection where php-fpm allows it, and keep
        // going even if the browser hangs up, so a digest is not left half sent
        @ignore_user_abort(true);
        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        }
        @set_time_limit(0);

        require_once YESWIKI_SOURCE_DIR . '/src/Content/contact.functions.php';
        foreach ($periods as $period) {
            try {
                sendEmailsToSubscribers($period, '');
            } catch (\Throwable $ignored) {
                // one period failing must not stop the others
            }
        }
    }

    private function lastRunTime(string $period): int
    {
        $file = $this->stateFile($period);
        $time = ($file !== null && is_file($file)) ? @filemtime($file) : false;

        return $time === false ? 0 : $time;
    }

    /** Stamp the period as taken. False when the state directory is not writable. */
    private function claim(string $period): bool
    {
        $file = $this->stateFile($period);
        if ($file === null) {
            return false;
        }
        $directory = dirname($file);
        if (!is_dir($directory) && !@mkdir($directory, 0o755, true)) {
            return false;
        }

        return @file_put_contents($file, (string)time()) !== false;
    }

    private function stateFile(string $period): ?string
    {
        if (!in_array($period, ContactDigestCommand::PERIODS, true)) {
            return null;
        }

        // under private/, not cache/: a cache is something anybody may delete to fix a problem,
        // and deleting this one sends every digest again
        return YESWIKI_INSTANCE_DIR . '/private/digests/' . $period . '.last';
    }
}
