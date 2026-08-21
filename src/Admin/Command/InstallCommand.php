<?php

namespace YesWiki\Admin\Command;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use YesWiki\Admin\Service\InstallationService;
use YesWiki\Init;
use YesWiki\Kernel\Command\RunsOutsideAnInstance;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\EnvironmentConfiguration;
use YesWiki\Kernel\Service\LanguageService;

/** Install a wiki from the terminal (ticket 44). */
class InstallCommand extends Command implements RunsOutsideAnInstance
{
    /** Options that are configuration, as option name => config key. */
    private const CONFIG_OPTIONS = [
        'driver' => 'db_driver',
        'db-host' => 'db_host',
        'db-port' => 'db_port',
        'db-database' => 'db_database',
        'db-user' => 'db_user',
        'db-password' => 'db_password',
        'table-prefix' => 'table_prefix',
        'base-url' => 'base_url',
        'root-page' => 'root_page',
        'wiki-name' => 'yeswiki_name',
        'language' => 'default_language',
    ];

    protected ?ContainerInterface $services;

    public function __construct(?ContainerInterface $services = null)
    {
        parent::__construct();
        $this->services = $services;
    }

    protected function configure()
    {
        $this
            ->setName('core:install')
            ->setDescription('Install this wiki: create its database, its first account and its configuration file.')
            ->setHelp(
                "Runs the same steps as the web installer, in the terminal.\n\n" .
                "Interactive by default. Pass every value as an option (or force it from\n" .
                "private/.env or the environment) and add --no-interaction to install without\n" .
                "being asked anything, which is what a provisioning script wants.\n\n" .
                "  ./yeswicli core:install --driver=sqlite --admin-password=… --no-interaction\n\n" .
                "With --from-backup it restores private/backups/content.sql instead of creating\n" .
                "the default pages, so a wiki can be moved without a browser.\n"
            );

        foreach (self::CONFIG_OPTIONS as $option => $key) {
            $this->addOption($option, null, InputOption::VALUE_REQUIRED, "Configuration: $key");
        }

        $this
            ->addOption('other-languages', null, InputOption::VALUE_REQUIRED, 'Further languages this wiki offers, comma separated')
            ->addOption('admin-name', null, InputOption::VALUE_REQUIRED, 'Name of the first account')
            ->addOption('admin-email', null, InputOption::VALUE_REQUIRED, 'Email of the first account')
            ->addOption('admin-password', null, InputOption::VALUE_REQUIRED, 'Password of the first account')
            ->addOption('from-backup', null, InputOption::VALUE_NONE, 'Restore ' . InstallationService::BACKUP_SQL_FILE . ' instead of installing the default content')
            ->addOption('allow-robots', null, InputOption::VALUE_NONE, 'Let search engines index this wiki')
            ->addOption('allow-raw-html', null, InputOption::VALUE_NONE, 'Allow raw HTML in page content')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $configFile = ConfigurationFileProvider::getConfigFileFromEnv();

        if (InstallationService::alreadyConfigured($configFile)) {
            $io->error("$configFile already configures a wiki. Remove it first if you mean to install over it.");

            return Command::FAILURE;
        }

        $stated = [];
        foreach (self::CONFIG_OPTIONS as $option => $key) {
            $given = $input->getOption($option);
            if (is_string($given) && $given !== '') {
                $stated[$key] = $given;
            }
        }
        $otherLanguages = $input->getOption('other-languages');
        if (is_string($otherLanguages) && $otherLanguages !== '') {
            $stated['other_languages'] = array_values(array_filter(array_map('trim', explode(',', $otherLanguages))));
        }

        $forced = InstallationService::environmentForcedValues();
        $config = $this->defaultConfig(array_merge($stated, $forced));
        $config['allow_robots'] = $input->getOption('allow-robots') ? '1' : '0';
        $config['allow_raw_html'] = $input->getOption('allow-raw-html') ? '1' : '0';

        $drivers = InstallationService::availableDrivers();
        if ($drivers === []) {
            $io->error('No PDO driver is loaded: this PHP cannot talk to any database YesWiki supports.');

            return Command::FAILURE;
        }

        if (is_string($config['default_language'] ?? null) && $config['default_language'] !== '') {
            LanguageService::getInstance()->loadPreferredLanguage((object)['config' => $config]);
        }

        $io->title('Installing a YesWiki in ' . YESWIKI_INSTANCE_DIR);

        if ($input->isInteractive()) {
            $config = $this->ask($io, $config, $forced, $drivers);
        }

        $config = EnvironmentConfiguration::apply(array_merge($config, $forced));

        $config = $this->defaultConfig($config);

        $statedBaseUrl = isset($stated['base_url']) || isset($forced['base_url']) || $input->isInteractive();
        if (!$statedBaseUrl || trim((string)$config['base_url']) === '') {
            $io->error('No base URL. Pass --base-url="https://example.org/?" (or set BASE_URL).');

            return Command::FAILURE;
        }

        if (!isset($drivers[$config['db_driver']])) {
            $io->error("No pdo_{$config['db_driver']} here. Available: " . implode(', ', array_keys($drivers)) . '.');

            return Command::FAILURE;
        }

        $admin = [
            'name' => $this->value($input, 'admin-name', 'ADMIN_NAME', 'WikiAdmin'),
            'email' => $this->value($input, 'admin-email', 'ADMIN_EMAIL', ''),
            'password' => $this->value($input, 'admin-password', 'ADMIN_PASSWORD', ''),
        ];
        if ($input->isInteractive()) {
            $admin = $this->askAdmin($io, $admin);
        }

        $service = (new InstallationService($config, $configFile))
            ->withAdminAccount($admin['name'], $admin['email'], $admin['password']);

        if ($input->getOption('from-backup')) {
            if (!InstallationService::hasBackup()) {
                $io->error('No backup at ' . InstallationService::backupFile() . '.');

                return Command::FAILURE;
            }
            $service->withContentFrom(InstallationService::BACKUP_SQL_FILE);
        }

        try {
            $service->install();
        } catch (\Exception $ex) {
            $this->report($io, $service->messages());
            $io->error($this->plain($ex->getMessage()));

            return Command::FAILURE;
        }

        $this->report($io, $service->messages());
        $installed = $service->config();
        $io->success('Wiki installed. It is at ' . $installed['base_url'] . $installed['root_page']);

        return Command::SUCCESS;
    }

