<?php

namespace YesWiki\Contact\Command;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `./yeswicli contact:send-digest --period=week` -- the periodic mailing-list digest.
 *
 * This was `/PageName/sendmail`, a page handler authenticated by `?key=<contact_passphrase>` in
 * the query string. Cron runs commands, not URLs, and a secret in a query string is written to the
 * web server's access log, to any proxy in front of it, to `Referer` headers and to browser
 * history -- and that secret was the wiki's *site-wide* contact passphrase. Moving the job to the
 * CLI removes the secret rather than relocating it: a shell already has to be trusted to run this.
 *
 * A wiki with no cron at all is served by ContactDigestScheduler, which spawns this from the
 * wiki's own housekeeping pass.
 */
class ContactDigestCommand extends Command
{
    public const PERIODS = ['day', 'week', 'month'];

    private ContainerInterface $services;

    /**
     * The container argument is not optional, even where a command does not obviously need it:
     * `src/commands/console` registers every command as `new $className($services)`,
     * unconditionally. A command without a matching constructor inherits Symfony's
     * `Command::__construct(?string $name)` and the container is passed as the *name* -- a
     * TypeError that takes down the whole console, `list` included, not just this command.
     */
    public function __construct(ContainerInterface $services)
    {
        parent::__construct();
        $this->services = $services;
    }

    protected function configure(): void
    {
        $this
            ->setName('contact:send-digest')
            ->setDescription('Send the periodic digest to mailing-list subscribers.')
            ->setHelp(
                "Sends the digest for one period to the groups subscribed to it.\n"
                . "Meant for cron:  0 6 * * *  cd /path/to/wiki && ./yeswicli contact:send-digest -p day\n"
                . "A wiki with no cron sends them from its own maintenance pass instead; see\n"
                . "ContactDigestScheduler.\n"
            )
            ->addOption(
                'period',
                'p',
                InputOption::VALUE_REQUIRED,
                'Which subscriptions to send: ' . implode(', ', self::PERIODS)
            )
            ->addOption('subject', null, InputOption::VALUE_OPTIONAL, 'Override the message subject', '');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $period = (string)$input->getOption('period');
        if (!in_array($period, self::PERIODS, true)) {
            // Named explicitly rather than defaulted: sending the wrong period mails the wrong
            // people, and there is no undo on an email.
            $output->writeln('<error>--period must be one of: ' . implode(', ', self::PERIODS) . '</error>');

            return Command::INVALID;
        }

        // A hibernated wiki is one being backed up or upgraded; mailing its subscribers from
        // under a maintenance window is the same mistake as writing to its database, which every
        // other write path already refuses.
        if ($this->services->get(\YesWiki\Kernel\Service\HibernationService::class)->isWikiHibernated()) {
            $output->writeln('<error>' . _t('WIKI_IN_HIBERNATION') . '</error>');

            return Command::FAILURE;
        }

        require_once YESWIKI_SOURCE_DIR . '/src/Contact/contact.functions.php';

        $output->writeln("Sending the '{$period}' digest");
        try {
            sendEmailsToSubscribers($period, (string)$input->getOption('subject'));
        } catch (\Throwable $th) {
            $output->writeln('<error>' . $th->getMessage() . '</error>');

            return Command::FAILURE;
        }
        $output->writeln('Done');

        return Command::SUCCESS;
    }
}
