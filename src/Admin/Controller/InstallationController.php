<?php

namespace YesWiki\Admin\Controller;

use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
use YesWiki\Core\YesWikiLoader;
use YesWiki\Identity\Entity\User;
use YesWiki\Kernel\Database\SqlDialectFactory;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Kernel\Service\EnvironmentConfiguration;
use YesWiki\Kernel\Service\LanguageService;
use YesWiki\Kernel\Service\WikiUrls;
use YesWiki\Render\Service\LayoutService;
use YesWiki\Search\Service\SearchIndexSchema;

/** Web installer, run by Init::doInstall() when the configuration file does not exist yet. */
class InstallationController
{
    /** Legacy POST/config keys still accepted, mapped to their current names. */
    private const LEGACY_KEY_MAPPING = [
        'mysql_host' => 'db_host',
        'mysql_database' => 'db_database',
        'mysql_user' => 'db_user',
        'mysql_password' => 'db_password',
        'mysql_port' => 'db_port',
        'wakka_name' => 'yeswiki_name',
    ];

    private const TABLE_NAMES = ['pages', 'triples'];

    /**
     * A database backup at this location (instance-relative) can be restored instead of the default content.
     */
    public const BACKUP_SQL_FILE = 'private/backups/content.sql';

    /** SQLite databases always live at this instance-relative path. */
    private const SQLITE_DATABASE_PATH = 'private/yeswiki.db';

    /** @var array<string, mixed> */
    protected array $config;
    protected string $configFile;
    /** @var array<string, mixed> the `config` values this request posted back, legacy keys already mapped */
    protected array $configPosted;
    /** @var array<string, mixed> configuration values the environment forces, which the form shows as read-only */
    protected array $env;
    protected string $step;
    protected string $baseUrl;
    protected \Twig\Environment $twig;
    protected string $adminName;
    protected string $adminEmail;
    protected string $adminPassword;
    protected string $adminPasswordConf;
    /** @var string either self::BACKUP_SQL_FILE or 'default' */
    protected string $contentSQL;

    /**
     * @var \PDO|null
     */
    protected $dbLink;

    /** The driver's own message for the last failed execSqlTemplate(), for the error page. */
    private ?string $lastSqlError = null;

    /**
     * @var list<array{result: string, output: string}> every check already passed
     */
    protected array $messages = [];

    /** The connection, once there is one. */
    private function db(): \PDO
    {
        if ($this->dbLink === null) {
            throw new \Exception('the installer used the database before connecting to it');
        }

        return $this->dbLink;
    }

    /**
     * @param array<string, mixed> $config     configuration from Init::getConfig() (defaults + environment overrides)
     * @param string               $configFile path of the yeswiki.config.php file to create
     */
    public function __construct(array $config, string $configFile)
    {
        LanguageService::getInstance()->loadPreferredLanguage((object)['config' => $config]);

        $this->config = $config;
        $this->configFile = $configFile;
        $this->env = $this->environmentForcedValues();

        if (isset($_POST['config']) && is_string($_POST['config'])) {
            $_POST['config'] = json_decode(html_entity_decode($_POST['config']), true) ?? [];
        }
        $this->configPosted = $this->mapLegacyKeys($_POST['config'] ?? []);

        $this->config = EnvironmentConfiguration::apply(array_merge($this->config, $this->configPosted));

        $this->adminName = $this->postedOrEnv('admin_name', 'ADMIN_NAME');
        $this->adminEmail = $this->postedOrEnv('admin_email', 'ADMIN_EMAIL');
        $this->adminPassword = $this->postedOrEnv('admin_password', 'ADMIN_PASSWORD');
        $this->adminPasswordConf = $this->postedOrEnv('admin_password_conf', 'ADMIN_PASSWORD');
        $postedContentSQL = $_POST['contentSQL'] ?? null;
        $this->contentSQL = is_string($postedContentSQL)
            ? $postedContentSQL
            : (file_exists(self::BACKUP_SQL_FILE) ? self::BACKUP_SQL_FILE : 'default');

        $this->step = trim($_REQUEST['installAction'] ?? '') ?: 'default';
        $this->baseUrl = WikiUrls::baseUrl(true);
        $this->twig = $this->setupTwig();
    }

    public function run(): void
    {
        header('Content-Type: text/html; charset=UTF-8');
        if ($this->step === 'install') {
            $this->install();
        } else {
            echo $this->render('installation-form.twig');
        }
    }

