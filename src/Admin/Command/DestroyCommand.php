<?php

namespace YesWiki\Admin\Command;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Admin\Service\ArchiveService;
use YesWiki\Admin\Service\DatabaseProvisioner;
use YesWiki\Files\Entity\S3Settings;
use YesWiki\Files\Service\BucketProvisioner;
use YesWiki\Files\Service\LocalFiles;
use YesWiki\Files\Service\Storage;

/** Take a wiki's archive and then drop the database, the account and the bucket it owned (first-class-binary 05). */
class DestroyCommand extends Command
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
            ->setName('core:destroy')
            ->setDescription('Archive this wiki, then drop the database, account and bucket it owns.')
            ->setHelp(
                "Takes a full archive first and stops if it cannot: a backup that failed is a\n" .
                "refusal to destroy, not a warning to click past.\n\n" .
                "The wiki's directory is not removed -- `yeswiki destroy` does that after this\n" .
                "returns. --confirm must name the host this wiki answers to.\n"
            )
            ->addOption('confirm', null, InputOption::VALUE_REQUIRED, 'The host this wiki answers to, which has to be typed to destroy it')
            ->addOption('archive-to', null, InputOption::VALUE_REQUIRED, 'Where the archive is left, outside the wiki')
            ->addOption('db-admin-user', null, InputOption::VALUE_REQUIRED, 'Drop the database and account as this administrator (or DB_ADMIN_USER)')
            ->addOption('db-admin-password', null, InputOption::VALUE_REQUIRED, 'Password of that administrator (or DB_ADMIN_PASSWORD)')
            ->addOption('s3-admin-key', null, InputOption::VALUE_REQUIRED, 'Drop the bucket with this key (or S3_ADMIN_KEY)')
            ->addOption('s3-admin-secret', null, InputOption::VALUE_REQUIRED, 'Secret of that key (or S3_ADMIN_SECRET)')
            ->addOption('keep-database', null, InputOption::VALUE_NONE, 'Leave the database and its account alone')
            ->addOption('keep-bucket', null, InputOption::VALUE_NONE, 'Leave the object storage alone')
            ->addOption('keep-archive', null, InputOption::VALUE_NONE, 'Skip the archive, for a wiki whose database is already gone');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $config = $this->services->get(ParameterBagInterface::class)->all();

        $host = self::hostOf((string)($config['base_url'] ?? ''));
        $confirmed = trim((string)$input->getOption('confirm'));

        if ($host === '') {
            $io->error('This wiki has no base_url, so there is no name to confirm it by.');

            return Command::FAILURE;
        }
        if ($confirmed !== $host) {
            $io->error("--confirm has to name the wiki being destroyed. This one is $host.");

            return Command::FAILURE;
        }

        if (!$input->getOption('keep-archive')) {
            if (($archived = $this->archive($io, $input)) === null) {
                return Command::FAILURE;
            }
            $io->success("Archived $host to $archived");
        }

        if (!$input->getOption('keep-database') && !$this->dropDatabase($io, $input, $config)) {
            return Command::FAILURE;
        }

        if (!$input->getOption('keep-bucket') && !$this->dropBucket($io, $input, $config)) {
            return Command::FAILURE;
        }

        $io->success("$host owns nothing else now. Its directory is still there.");

        return Command::SUCCESS;
    }

    private function archive(SymfonyStyle $io, InputInterface $input): ?string
    {
        $destination = trim((string)$input->getOption('archive-to'));
        if ($destination === '') {
            $io->error('--archive-to says where the archive is left. It has to be outside this wiki.');

            return null;
        }

        try {
            $written = '';
            $made = $this->services->get(ArchiveService::class)->synchronously()->archive($written, true, true);
        } catch (\Throwable $th) {
            $io->error('The archive failed, so nothing was destroyed: ' . $th->getMessage());

            return null;
        }

        if (!$this->services->get(Storage::class)->fileExists($made)) {
            $io->error("The archive reported $made, which is not there. Nothing was destroyed.");

            return null;
        }

        if ($this->localFiles()->isDirectory($destination)) {
            $destination = rtrim($destination, '/') . '/' . basename($made);
        }
        if (!$this->localFiles()->isDirectory(\dirname($destination))) {
            $io->error(\dirname($destination) . ' is not a directory. Nothing was destroyed.');

            return null;
        }

        try {
            $this->copyOut($made, $destination);
        } catch (\Throwable $th) {
            $io->error("Could not put the archive at $destination, so nothing was destroyed: " . $th->getMessage());

            return null;
        }

        return $destination;
    }

    /**
     * Copy the archive out through Storage, because a wiki on object storage keeps private/backups in its bucket -- the bucket this is about to delete (ADR-0022).
     *
     * @throws \Exception when the archive cannot be written to the destination
     */
    private function copyOut(string $archive, string $destination): void
    {
        $from = $this->services->get(Storage::class)->readStream($archive);
        $to = $this->localFiles()->openForWriting($destination);

        if ($to === null) {
            throw new \Exception("could not open $destination for writing");
        }

        try {
            if (stream_copy_to_stream($from, $to) === false) {
                throw new \Exception("could not write $destination");
            }
        } finally {
            fclose($to);
            if (is_resource($from)) {
                fclose($from);
            }
        }
    }

    /** @param array<string, mixed> $config */
    private function dropDatabase(SymfonyStyle $io, InputInterface $input, array $config): bool
    {
        $driver = (string)($config['db_driver'] ?? '');
        if (!DatabaseProvisioner::supports($driver)) {
            $io->text("A $driver database is a file inside the wiki, and goes when the directory does.");

            return true;
        }

        $adminUser = $this->value($input, 'db-admin-user', 'DB_ADMIN_USER');
        if ($adminUser === '') {
            $io->error('Dropping a ' . $driver . ' database needs --db-admin-user (or DB_ADMIN_USER). Nothing was destroyed.');

            return false;
        }

        $provisioner = new DatabaseProvisioner();

        try {
            $provisioner->destroy($adminUser, $this->value($input, 'db-admin-password', 'DB_ADMIN_PASSWORD'), $config);
        } catch (\Throwable $th) {
            $io->error('Could not drop the database: ' . $th->getMessage());

            return false;
        }

        $io->success('Dropped ' . implode(', ', $provisioner->done()));

        return true;
    }

    /** @param array<string, mixed> $config */
    private function dropBucket(SymfonyStyle $io, InputInterface $input, array $config): bool
    {
        if (S3Settings::forInstance(YESWIKI_INSTANCE_DIR) === null) {
            return true;
        }

        $provisioner = new BucketProvisioner();

        try {
            $provisioner->destroy(
                $this->value($input, 's3-admin-key', 'S3_ADMIN_KEY'),
                $this->value($input, 's3-admin-secret', 'S3_ADMIN_SECRET'),
                YESWIKI_INSTANCE_DIR,
                self::storageOf(YESWIKI_INSTANCE_DIR)
            );
        } catch (\Throwable $th) {
            $io->error('Could not drop the object storage: ' . $th->getMessage());

            return false;
        }

        $io->success('Dropped ' . implode(', ', $provisioner->done()));
        foreach ($provisioner->warnings() as $warning) {
            $io->warning($warning);
        }

        return true;
    }

    /**
     * The storage settings as the wiki states them, which live in private/.env and never in the configuration (first-class-binary 07).
     *
     * @return array<string, string>
     */
    private static function storageOf(string $instance): array
    {
        $stated = [];
        foreach (\YesWiki\Core\YesWikiLoader::envFileValues($instance) as $name => $value) {
            if (str_starts_with($name, 'YESWIKI_')) {
                $stated[strtolower(substr($name, \strlen('YESWIKI_')))] = $value;
            }
        }

        return $stated;
    }

    public static function hostOf(string $baseUrl): string
    {
        $baseUrl = trim($baseUrl);
        if ($baseUrl === '') {
            return '';
        }
        if (!str_contains($baseUrl, '://')) {
            $baseUrl = 'https://' . $baseUrl;
        }

        return strtolower((string)parse_url($baseUrl, PHP_URL_HOST));
    }

    private function value(InputInterface $input, string $option, string $envName): string
    {
        $given = $input->getOption($option);
        if (is_string($given) && $given !== '') {
            return $given;
        }
        $env = getenv($envName);

        return $env === false ? '' : $env;
    }
}
