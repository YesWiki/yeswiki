<?php

namespace YesWiki\Content\Command;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use YesWiki\Content\Service\PageBodyMigrator;
use YesWiki\Kernel\Service\DbService;

/**
 * Operator-facing front end for ticket 09's body migration.
 *
 * `./yeswicli migrate` runs the migration unattended as part of an upgrade. This command
 * exists for the case that actually worries us -- a large wiki whose operator wants to
 * look before leaping:
 *
 *     ./yeswicli content:migrate-bodies --dry-run    # report only, writes nothing
 *     ./yeswicli content:migrate-bodies              # asks about backups, then converts
 *     ./yeswicli content:migrate-bodies --verify     # re-read every row and check it
 *
 * The conversion is idempotent, so running it here and then letting `migrate` run it
 * again is harmless -- the second pass finds nothing left to do.
 */
class MigrateBodiesCommand extends Command
{
    private ContainerInterface $services;

    public function __construct(ContainerInterface $services)
    {
        parent::__construct();
        $this->services = $services;
    }

    protected function configure(): void
    {
        $this
            ->setName('content:migrate-bodies')
            ->setDescription('Convert every pages.body to the JSON shape (ticket 09), all revisions')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would change; write nothing')
            ->addOption('verify', null, InputOption::VALUE_NONE, 'Only re-read every row and report any that is not in the target shape')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Skip the backup confirmation (for unattended runs that already took one)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $migrator = $this->services->get(PageBodyMigrator::class);

        if ($input->getOption('verify')) {
            return $this->report($output, $migrator->verify());
        }

        if ($input->getOption('dry-run')) {
            $plan = $migrator->plan();
            $output->writeln(sprintf(
                '%d revisions: %d would be converted, %d re-encoded canonically, %d already correct, %d empty.',
                $plan['total'],
                $plan['converted'],
                $plan['normalized'],
                $plan['already_json'],
                $plan['empty']
            ));
            foreach ($plan['samples'] as $sample) {
                $output->writeln("  {$sample['tag']} #{$sample['id']}");
                $output->writeln("    before: {$sample['before']}");
                $output->writeln("    after:  {$sample['after']}");
            }
            $output->writeln('Nothing was written.');

            return Command::SUCCESS;
        }

        if (!$input->getOption('force') && !$this->confirmBackup($input, $output)) {
            $output->writeln('<comment>Aborted. Take a backup first, then run this again.</comment>');

            return Command::FAILURE;
        }

        $counts = $migrator->apply(function (int $done) use ($output) {
            $output->writeln("  ... {$done} revisions");
        });
        $output->writeln(sprintf(
            'Converted %d bodies, re-encoded %d canonically (%d already correct, %d empty) across %d revisions.',
            $counts['converted'],
            $counts['normalized'],
            $counts['already_json'],
            $counts['empty'],
            $counts['total']
        ));

        return $this->report($output, $migrator->verify());
    }

    /**
     * The backup gate. This rewrites every revision of the central table, so it asks
     * rather than assuming -- and it says where the backup should come from, because the
     * built-in archive command only produces a usable dump on MySQL (on SQLite the
     * honest backup is a copy of the database file).
     */
    private function confirmBackup(InputInterface $input, OutputInterface $output): bool
    {
        $driver = $this->services->get(DbService::class)->getDriver();
        $how = $driver === 'sqlite'
            ? 'copy the database file (see db_database in the config)'
            : './yeswicli core:archive --database-only';

        $output->writeln('<comment>This rewrites the body of every page revision, history included.</comment>');
        $output->writeln("Take a backup first: {$how}");

        /** @var \Symfony\Component\Console\Helper\QuestionHelper $helper */
        $helper = $this->getHelper('question');

        return (bool)$helper->ask($input, $output, new ConfirmationQuestion('Do you have a backup? [y/N] ', false));
    }

    /**
     * @param list<array{id: string, tag: string, reason: string}> $failures
     */
    private function report(OutputInterface $output, array $failures): int
    {
        if (empty($failures)) {
            $output->writeln('<info>Verified: every revision is in the target shape.</info>');

            return Command::SUCCESS;
        }

        $output->writeln('<error>' . count($failures) . ' revision(s) are not in the target shape:</error>');
        foreach (array_slice($failures, 0, 20) as $failure) {
            $output->writeln("  {$failure['tag']} #{$failure['id']}: {$failure['reason']}");
        }
        if (count($failures) > 20) {
            $output->writeln('  ... and ' . (count($failures) - 20) . ' more');
        }

        return Command::FAILURE;
    }
}