    /**
     * Run all installation steps; on failure display the checks done so far, the error, and a retry form carrying the posted values.
     */
    protected function install(): void
    {
        try {
            $this->connectDatabase();
            $this->checkTablePrefix();
            if ($this->useBackup()) {
                $this->importBackup();
            } else {
                $this->validateAdminAccount();
                $this->validateRootPage();
                $this->installDatabaseContent();
            }
            $this->writeRobotsTxtFile();
            $this->writeConfigFile();

            header('Location: ' . $this->config['base_url'] . $this->config['root_page']);
            exit;
        } catch (\Exception $ex) {
            echo $this->render('installation-database.twig', [
                'messages' => $this->messages,
                'error' => $ex->getMessage(),
                'configPosted' => htmlspecialchars(json_encode($this->configPosted) ?: '{}', ENT_COMPAT, 'UTF-8'),
            ]);
        }
    }

    /** @param array<string, mixed> $extraOptions */
    protected function render(string $template, array $extraOptions = []): string
    {
        $availableDrivers = [];
        if (extension_loaded('pdo_mysql')) {
            $availableDrivers['mysql'] = 'MySQL / MariaDB';
        }
        if (extension_loaded('pdo_sqlite')) {
            $availableDrivers['sqlite'] = 'SQLite';
        }
        if (extension_loaded('pdo_pgsql')) {
            $availableDrivers['pgsql'] = 'PostgreSQL';
        }

        $options = array_merge([
            'template' => $template,
            'baseUrl' => $this->baseUrl,
            'config' => $this->config,
            'env' => $this->env,
            'locale' => LanguageService::getInstance()->preferredLanguage(),

            'installedLanguages' => $GLOBALS['installed_languages'] ?? $GLOBALS['available_languages'],
            'languagesList' => $GLOBALS['languages_list'],
            'availableDrivers' => $availableDrivers,
            'yeswikiVersion' => ucfirst(YESWIKI_VERSION) . ' ' . YESWIKI_RELEASE,
            'pattern' => WN_CAMEL_CASE_EVOLVED,
            'backupFound' => file_exists(self::BACKUP_SQL_FILE),
            'backupSqlFile' => self::BACKUP_SQL_FILE,
            'contentSQL' => $this->contentSQL,
            'adminName' => $this->adminName ?: 'WikiAdmin',
            'adminEmail' => $this->adminEmail,
            'adminPassword' => $this->adminPassword,
            'adminPasswordConf' => $this->adminPasswordConf,
            'adminEnvForced' => [
                'name' => $this->envValue('ADMIN_NAME') !== null,
                'email' => $this->envValue('ADMIN_EMAIL') !== null,
                'password' => $this->envValue('ADMIN_PASSWORD') !== null,
            ],
        ], $extraOptions);

        return $this->twig->render('installation.twig', $options);
    }

    protected function useBackup(): bool
    {
        return $this->contentSQL === self::BACKUP_SQL_FILE && file_exists(self::BACKUP_SQL_FILE);
    }

    protected function dbDriver(): string
    {
        return $this->config['db_driver'] ?? 'mysql';
    }

