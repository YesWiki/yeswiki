<?php

namespace YesWiki\Contact\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use YesWiki\Contact\Command\ContactDigestCommand;
use YesWiki\Kernel\Entity\Event;
use YesWiki\Kernel\Service\ConsoleService;

/** Sends the mailing-list digests without any external cron. */
class ContactDigestScheduler implements EventSubscriberInterface
{
    /** How long each period must go unsent before it is due again, in seconds. */
    private const FLOORS = [
        'day' => 82800,
        'week' => 604800,
        'month' => 2592000,
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
     * Never throws: this runs inside somebody's page view, and a digest that cannot be sent must not be the reason a reader gets an error instead of their page.
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

            if ($this->claim($period)) {
                $due[] = $period;
            }
        }

        return $due;
    }

    /** Whether anything wants a digest at all. */
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
        @ignore_user_abort(true);
        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        }
        @set_time_limit(0);

        require_once YESWIKI_SOURCE_DIR . '/src/Contact/contact.functions.php';
        foreach ($periods as $period) {
            try {
                sendEmailsToSubscribers($period, '');
            } catch (\Throwable $ignored) {
            }
        }
    }

    private function lastRunTime(string $period): int
    {
        $file = $this->stateFile($period);
        $time = ($file !== null && is_file($file)) ? @filemtime($file) : false;

        return $time === false ? 0 : $time;
    }

    /** Stamp the period as taken. */
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

        return YESWIKI_INSTANCE_DIR . '/private/digests/' . $period . '.last';
    }
}
