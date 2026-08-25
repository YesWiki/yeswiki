<?php

namespace YesWiki\Admin\Command;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use YesWiki\Admin\Service\ArchiveService;
use YesWiki\Admin\Service\RemoteWikiArchive;
use YesWiki\Files\Service\LocalFiles;
use YesWiki\Files\Service\Storage;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\ConfigurationService;

/** Fill this wiki with the contents of a remote one (first-class-binary 06). */
class CloneCommand extends Command
{
    public function __construct(protected ContainerInterface $services)
    {
        parent::__construct();
    }

    /** Resolved rather than injected: `src/commands/console` builds commands with the container alone. */
    private function localFiles(): LocalFiles
    {
        return $this->services->get(LocalFiles::class);
    }

    protected function configure(): void
    {
        $this
            ->setName('core:clone')
            ->setDescription('Fill this wiki with a remote wiki\'s pages, entries, users and uploads.')
            ->setHelp(
                "Asks the remote wiki for a full archive, waits for it, downloads it and restores\n" .
                "it here. Needs an administrator account on the remote wiki.\n\n" .
                "This wiki keeps its own URL, database, bucket and table prefix: only the contents\n" .
                "come across. The archive is restored beside the existing tables and put in their\n" .
                "place only once it is all there, so an interrupted restore leaves this wiki as it\n" .
                "was.\n\n" .
                "The password is asked for rather than passed, so it stays out of ps and history.\n" .
                'Set REMOTE_ADMIN_PASSWORD to run without being asked.'
            )
            ->addOption('from-wiki', null, InputOption::VALUE_REQUIRED, 'Address of the wiki to clone')
            ->addOption('remote-admin', null, InputOption::VALUE_REQUIRED, 'An administrator of that wiki')
            ->addOption('keep-archive', null, InputOption::VALUE_NONE, 'Leave the downloaded archive in private/backups');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $url = trim((string)$input->getOption('from-wiki'));
        $admin = trim((string)$input->getOption('remote-admin'));

        if ($url === '' || $admin === '') {
            $io->error('--from-wiki names the wiki to clone and --remote-admin an administrator of it.');

            return Command::FAILURE;
        }

        $password = (string)getenv('REMOTE_ADMIN_PASSWORD');
        if ($password === '') {
            if (!$input->isInteractive()) {
                $io->error('No password for ' . $admin . '. Set REMOTE_ADMIN_PASSWORD, or run without --no-interaction to be asked.');

                return Command::FAILURE;
            }
            $password = (string)$io->askHidden("Password for $admin on $url");
        }
        if ($password === '') {
            $io->error('No password given.');

            return Command::FAILURE;
        }

        $storage = $this->services->get(Storage::class);
        $name = 'cloned-' . date('Y-m-d\TH-i-s') . '.zip';
        $downloaded = rtrim(sys_get_temp_dir(), '/') . '/yeswiki-' . $name;
        $remote = new RemoteWikiArchive(static fn (string $message) => $io->text($message));

        try {
            $remote->fetchInto($url, $admin, $password, $downloaded);
            $storage->writeFrom('private/backups/' . $name, $downloaded);
        } catch (\Throwable $th) {
            $io->error('Nothing was changed here: ' . $th->getMessage());

            return Command::FAILURE;
        } finally {
            $this->forget($downloaded);
        }

        try {
            $this->services->get(ArchiveService::class)->restoreArchive($name, true, true);
        } catch (\Throwable $th) {
            $io->error('The restore failed and this wiki was left as it was: ' . $th->getMessage());

            return Command::FAILURE;
        }

        try {
            $took = $this->takeRemoteSettings($storage, $name);
            $io->text('Took ' . $took . ' setting(s) from the remote wiki, keeping this one\'s own address, database and prefix.');
        } catch (\Throwable $th) {
            $io->warning('The contents were restored, but the remote wiki\'s settings could not be read: ' . $th->getMessage());
        }

        if (!$input->getOption('keep-archive')) {
            $storage->delete('private/backups/' . $name);
        }

        $io->success('Cloned ' . RemoteWikiArchive::baseUrlOf($url) . ' into this wiki.');
        $io->text('It keeps its own address, database and storage: ' . implode(', ', ArchiveService::localOnlyFiles()) . ' were not restored.');

        return Command::SUCCESS;
    }

    /**
     * Copy the remote wiki's configuration over this one's, except what says where this wiki is.
     *
     * @return int how many settings came across
     *
     * @throws \Exception when the archive's configuration cannot be read
     */
    private function takeRemoteSettings(Storage $storage, string $name): int
    {
        $remote = [];
        $storage->withLocalCopy('private/backups/' . $name, function (string $local) use (&$remote): void {
            $zip = new \ZipArchive();
            if ($zip->open($local) !== true) {
                throw new \Exception('the archive could not be opened');
            }

            try {
                $stated = $zip->getFromName(basename(ConfigurationFileProvider::getConfigFileFromEnv()));
                if ($stated === false) {
                    throw new \Exception('it holds no configuration file');
                }
                $remote = self::readConfig($stated);
            } finally {
                $zip->close();
            }
        });

        $service = new ConfigurationService();
        $configuration = $service->getConfiguration(ConfigurationFileProvider::getConfigFileFromEnv());
        $configuration->load();

        $before = \count($configuration);
        if ($before === 0) {
            throw new \Exception('this wiki\'s own configuration could not be read, so it was left alone');
        }

        $local = [];
        foreach ($configuration as $key => $value) {
            if (is_string($key)) {
                $local[$key] = $value;
            }
        }

        $merged = ArchiveService::mergedSettings($local, $remote);
        foreach ($merged as $key => $value) {
            $configuration[$key] = $value;
        }

        if (\count($configuration) < $before) {
            throw new \Exception('the merged configuration came out smaller than this wiki\'s own, so it was left alone');
        }

        if (!$service->write($configuration)) {
            throw new \Exception('this wiki\'s configuration file could not be written');
        }

        return \count(array_diff_key($remote, array_flip(ArchiveService::localOnlyKeys())));
    }

    /**
     * The configuration an archive holds, read the same way the wiki reads its own.
     *
     * @return array<string, mixed>
     *
     * @throws \Exception when it cannot be put somewhere to be read
     */
    private static function readConfig(string $stated): array
    {
        // Static, so LocalFiles is built here: it holds nothing and this reads a remote wiki's
        // configuration before there is a wiki to ask about it.
        $localFiles = new LocalFiles();
        $file = $localFiles->temporaryFile('yeswiki-remote-config');

        try {
            $localFiles->write($file, $stated);
            $configuration = (new ConfigurationService())->getConfiguration($file);
            $configuration->load();

            $read = [];
            foreach ($configuration as $key => $value) {
                if (is_string($key)) {
                    $read[$key] = $value;
                }
            }

            return $read;
        } finally {
            $localFiles->remove($file);
        }
    }

    private function forget(string $path): void
    {
        if ($this->localFiles()->isFile($path)) {
            $this->localFiles()->remove($path);
        }
    }
}