    /**
     * Connect to the database server, creating the database if needed (SQLite files are simply created in place; for MySQL/PostgreSQL a CREATE DATABASE is attempted when the database is missing).
     */
    protected function connectDatabase(): void
    {
        $driver = $this->dbDriver();
        $pdoOptions = [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION];

        if ($driver === 'sqlite') {
            $this->config['db_database'] = self::SQLITE_DATABASE_PATH;
            if (!is_dir(dirname(self::SQLITE_DATABASE_PATH))) {
                @mkdir(dirname(self::SQLITE_DATABASE_PATH), 0755, true);
            }
            try {
                $this->dbLink = new \PDO('sqlite:' . self::SQLITE_DATABASE_PATH, null, null, $pdoOptions);
            } catch (\PDOException $th) {
                throw new \Exception(_t('TEST_DATABASE_CONNECTION') . ' :<br />' . _t('ERROR') . ' "' . $th->getMessage() . '"');
            }
            $this->pass(_t('TEST_DATABASE_CONNECTION'));
            $this->db()->exec('PRAGMA foreign_keys = ON');

            return;
        }

        try {
            $this->dbLink = new \PDO(
                $this->serverDsn($driver),
                $this->config['db_user'],
                $this->config['db_password'],
                $pdoOptions
            );
        } catch (\PDOException $th) {
            throw new \Exception(_t('TEST_DATABASE_CONNECTION') . ' :<br />' . _t('INCORRECT_DB_HOST_PASSWORD_OR_USER') . '<br />' . _t('ERROR') . ' "' . $th->getMessage() . '"');
        }
        $this->pass(_t('TEST_DATABASE_CONNECTION'));

        if ($this->databaseExists($driver)) {
            $this->pass(_t('SEARCH_FOR_DATABASE') . ' "' . $this->config['db_database'] . '"');
        } else {
            $this->pass(
                _t('SEARCH_FOR_DATABASE') . ' "' . $this->config['db_database'] . '" :<br />'
                    . _t('NO_DATABASE_FOUND_TRY_TO_CREATE'),
                'warning'
            );
            try {
                $quotedName = $driver === 'pgsql'
                    ? $this->config['db_database']
                    : '`' . $this->config['db_database'] . '`';
                $this->db()->exec('CREATE DATABASE ' . $quotedName);
            } catch (\PDOException $th) {
                throw new \Exception(_t('TRYING_TO_CREATE_DATABASE') . ' "' . $this->config['db_database'] . '" :<br />' . _t('DATABASE_COULD_NOT_BE_CREATED_YOU_MUST_CREATE_IT_MANUALLY'));
            }
            $this->pass(_t('TRYING_TO_CREATE_DATABASE') . ' "' . $this->config['db_database'] . '"');
        }

        try {
            $this->dbLink = new \PDO(
                $this->serverDsn($driver) . ';dbname=' . $this->config['db_database'],
                $this->config['db_user'],
                $this->config['db_password'],
                $pdoOptions
            );
        } catch (\PDOException $th) {
            throw new \Exception(_t('SEARCH_FOR_DATABASE') . ' "' . $this->config['db_database'] . '" :<br />' . _t('DATABASE_DOESNT_EXIST_YOU_MUST_CREATE_IT'));
        }

        if ($driver === 'mysql') {
            $this->db()->exec('SET NAMES utf8mb4 COLLATE utf8mb4_general_ci');
        } else {
            $this->db()->exec("SET client_encoding TO 'UTF8'");
        }
    }

    /** A connection to the *server*, before the wiki's own database is known to exist. */
    private function serverDsn(string $driver): string
    {
        $dsn = $driver . ':host=' . $this->config['db_host'];
        if (!empty($this->config['db_port'])) {
            $dsn .= ';port=' . $this->config['db_port'];
        }
        if ($driver === 'pgsql') {
            $dsn .= ';dbname=postgres';
        }

        return $dsn;
    }

    private function databaseExists(string $driver): bool
    {
        try {
            if ($driver === 'pgsql') {
                $stmt = $this->db()->query(
                    'SELECT 1 FROM pg_database WHERE datname = ' . $this->db()->quote($this->config['db_database'])
                );

                return $stmt !== false && $stmt->fetchColumn() !== false;
            }
            $this->db()->exec('USE `' . $this->config['db_database'] . '`');

            return true;
        } catch (\PDOException $th) {
            return false;
        }
    }

    protected function checkTablePrefix(): void
    {
        $driver = $this->dbDriver();
        $prefix = $this->config['table_prefix'];
        if ($driver === 'sqlite') {
            $query = "SELECT name FROM sqlite_master WHERE type='table' AND name LIKE '{$prefix}%'";
        } elseif ($driver === 'pgsql') {
            $query = "SELECT tablename FROM pg_tables WHERE schemaname = 'public' AND tablename LIKE '{$prefix}%'";
        } else {
            $query = "SHOW TABLES LIKE '{$prefix}%'";
        }

        try {
            $stmt = $this->db()->query($query);
            $existingTables = ($stmt === false) ? [] : $stmt->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\PDOException $th) {
            $existingTables = [];
        }

        if (!empty($existingTables)) {
            throw new \Exception(_t('CHECK_EXISTING_TABLE_PREFIX') . ' ("' . $prefix . '") :<br />' . _t('TABLE_PREFIX_ALREADY_USED'));
        }
        $this->pass(_t('CHECK_EXISTING_TABLE_PREFIX'));
    }

