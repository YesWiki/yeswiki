<?php

namespace YesWiki\Files\Command;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use YesWiki\Files\Entity\S3Settings;
use YesWiki\Files\Service\Storage;

/** Move an existing wiki's Data tiers to wherever it is now configured to keep them (ADR-0022). */
class StorageSyncCommand extends Command
{
    /**
     * What an instance owns, from the most important to the least. Backups are opt-in: they are usually the largest thing here and the least urgent to have remote.
     *
     * @var array<string, bool> path => whether `--with-backups` is needed for it
     */
    private const ROOTS = [
        'private/files' => false,
        'custom' => false,
        'files' => false,
        'private/backups' => true,
    ];

    public function __construct(protected ContainerInterface $services)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('storage:sync')
            ->setDescription('Copy this wiki\'s files to the storage it is configured for.')
            ->setHelp(
                "Reads private/.env. With YESWIKI_STORAGE=local there is nothing to do.\n"
                . 'Re-runnable: an object already there at the same size is left alone.'
            )
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would be copied, and copy nothing')
            ->addOption('with-backups', null, InputOption::VALUE_NONE, 'Include private/backups, which is usually the largest');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $settings = S3Settings::fromEnvironment();
        if ($settings === null) {
            $output->writeln('<comment>YESWIKI_STORAGE is local: this wiki keeps its files here, and there is nothing to sync.</comment>');

            return Command::SUCCESS;
        }

        $root = \defined('YESWIKI_INSTANCE_DIR') ? YESWIKI_INSTANCE_DIR : (string)getcwd();
        $here = Storage::rootedAt($root);
        $there = Storage::rootedAtWith($root, $settings);

        if (!$input->getOption('dry-run') && $there->createBucketIfMissing()) {
            $output->writeln(sprintf('<info>Created the bucket %s.</info>', $settings->bucket));
        }

        $dryRun = (bool)$input->getOption('dry-run');
        $withBackups = (bool)$input->getOption('with-backups');

        $copied = 0;
        $skipped = 0;
        $bytes = 0;

        foreach (self::ROOTS as $directory => $optIn) {
            if ($optIn && !$withBackups) {
                $count = count($here->files($directory, true));
                if ($count > 0) {
                    $output->writeln(sprintf('  %-20s %6d objects   [--with-backups to include]', $directory, $count));
                }
                continue;
            }

            $directoryCopied = 0;
            $directoryBytes = 0;
            foreach ($here->files($directory, true) as $path) {
                if (!$there->isRemote($path)) {
                    continue;
                }
                $size = $here->fileSize($path);
                if ($there->fileExists($path) && $there->fileSize($path) === $size) {
                    $skipped++;
                    continue;
                }
                if (!$dryRun) {
                    $there->writeStream($path, $here->readStream($path));
                }
                $directoryCopied++;
                $directoryBytes += $size;
            }

            $copied += $directoryCopied;
            $bytes += $directoryBytes;
            if ($directoryCopied > 0) {
                $output->writeln(sprintf('  %-20s %6d objects  %s', $directory, $directoryCopied, self::humanBytes($directoryBytes)));
            }
        }

        $output->writeln('');
        $output->writeln(sprintf(
            '<info>%s %d objects (%s)%s</info>',
            $dryRun ? 'Would copy' : 'Copied',
            $copied,
            self::humanBytes($bytes),
            $skipped > 0 ? sprintf(', %d already there', $skipped) : ''
        ));

        if ($dryRun) {
            $output->writeln('<comment>Nothing was written: this was --dry-run.</comment>');
        }

        return Command::SUCCESS;
    }

    private static function humanBytes(int $bytes): string
    {
        $units = ['B', 'kB', 'MB', 'GB', 'TB'];
        $unit = 0;
        $size = (float)$bytes;
        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return sprintf($unit === 0 ? '%d %s' : '%.1f %s', $size, $units[$unit]);
    }
}
