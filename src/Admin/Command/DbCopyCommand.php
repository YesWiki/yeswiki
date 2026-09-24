<?php

namespace YesWiki\Admin\Command;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Admin\Service\DatabaseCopier;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Kernel\Service\DbService;

/** `./yeswicli db:copy` -- this wiki's tables into an empty database, on the same engine or another. */
class DbCopyCommand extends Command
{
    public const PASSWORD_ENV = 'YESWIKI_COPY_PASSWORD';

    public function __construct(private readonly ContainerInterface $services)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('db:copy')
            ->setDescription('Copy this wiki\'s database into an empty one, on MySQL, PostgreSQL or SQLite.')
            ->setHelp("The target database must exist and hold no table under the prefix.\n"
                . 'The password is read from ' . self::PASSWORD_ENV . ", so it never shows in the process list.\n"
                . "--write-config switches the wiki to the copy, and only when every table has the same row count.\n\n"
                . '  YESWIKI_COPY_PASSWORD=... ./yeswicli db:copy --driver=pgsql --host=127.0.0.1 --database=fairetilt --user=fairetilt --write-config')
            ->addOption('driver', null, InputOption::VALUE_REQUIRED, 'mysql, pgsql or sqlite')
            ->addOption('host', null, InputOption::VALUE_REQUIRED, 'Target host', '127.0.0.1')
            ->addOption('port', null, InputOption::VALUE_REQUIRED, 'Target port, the engine\'s default when empty', '')
            ->addOption('database', null, InputOption::VALUE_REQUIRED, 'Target database, or the file for sqlite')
            ->addOption('user', null, InputOption::VALUE_REQUIRED, 'Target user', '')
            ->addOption('prefix', null, InputOption::VALUE_REQUIRED, 'Table prefix, this wiki\'s own by default')
            ->addOption('write-config', null, InputOption::VALUE_NONE, 'Point this wiki at the copy once it is verified');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $driver = (string)$input->getOption('driver');
        $database = (string)$input->getOption('database');
        if ($driver === '' || $database === '') {
            $output->writeln('<error>--driver and --database are required.</error>');

            return Command::INVALID;
        }
        $params = $this->services->get(ParameterBagInterface::class);
        $prefix = (string)($input->getOption('prefix') ?: $params->get('table_prefix'));
        $host = (string)$input->getOption('host');
        $port = (string)$input->getOption('port');
        $user = (string)$input->getOption('user');
        $password = (string)getenv(self::PASSWORD_ENV);

        $copier = new DatabaseCopier($this->services->get(DbService::class));
        try {
            $target = DatabaseCopier::connect($driver, $host, $port, $database, $user, $password);
            $counts = $copier->copy($target, $prefix, function (string $table, int $rows) use ($output): void {
                $output->writeln(sprintf('  %-28s %8d row(s)', $table, $rows));
            });
        } catch (\Throwable $failure) {
            $output->writeln('<error>' . $failure->getMessage() . '</error>');

            return Command::FAILURE;
        }

        foreach ($copier->notes() as $note) {
            $output->writeln("note | {$note}");
        }
        $mismatches = array_filter($counts, fn (array $pair): bool => $pair[0] !== $pair[1]);
        foreach ($mismatches as $table => [$from, $to]) {
            $output->writeln("<error>{$table}: {$from} row(s) in the source, {$to} in the copy</error>");
        }
        if ($mismatches !== []) {
            return Command::FAILURE;
        }
        $output->writeln('copied and verified: ' . array_sum(array_column($counts, 0)) . ' row(s) in ' . count($counts) . ' table(s)');

        if ($input->getOption('write-config')) {
            $this->pointConfigAt($driver, $host, $port, $database, $user, $password, $prefix);
            $output->writeln('yeswiki.config.php now points at the copy; run ./yeswicli cache:clear if a worker serves this wiki');
        }

        return Command::SUCCESS;
    }

    /** Rewrites the connection keys, and drops the Doryphore `mysql_*` ones they would otherwise shadow. */
    private function pointConfigAt(string $driver, string $host, string $port, string $database, string $user, string $password, string $prefix): void
    {
        $config = $this->services->get(ConfigurationService::class)->getConfiguration(ConfigurationFileProvider::getConfigFileFromEnv());
        $config->load();
        foreach (['mysql_host', 'mysql_database', 'mysql_user', 'mysql_password', 'mysql_port'] as $legacy) {
            unset($config[$legacy]);
        }
        $config['db_driver'] = $driver;
        $config['db_host'] = $host;
        $config['db_port'] = $port;
        $config['db_database'] = $database;
        $config['db_user'] = $user;
        $config['db_password'] = $password;
        $config['table_prefix'] = $prefix;
        $config->write();
    }
}