    protected function validateAdminAccount(): void
    {
        if (strlen($this->adminPassword) < 5) {
            throw new \Exception(_t('CHECKING_THE_ADMIN_PASSWORD') . ' :<br />' . _t('PASSWORD_TOO_SHORT'));
        }
        $this->pass(_t('CHECKING_THE_ADMIN_PASSWORD'));

        if ($this->adminPassword !== $this->adminPasswordConf) {
            throw new \Exception(_t('CHECKING_THE_ADMIN_PASSWORD_CONFIRMATION') . ' :<br />' . _t('ADMIN_PASSWORD_ARE_DIFFERENT'));
        }
        $this->pass(_t('CHECKING_THE_ADMIN_PASSWORD_CONFIRMATION'));

        if (
            empty($this->adminName)
            || strlen($this->adminName) > 80
            || !preg_match('/^[^!#@<>\\\\\/][^<>\\\\\/]{2,}$/', $this->adminName)
        ) {
            throw new \Exception(_t('CHECKING_THE_ADMIN_NAME') . ' :<br />' . _t('USER_THIS_IS_NOT_A_VALID_NAME'));
        }
        $this->pass(_t('CHECKING_THE_ADMIN_NAME'));
    }

    protected function validateRootPage(): void
    {
        $this->config['root_page'] = trim($this->config['root_page']);
        if (!preg_match('/^' . WN_CAMEL_CASE_EVOLVED . '$/', $this->config['root_page'])) {
            throw new \Exception(_t('CHECKING_ROOT_PAGE_NAME') . ' :<br />' . _t('INCORRECT_ROOT_PAGE_NAME'));
        }
        $this->pass(_t('CHECKING_ROOT_PAGE_NAME'));
    }

    /**
     * Hashes the admin password with the same 'auto' algorithm the runtime PasswordHasherFactory (src/Identity/Service/PasswordHasherFactory.php) configures for User::class, so the seeded hash is exactly what UserManager would produce.
     */
    protected function hashAdminPassword(): string
    {
        $factory = new PasswordHasherFactory(['admin' => ['algorithm' => 'auto']]);

        return $factory->getPasswordHasher('admin')->hash($this->adminPassword);
    }

