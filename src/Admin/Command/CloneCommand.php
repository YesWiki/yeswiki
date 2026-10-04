<?php

namespace YesWiki\Admin\Command;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use YesWiki\Admin\Service\ArchiveFilename;
use YesWiki\Admin\Service\ArchiveService;
use YesWiki\Admin\Service\RemoteWikiArchive;
use YesWiki\Files\Service\LocalFiles;
use YesWiki\Files\Service\Storage;

/** Fill this wiki with the contents of a remote one (first-class-binary 06). */
class CloneCommand extends Command
{
    public function __construct(protected ContainerInterface $services)
    {
        parent::__construct();
    }

    /** LocalFiles from the container, which is all `src/commands/console` hands a command. */
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
        $archives = $this->services->get(ArchiveService::class);
        $name = ArchiveFilename::forNow('full', RemoteWikiArchive::baseUrlOf($url));
        $downloaded = rtrim(sys_get_temp_dir(), '/') . '/yeswiki-' . $name;
        $remote = new RemoteWikiArchive(static fn (string $message) => $io->text($message));

        try {
            $remote->fetchInto($url, $admin, $password, $downloaded);
            $this->assertRoomInBackups($storage, $this->localFiles()->size($downloaded));
            $storage->writeFrom($archives->getPrivateFolder() . '/' . $name, $downloaded);
        } catch (\Throwable $th) {
            $io->error('Nothing was changed here: ' . $th->getMessage());

            return Command::FAILURE;
        } finally {
            $this->forget($downloaded);
        }

        try {
            $archives->restoreArchive($name, true, true);
        } catch (\Throwable $th) {
            $io->error('The restore failed and this wiki was left as it was: ' . $th->getMessage());

            return Command::FAILURE;
        }

        if (!$input->getOption('keep-archive')) {
            $storage->delete($archives->getPrivateFolder() . '/' . $name);
        }

        $io->success('Cloned ' . RemoteWikiArchive::baseUrlOf($url) . ' into this wiki.');
        $io->text('It keeps its own address, database and storage: ' . implode(', ', ArchiveService::localOnlyFiles()) . ' were not restored, and the settings ' . implode(', ', ArchiveService::localOnlyKeys()) . ' stayed as they were.');

        return Command::SUCCESS;
    }

    /**
     * @throws \Exception when the backups folder cannot take a copy of the download
     */
    private function assertRoomInBackups(Storage $storage, int $bytes): void
    {
        $archives = $this->services->get(ArchiveService::class);
        if ($storage->isRemote($archives->getPrivateFolder())) {
            return;
        }
        $free = $archives->freeSpaceForArchives();
        if ($free !== null && $free < $bytes) {
            throw new \Exception('Not enough free space in ' . $archives->getPrivateFolder() . ' for the downloaded backup' . ArchiveService::spaceDetail($bytes, $free) . '.');
        }
    }

    private function forget(string $path): void
    {
        if ($this->localFiles()->isFile($path)) {
            $this->localFiles()->remove($path);
        }
    }
}