    /**
     * @param array<string, mixed>  $config
     * @param array<string, mixed>  $forced
     * @param array<string, string> $drivers
     *
     * @return array<string, mixed>
     */
    private function ask(SymfonyStyle $io, array $config, array $forced, array $drivers): array
    {
        if (!isset($forced['db_driver'])) {
            $config['db_driver'] = (string)$io->choice('Database', array_keys($drivers), (string)$config['db_driver']);
        }

        if ($config['db_driver'] !== 'sqlite') {
            foreach (['db_host' => 'Database host', 'db_database' => 'Database name', 'db_user' => 'Database user'] as $key => $label) {
                if (!isset($forced[$key])) {
                    $config[$key] = (string)$io->ask($label, (string)($config[$key] ?: ''));
                }
            }
            if (!isset($forced['db_password'])) {
                $config['db_password'] = (string)$io->askHidden('Database password (leave empty for none)') ?: '';
            }
        }

        foreach (
            [
                'table_prefix' => 'Table prefix',
                'base_url' => 'Base URL of the wiki',
                'yeswiki_name' => 'Name of the wiki',
                'root_page' => 'Home page name',
                'default_language' => 'Default language',
            ] as $key => $label
        ) {
            if (!isset($forced[$key])) {
                $config[$key] = (string)$io->ask($label, (string)($config[$key] ?: ''));
            }
        }

        return $config;
    }

    /**
     * @param array{name: string, email: string, password: string} $admin
     *
     * @return array{name: string, email: string, password: string}
     */
    private function askAdmin(SymfonyStyle $io, array $admin): array
    {
        $io->section('The first account');
        $admin['name'] = (string)$io->ask('Administrator name', $admin['name'] ?: 'WikiAdmin');
        $admin['email'] = (string)$io->ask('Administrator email', $admin['email'] ?: null);
        if ($admin['password'] === '') {
            $admin['password'] = (string)$io->askHidden('Administrator password (at least 5 characters)');
        }

        return $admin;
    }

    /** An option, or what the environment says, or a default. */
    private function value(InputInterface $input, string $option, string $envName, string $default): string
    {
        $given = $input->getOption($option);
        if (is_string($given) && $given !== '') {
            return $given;
        }
        $env = getenv($envName);

        return $env === false ? $default : $env;
    }

    /** @param list<array{result: string, output: string}> $messages */
    private function report(SymfonyStyle $io, array $messages): void
    {
        foreach ($messages as $message) {
            $text = $this->plain($message['output']);
            if ($message['result'] === 'success') {
                $io->writeln(" <info>✓</info> $text");
            } else {
                $io->writeln(" <comment>!</comment> $text");
            }
        }
    }

    /** The installer's messages are written for a web page, and Symfony's console reads `<br />` as a style tag it does not know. */
    private function plain(string $message): string
    {
        return trim(html_entity_decode(strip_tags(str_replace(['<br />', '<br>'], "\n", $message))));
    }

    /**
     * The configuration defaults, which is what the web installer starts from too.
     *
     * @param array<string, mixed> $stated values the operator gave, so that everything getConfig()
     *
     * @return array<string, mixed>
     */
    private function defaultConfig(array $stated = []): array
    {
        require_once YESWIKI_PROGRAM_DIR . '/src/constants.php';
        require_once YESWIKI_PROGRAM_DIR . '/src/Kernel/Service/LanguageService.php';
        require_once YESWIKI_PROGRAM_DIR . '/src/YesWikiInit.php';

        $_SERVER['HTTP_HOST'] ??= 'localhost';
        $_SERVER['REQUEST_URI'] ??= '/';
        $_SERVER['SCRIPT_NAME'] ??= '/index.php';

        LanguageService::getInstance()->initialize();

        $init = (new \ReflectionClass(Init::class))->newInstanceWithoutConstructor();
        $init->configFile = ConfigurationFileProvider::getConfigFileFromEnv();

        return $init->getConfig($stated);
    }
}