    /** Create the tables and insert the default pages from the .sql.twig templates. */
    protected function installDatabaseContent(): void
    {
        $replacements = [
            'prefix' => $this->config['table_prefix'],
            'siteTitle' => $this->config['yeswiki_name'],
            'WikiName' => $this->adminName,
            'email' => $this->adminEmail,
            'rootPage' => $this->config['root_page'],
            'url' => $this->config['base_url'],

            'adminUserBody' => json_encode([
                'email' => $this->adminEmail,
                'motto' => '',
                'revisioncount' => '20',
                'changescount' => '50',
                'doubleclickedit' => 'Y',
                'signuptime' => date('Y-m-d H:i:s'),
                'show_comments' => 'N',
                'password' => $this->hashAdminPassword(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),

            'adminUserMetadata' => json_encode(['acls' => ['write' => "%\n@admins"]]),
        ];

        if (!$this->execSqlTemplate('installation-create-tables.sql.twig', $replacements)) {
            $this->dropEmptyTables();
            throw new \Exception(_t('CREATION_OF_TABLES') . ' :<br />' . _t('NOT_POSSIBLE_TO_CREATE_SQL_TABLES') . $this->sqlErrorDetail());
        }
        $this->pass(_t('CREATION_OF_TABLES'));

        $this->db()->beginTransaction();
        if (!$this->execSqlTemplate('installation-default-content.sql.twig', $replacements)) {
            $this->db()->rollBack();
            $this->dropEmptyTables();
            throw new \Exception(_t('INSERTION_OF_PAGES') . ' :<br />' . _t('ALREADY_CREATED') . ' ?' . $this->sqlErrorDetail());
        }
        $this->db()->commit();
        $this->pass(_t('INSERTION_OF_PAGES'));

        $this->installSearchIndex();
    }

    /** Create the search index and queue the seeded content for it (ticket 18 / ADR-0015). */
    private function installSearchIndex(): void
    {
        $db = $this->dbLink;
        if ($db === null) {
            throw new \Exception('no database connection');
        }

        $dialect = SqlDialectFactory::forDriver((string)$db->getAttribute(\PDO::ATTR_DRIVER_NAME));
        $prefix = $this->config['table_prefix'];
        $index = $prefix . SearchIndexSchema::TABLE;
        $queue = $prefix . SearchIndexSchema::QUEUE_TABLE;
        $keywords = $prefix . SearchIndexSchema::KEYWORDS_TABLE;

        foreach ($dialect->searchIndexDdl($index, $queue, $keywords) as $statement) {
            $db->exec($statement);
        }

        $db->exec(
            "INSERT INTO {$queue} (tag, queued_at) SELECT DISTINCT tag, {$dialect->now()}"
            . " FROM {$prefix}pages WHERE latest = 'Y'"
        );

        $this->pass(_t('CREATION_OF_SEARCH_INDEX'));
    }

    /** Restore the database from private/backups/content.sql, adapting the table prefix. */
    protected function importBackup(): void
    {
        $sql = file_get_contents(self::BACKUP_SQL_FILE);
        if ($sql === false) {
            throw new \Exception(_t('IMPORT_DB_BACKUP') . ' :<br />' . _t('NOT_POSSIBLE_TO_IMPORT_BACKUP_SQL'));
        }

        preg_match('/`(.*)pages`/m', $sql, $matches);
        $backupPrefix = $matches[1] ?? null;
        if ($backupPrefix === null) {
            throw new \Exception(_t('IMPORT_DB_BACKUP') . ' :<br />' . _t('NO_PREFIX_FOUND_IN_BACKUP_SQL'));
        }
        if ($backupPrefix !== $this->config['table_prefix']) {
            $sql = str_replace($backupPrefix, $this->config['table_prefix'], $sql);
        }

        try {
            $statement = '';
            foreach (explode("\n", $sql) as $line) {
                if (substr($line, 0, 2) === '--' || trim($line) === '') {
                    continue;
                }
                $statement .= $line;
                if (substr(trim($line), -1) === ';') {
                    $this->db()->exec($statement);
                    $statement = '';
                }
            }
        } catch (\PDOException $th) {
            throw new \Exception(_t('IMPORT_DB_BACKUP') . ' :<br />' . _t('NOT_POSSIBLE_TO_IMPORT_BACKUP_SQL') . '<br />' . _t('ERROR') . ' "' . $th->getMessage() . '"');
        }
        $this->pass(_t('IMPORT_DB_BACKUP'));
    }

    /**
     * After a failed content insertion, drop the tables left empty so the installation can be retried from a clean state.
     */
    private function dropEmptyTables(): void
    {
        $driver = $this->dbDriver();
        foreach (self::TABLE_NAMES as $tableName) {
            $fullTableName = $this->config['table_prefix'] . $tableName;
            $quotedName = $driver === 'mysql' ? "`$fullTableName`" : "\"$fullTableName\"";
            try {
                $countStmt = $this->db()->query("SELECT COUNT(*) FROM $quotedName");
                if ($countStmt !== false && (int)$countStmt->fetchColumn() === 0) {
                    $this->db()->exec("DROP TABLE IF EXISTS $quotedName");
                }
            } catch (\Throwable $th) {
            }
        }
    }

    protected function writeRobotsTxtFile(): void
    {
        $allowRobots = isset($this->config['allow_robots']) && $this->config['allow_robots'] == '1';
        $rule = $allowRobots ? 'Allow' : 'Disallow';

        $existing = file_exists('robots.txt') ? file_get_contents('robots.txt') : false;
        if ($existing !== false) {
            $robotFile = $existing;
            $agentPattern = "/User-agent: \*(\r?\n?)(?:\s*(?:Disa|A)llow:\s*\/\s*)?/";
            if (preg_match($agentPattern, $robotFile)) {
                $robotFile = preg_replace($agentPattern, 'User-agent: *$1' . $rule . ': /$1', $robotFile) ?? $robotFile;
            } else {
                $robotFile .= "\nUser-agent: *\n$rule: /\n";
            }
        } else {
            $robotFile = "User-agent: *\n$rule: /\n";
        }

        if (!$allowRobots) {
            $this->config['meta'] = array_merge(
                $this->config['meta'] ?? [],
                ['robots' => 'noindex,nofollow,max-image-preview:none,noarchive,noimageindex']
            );
        }

        unset($this->config['allow_robots']);

        if (file_put_contents('robots.txt', $robotFile) === false) {
            throw new \Exception(_t('WRITE_ROBOT_TXT') . ' :<br />' . _t('ROBOT_TXT_NOT_WRITABLE'));
        }
        $this->pass(_t('WRITE_ROBOT_TXT'));
    }

    protected function writeConfigFile(): void
    {
        $this->config['yeswiki_version'] = YESWIKI_VERSION;
        $this->config['yeswiki_release'] = YESWIKI_RELEASE;
        if ($this->dbDriver() === 'mysql') {
            $this->config['db_charset'] = 'utf8mb4';
        }
        foreach (['allow_raw_html', 'rewrite_mode'] as $name) {
            if (isset($this->config[$name])) {
                $this->config[$name] = in_array($this->config[$name], ['1', true, 'true'], true);
            }
        }

        $this->config += $this->defaultLayout();

        try {
            $configurationService = new ConfigurationService();
            $configuration = $configurationService->getConfiguration($this->configFile);
            foreach ($this->config as $key => $value) {
                $configuration[$key] = $value;
            }
            if (!$configurationService->write($configuration)) {
                throw new \Exception(_t('CONFIGURATION_FILE_NOT_WRITABLE'));
            }
            chmod($this->configFile, 0600);
        } catch (\Throwable $th) {
            throw new \Exception(_t('WRITE_CONFIG') . ' <tt>' . $this->configFile . '</tt> :<br />' . _t('CONFIGURATION_FILE_NOT_CREATED') . '. ' . _t('VERIFY_YOU_HAVE_RIGHTS_TO_WRITE_FILE') . '.<br />' . _t('ERROR') . ' "' . $th->getMessage() . '"');
        }
        $this->pass(_t('WRITE_CONFIG') . ' <tt>' . $this->configFile . '</tt>');
    }

    /**
     * The chrome a fresh wiki wears, as configuration (ticket 30).
     *
     * @return array<string, mixed>
     */
    private function defaultLayout(): array
    {
        return [
            LayoutService::TITLE => '',
            LayoutService::LOGO => '',
            LayoutService::BRAND => 'text',
            LayoutService::ACCOUNT_BUTTON => true,
            LayoutService::NAVBAR => [
                ['label' => 'Bac à sable', 'link' => 'BacASable', 'children' => []],
                ['label' => 'Menu exemple', 'link' => '', 'children' => [
                    ['label' => 'Exemple annuaire', 'link' => 'TrombiAnnuaire'],
                    ['label' => 'Exemple agenda', 'link' => 'VueActivite'],
                    ['label' => 'Exemple ressourcerie', 'link' => 'FacetteRessource'],
                    ['label' => 'Exemple blog', 'link' => 'VoirBlog'],
                ]],
            ],
            LayoutService::QUICK_MENU => [
                ['icon' => 'search', 'label' => 'Rechercher', 'link' => 'search'],
                ['icon' => 'cog', 'label' => 'Tableau de bord', 'link' => 'dashboard'],
            ],
        ];
    }

    /**
     * Render a Twig SQL template file.
     *
     * @param string               $templateFile absolute path to the .sql.twig template file
     * @param array<string, mixed> $variables    variables to pass to the template
     */
    public static function renderSqlTemplate(string $templateFile, array $variables = []): string
    {
        $loader = new \Twig\Loader\FilesystemLoader(dirname($templateFile));
        $twig = new \Twig\Environment($loader, [
            'autoescape' => false,
        ]);
        $twig->setLexer(new \Twig\Lexer($twig, [
            'tag_comment' => ['<#', '#>'],
            'tag_block' => ['<%', '%>'],
            'tag_variable' => ['[[', ']]'],
            'interpolation' => ['#{', '}'],
        ]));

        return $twig->render(basename($templateFile), $variables);
    }

    /**
     * Split a SQL script into individual statements, respecting single-quoted string literals (including '' escaped quotes) so that semicolons inside string values are not mistaken for statement separators.
     *
     * @return string[]
     */
    public static function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $current = '';
        $inString = false;
        $length = strlen($sql);
        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $current .= $char;
            if ($char === "'") {
                if ($inString && ($sql[$i + 1] ?? '') === "'") {
                    $current .= "'";
                    $i++;
                    continue;
                }
                $inString = !$inString;
                continue;
            }
            if ($char === ';' && !$inString) {
                $statements[] = $current;
                $current = '';
            }
        }
        if (trim($current) !== '') {
            $statements[] = $current;
        }

        return array_values(array_filter(array_map('trim', $statements), function ($statement) {
            return self::stripSqlComments($statement) !== '';
        }));
    }

    /** A statement with its `--` line comments removed, for deciding whether it does anything. */
    private static function stripSqlComments(string $statement): string
    {
        $withoutComments = preg_replace('/^\s*--[^\n]*$/m', '', $statement) ?? $statement;

        return trim(str_replace(';', '', $withoutComments));
    }

    /**
     * Render a .sql.twig template for the current driver, substitute the {{keyword}} placeholders and execute the resulting statements.
     */
    /** @param array<string, mixed> $replacements */
    private function execSqlTemplate(string $templateName, array $replacements): bool
    {
        $sql = self::renderSqlTemplate(
            YESWIKI_PROGRAM_DIR . '/templates/' . $templateName,
            [
                'driver' => $this->dbDriver(),

                'bodyType' => SqlDialectFactory::forDriver($this->dbDriver())->jsonColumnType(),
            ]
        );

        $db = $this->dbLink;
        if ($db === null) {
            return false;
        }

        $previousSqlMode = null;
        if ($this->dbDriver() === 'mysql') {
            $modeStatement = $db->query('SELECT @@SESSION.sql_mode');
            $previousSqlMode = $modeStatement === false ? '' : (string)$modeStatement->fetchColumn();
            $db->exec("SET SESSION sql_mode = CONCAT(@@SESSION.sql_mode, ',NO_BACKSLASH_ESCAPES')");
        }

        try {
            foreach ($replacements as $keyword => $value) {
                $quoted = $db->quote((string)$value);
                $sql = str_replace('{{' . $keyword . '}}', substr($quoted, 1, -1), $sql);
            }

            foreach (self::splitSqlStatements($sql) as $statement) {
                $db->exec($statement);
            }
        } catch (\PDOException $th) {
            $this->lastSqlError = $th->getMessage();

            return false;
        } finally {
            if ($previousSqlMode !== null) {
                $db->exec('SET SESSION sql_mode = ' . $db->quote($previousSqlMode));
            }
        }

        return true;
    }

    /** The driver's message for the last failed statement, ready to append to an error. */
    private function sqlErrorDetail(): string
    {
        return $this->lastSqlError === null
            ? ''
            : '<br /><code>' . htmlspecialchars($this->lastSqlError, ENT_QUOTES) . '</code>';
    }

    /**
     * Configuration values forced by the environment, as configKey => value: every private/.env entry plus the known variables from the real environment.
     */
    /** @return array<string, mixed> */
    private function environmentForcedValues(): array
    {
        $values = [];
        foreach (YesWikiLoader::envFileValues() as $name => $value) {
            if (!in_array($name, EnvironmentConfiguration::NOT_CONFIG, true)) {
                $values[strtolower($name)] = $value;
            }
        }
        foreach (EnvironmentConfiguration::knownEnvNames() as $name => $key) {
            $value = getenv($name);
            if ($value !== false) {
                $values[$key] = $value;
            }
        }

        return $values;
    }

    /**
     * The value this request posted under $postKey, or -- when it posted none, or something
     * that is not a string -- what the environment says, or ''.
     */
    private function postedOrEnv(string $postKey, string $envName): string
    {
        $posted = $_POST[$postKey] ?? null;
        if (is_string($posted)) {
            return $posted;
        }

        return $this->envValue($envName) ?? '';
    }

    /** An environment value by variable name (private/.env values are putenv()ed by YesWikiLoader). */
    private function envValue(string $name): ?string
    {
        $value = getenv($name);

        return $value === false ? null : $value;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function mapLegacyKeys(array $config): array
    {
        foreach (self::LEGACY_KEY_MAPPING as $oldKey => $newKey) {
            if (isset($config[$oldKey]) && !isset($config[$newKey])) {
                $config[$newKey] = $config[$oldKey];
            }
            unset($config[$oldKey]);
        }

        return $config;
    }

    private function pass(string $output, string $result = 'success'): void
    {
        $this->messages[] = ['result' => $result, 'output' => $output];
    }

    private function setupTwig(): \Twig\Environment
    {
        $twigLoader = new \Twig\Loader\FilesystemLoader(YESWIKI_PROGRAM_DIR . '/templates');
        $twig = new \Twig\Environment($twigLoader, [
            'debug' => (bool)($this->config['debug'] ?? false),
            'cache' => 'cache/templates/',
            'auto_reload' => true,
        ]);
        if (!empty($this->config['debug'])) {
            $twig->addExtension(new \Twig\Extension\DebugExtension());
        }
        $twig->addFunction(new \Twig\TwigFunction('_t', function ($key, $params = []) {
            return html_entity_decode(_t($key, $params));
        }));

        return $twig;
    }
}
