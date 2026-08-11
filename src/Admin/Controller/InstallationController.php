<?php

namespace YesWiki\Admin\Controller;

use PDO;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
use YesWiki\Core\YesWikiLoader;
use YesWiki\Identity\Entity\User;
use YesWiki\Kernel\Database\SqlDialectFactory;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Kernel\Service\EnvironmentConfiguration;
use YesWiki\Kernel\Service\LanguageService;
use YesWiki\Render\Service\LayoutService;
use YesWiki\Search\Service\SearchIndexSchema;

/**
 * Web installer, run by Init::doInstall() when the configuration file does not exist
 * yet. At that point there is no database and no service container, so this controller
 * only relies on plain PHP, PDO and a Twig environment of its own.
 *
 * Values forced by the environment (private/.env or real environment variables, see
 * EnvironmentConfiguration) are shown disabled in the form and win over posted values,
 * so an instance can be installed non-interactively: provision DB_* / ADMIN_* variables
 * and POST to ?PagePrincipale&installAction=install.
 */
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

    /** A database backup at this location (instance-relative) can be restored instead of the default content. */
    public const BACKUP_SQL_FILE = 'private/backups/content.sql';

    /** SQLite databases always live at this instance-relative path. */
    private const SQLITE_DATABASE_PATH = 'private/yeswiki.db';

    protected $config;
    protected $configFile;
    protected $configPosted;
    protected $env;
    protected $step;
    protected $baseUrl;
    protected $twig;
    protected $adminName;
    protected $adminEmail;
    protected $adminPassword;
    protected $adminPasswordConf;
    protected $contentSQL;

    /** @var \PDO|null */
    protected $dbLink;
    /** The driver's own message for the last failed execSqlTemplate(), for the error page. */
    private ?string $lastSqlError = null;
    /** @var array[] every check already passed, ['result' => 'success'|'warning', 'output' => text] */
    protected $messages = [];

    /**
     * @param array  $config     configuration from Init::getConfig() (defaults + environment overrides)
     * @param string $configFile path of the yeswiki.config.php file to create
     */
    public function __construct(array $config, string $configFile)
    {
        // no Wiki object exists yet: hand the configuration (which may carry an
        // environment-forced default_language) to the language detection directly
        LanguageService::getInstance()->loadPreferredLanguage((object)['config' => $config]);

        $this->config = $config;
        $this->configFile = $configFile;
        $this->env = $this->environmentForcedValues();

        // the retry form re-posts the whole config as a JSON string
        if (isset($_POST['config']) && is_string($_POST['config'])) {
            $_POST['config'] = json_decode(html_entity_decode($_POST['config']), true) ?? [];
        }
        $this->configPosted = $this->mapLegacyKeys($_POST['config'] ?? []);
        // merge posted values, then re-apply the environment overrides: they always win
        $this->config = EnvironmentConfiguration::apply(array_merge($this->config, $this->configPosted));

        $this->adminName = $_POST['admin_name'] ?? $this->envValue('ADMIN_NAME') ?? '';
        $this->adminEmail = $_POST['admin_email'] ?? $this->envValue('ADMIN_EMAIL') ?? '';
        $this->adminPassword = $_POST['admin_password'] ?? $this->envValue('ADMIN_PASSWORD') ?? '';
        $this->adminPasswordConf = $_POST['admin_password_conf'] ?? $this->envValue('ADMIN_PASSWORD') ?? '';
        $this->contentSQL = $_POST['contentSQL']
            ?? (file_exists(self::BACKUP_SQL_FILE) ? self::BACKUP_SQL_FILE : 'default');

        $this->step = trim($_REQUEST['installAction'] ?? '') ?: 'default';
        $this->baseUrl = computeBaseURL(true);
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
     * Run all installation steps; on failure display the checks done so far, the error,
     * and a retry form carrying the posted values.
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
            // installation successful!
            header('Location: ' . $this->config['base_url'] . $this->config['root_page']);
            exit;
        } catch (\Exception $ex) {
            echo $this->render('installation-database.twig', [
                'messages' => $this->messages,
                'error' => $ex->getMessage(),
                'configPosted' => htmlspecialchars(json_encode($this->configPosted), ENT_COMPAT, 'UTF-8'),
            ]);
        }
    }

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
            'locale' => $GLOBALS['prefered_language'],
            'availableLanguages' => $GLOBALS['available_languages'],
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
     * Connect to the database server, creating the database if needed (SQLite files are
     * simply created in place; for MySQL/PostgreSQL a CREATE DATABASE is attempted when
     * the database is missing).
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
            $this->dbLink->exec('PRAGMA foreign_keys = ON');

            return;
        }

        // MySQL or PostgreSQL: connect to the server first
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
                $this->dbLink->exec('CREATE DATABASE ' . $quotedName);
            } catch (\PDOException $th) {
                throw new \Exception(_t('TRYING_TO_CREATE_DATABASE') . ' "' . $this->config['db_database'] . '" :<br />' . _t('DATABASE_COULD_NOT_BE_CREATED_YOU_MUST_CREATE_IT_MANUALLY'));
            }
            $this->pass(_t('TRYING_TO_CREATE_DATABASE') . ' "' . $this->config['db_database'] . '"');
        }

        // reconnect with the database selected
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
            $this->dbLink->exec('SET NAMES utf8mb4 COLLATE utf8mb4_general_ci');
        } else {
            $this->dbLink->exec("SET client_encoding TO 'UTF8'");
        }
    }

    /**
     * A connection to the *server*, before the wiki's own database is known to exist.
     *
     * PostgreSQL needs a database named here and MySQL does not, which is the whole subtlety:
     * libpq given no `dbname` falls back to **the connecting user's name**, so this worked only
     * where the database happened to be named after its owner and failed with
     * `FATAL: database "<user>" does not exist` everywhere else. The install then reported the
     * failure as an ordinary page, so the next thing anyone saw was `yeswicli` answering
     * "Command migrate is not defined" -- the console registers almost nothing without a
     * `base_url` in the config that never got written.
     *
     * `postgres` is the maintenance database every PostgreSQL install has; `tests/e2e/reset.sh`
     * already attaches to `template1` for the same reason. MySQL keeps connecting with no
     * database, which is valid there and is what `databaseExists()` then interrogates.
     */
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
                $stmt = $this->dbLink->query(
                    'SELECT 1 FROM pg_database WHERE datname = ' . $this->dbLink->quote($this->config['db_database'])
                );

                return $stmt->fetchColumn() !== false;
            }
            $this->dbLink->exec('USE `' . $this->config['db_database'] . '`');

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
            $existingTables = $this->dbLink->query($query)->fetchAll(\PDO::FETCH_COLUMN);
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

        // same rule as UserOperationsService::sanitizeName()
        if (
            empty($this->adminName)
            || !is_string($this->adminName)
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
     * Hashes the admin password with the same 'auto' algorithm the runtime
     * PasswordHasherFactory (src/Identity/Service/PasswordHasherFactory.php) configures for
     * User::class, so the seeded hash is exactly what UserManager would produce.
     * Historically this was md5(), which used to keep logging in via the runtime's
     * migrate_from chain. That chain is gone -- md5 no longer authenticates at all -- so an
     * installer that seeded one would create an admin who could not sign in.
     */
    protected function hashAdminPassword(): string
    {
        $factory = new PasswordHasherFactory(['admin' => ['algorithm' => 'auto']]);

        return $factory->getPasswordHasher('admin')->hash($this->adminPassword);
    }

    /**
     * Create the tables and insert the default pages from the .sql.twig templates.
     */
    protected function installDatabaseContent(): void
    {
        $replacements = [
            'prefix' => $this->config['table_prefix'],
            'siteTitle' => $this->config['yeswiki_name'],
            'WikiName' => $this->adminName,
            'email' => $this->adminEmail,
            'rootPage' => $this->config['root_page'],
            'url' => $this->config['base_url'],
            // the admin account is seeded as a `pages` row (see UserManager) -- built as a
            // single pre-encoded JSON value rather than interpolating {{WikiName}}/
            // {{password}}/{{email}} into hand-assembled JSON in the twig template, so this
            // one json_encode() call is the only place responsible for JSON-escaping (the
            // template's own {{...}} substitution only does SQL-string escaping, not JSON)
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
            // default_write_acl is '*' (everyone) -- without this override, anyone could
            // edit the admin account's page; matches UserManager::persistNewUserPage()
            'adminUserMetadata' => json_encode(['acls' => ['write' => "%\n@admins"]]),
        ];

        // Table creation runs OUTSIDE any transaction: MySQL and MariaDB implicitly commit
        // on every DDL statement, so a transaction opened around CREATE TABLE is already
        // gone by the time we reach commit() -- which then throws "There is no active
        // transaction" and fails the whole install. SQLite has transactional DDL and hides
        // this, which is why the phpunit suite never caught it. Failed table creation is
        // undone by dropEmptyTables() instead, the same way a failed content insert is.
        if (!$this->execSqlTemplate('installation-create-tables.sql.twig', $replacements)) {
            $this->dropEmptyTables();
            throw new \Exception(_t('CREATION_OF_TABLES') . ' :<br />' . _t('NOT_POSSIBLE_TO_CREATE_SQL_TABLES') . $this->sqlErrorDetail());
        }
        $this->pass(_t('CREATION_OF_TABLES'));

        // The seed content is all INSERTs -- nothing here implicitly commits on any
        // dialect, so the transaction is still open when we reach commit().
        $this->dbLink->beginTransaction();
        if (!$this->execSqlTemplate('installation-default-content.sql.twig', $replacements)) {
            $this->dbLink->rollBack();
            $this->dropEmptyTables();
            throw new \Exception(_t('INSERTION_OF_PAGES') . ' :<br />' . _t('ALREADY_CREATED') . ' ?' . $this->sqlErrorDetail());
        }
        $this->dbLink->commit();
        $this->pass(_t('INSERTION_OF_PAGES'));

        $this->installSearchIndex();
    }

    /**
     * Create the search index and queue the seeded content for it (ticket 18 / ADR-0015).
     *
     * A fresh install cannot get this from the migration that creates it on an *existing*
     * wiki: new installs seed their migrations as already-run, so anything only a migration
     * does simply never happens here. That is not a hypothetical -- it is defect 4 of ticket
     * 25, where fresh installs shipped a Pages form with no `syntax` for exactly this
     * reason, and every page rendered its own markup verbatim.
     *
     * The DDL comes from the dialect rather than from `installation-create-tables.sql.twig`,
     * so the index has one definition and not two that drift. The installer runs before the
     * container exists, but a dialect has no dependencies to inject.
     *
     * Outside a transaction, like the other DDL above: MySQL implicitly commits on every DDL
     * statement, so wrapping it achieves nothing except a "no active transaction" throw at
     * commit time.
     */
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

        foreach ($dialect->searchIndexDdl($index, $queue) as $statement) {
            $db->exec($statement);
        }

        // The seed is a few dozen pages, so this could have been indexed inline. It is
        // queued instead so that a fresh install and an upgraded one converge through the
        // same drain -- one path to keep working, not two.
        $db->exec(
            "INSERT INTO {$queue} (tag, queued_at) SELECT DISTINCT tag, {$dialect->now()}"
            . " FROM {$prefix}pages WHERE latest = 'Y'"
        );

        $this->pass(_t('CREATION_OF_SEARCH_INDEX'));
    }

    /**
     * Restore the database from private/backups/content.sql, adapting the table prefix.
     * Dump files hold one statement per line, so statements are split on line ends.
     */
    protected function importBackup(): void
    {
        $sql = file_get_contents(self::BACKUP_SQL_FILE);
        // `pages`, unlike `acls` (dropped in ticket 03), exists in every schema version this
        // backup file could have come from -- old or new
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
                    $this->dbLink->exec($statement);
                    $statement = '';
                }
            }
        } catch (\PDOException $th) {
            throw new \Exception(_t('IMPORT_DB_BACKUP') . ' :<br />' . _t('NOT_POSSIBLE_TO_IMPORT_BACKUP_SQL') . '<br />' . _t('ERROR') . ' "' . $th->getMessage() . '"');
        }
        $this->pass(_t('IMPORT_DB_BACKUP'));
    }

    /**
     * After a failed content insertion, drop the tables left empty so the
     * installation can be retried from a clean state.
     */
    private function dropEmptyTables(): void
    {
        $driver = $this->dbDriver();
        foreach (self::TABLE_NAMES as $tableName) {
            $fullTableName = $this->config['table_prefix'] . $tableName;
            $quotedName = $driver === 'mysql' ? "`$fullTableName`" : "\"$fullTableName\"";
            try {
                $countStmt = $this->dbLink->query("SELECT COUNT(*) FROM $quotedName");
                if ((int)$countStmt->fetchColumn() === 0) {
                    $this->dbLink->exec("DROP TABLE IF EXISTS $quotedName");
                }
            } catch (\Throwable $th) {
                // table absent: nothing to clean
            }
        }
    }

    protected function writeRobotsTxtFile(): void
    {
        $allowRobots = isset($this->config['allow_robots']) && $this->config['allow_robots'] == '1';
        $rule = $allowRobots ? 'Allow' : 'Disallow';

        if (file_exists('robots.txt')) {
            $robotFile = file_get_contents('robots.txt');
            $agentPattern = "/User-agent: \*(\r?\n?)(?:\s*(?:Disa|A)llow:\s*\/\s*)?/";
            if (preg_match($agentPattern, $robotFile)) {
                $robotFile = preg_replace($agentPattern, 'User-agent: *$1' . $rule . ': /$1', $robotFile);
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
        // only used to write robots.txt, not a YesWiki config value
        unset($this->config['allow_robots']);

        if (file_put_contents('robots.txt', $robotFile) === false) {
            throw new \Exception(_t('WRITE_ROBOT_TXT') . ' :<br />' . _t('ROBOT_TXT_NOT_WRITABLE'));
        }
        $this->pass(_t('WRITE_ROBOT_TXT'));
    }

    protected function writeConfigFile(): void
    {
        // set version to current version, yay!
        $this->config['yeswiki_version'] = YESWIKI_VERSION;
        $this->config['yeswiki_release'] = YESWIKI_RELEASE;
        if ($this->dbDriver() === 'mysql') {
            // new installs are utf8mb4; older wikis get a conversion on update
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
            chmod($this->configFile, 0600); // only the system user can read/write the config
        } catch (\Throwable $th) {
            throw new \Exception(_t('WRITE_CONFIG') . ' <tt>' . $this->configFile . '</tt> :<br />' . _t('CONFIGURATION_FILE_NOT_CREATED') . '. ' . _t('VERIFY_YOU_HAVE_RIGHTS_TO_WRITE_FILE') . '.<br />' . _t('ERROR') . ' "' . $th->getMessage() . '"');
        }
        $this->pass(_t('WRITE_CONFIG') . ' <tt>' . $this->configFile . '</tt>');
    }

    /**
     * The chrome a fresh wiki wears, as configuration (ticket 30).
     *
     * This is what the seeded `PageTitre`, `PageMenuHaut` and `PageRapideHaut` used to hold,
     * and the reason those three pages are no longer seeded. No title: the field is empty and
     * LayoutService falls back to `yeswiki_name`, which is what the seeded `PageTitre`
     * (`{{configuration param="yeswiki_name"}}`) said in the longest possible way.
     *
     * Written here rather than declared as a default in YesWikiInit, deliberately: a default
     * would make every existing wiki look like it already had a layout, and the migration that
     * reads the three pages skips a wiki that does.
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
                ['icon' => 'gauge', 'label' => 'Tableau de bord', 'link' => 'dashboard'],
            ],
        ];
    }

    /**
     * Render a Twig SQL template file.
     *
     * YesWiki's own template syntax ({{ }} and {# #}) is used verbatim inside the SQL
     * content (default page content), so Twig is configured with different delimiters
     * ([[ ]] / <% %> / <# #>) to avoid parsing that content as Twig.
     *
     * Also used by UpdateAdminPagesService to extract the default admin pages.
     *
     * @param string $templateFile absolute path to the .sql.twig template file
     * @param array  $variables    variables to pass to the template
     */
    public static function renderSqlTemplate(string $templateFile, array $variables = []): string
    {
        $loader = new \Twig\Loader\FilesystemLoader(dirname($templateFile));
        $twig = new \Twig\Environment($loader, [
            'autoescape' => false, // SQL templates must not be HTML-escaped
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
     * Split a SQL script into individual statements, respecting single-quoted string
     * literals (including '' escaped quotes) so that semicolons inside string values
     * are not mistaken for statement separators.
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

        // A fragment carrying nothing but comments is dropped, not just an empty one. The
        // seed templates end their sections with `-- end triples` and the like, so the tail
        // after the last `;` is a comment: MySQL and SQLite execute that happily as a no-op,
        // and **PostgreSQL rejects it** with a bare "SQLSTATE[HY000]: General error" -- an
        // empty query. Every fresh PostgreSQL install failed at "Insertion des pages par
        // défaut", and the installer's guess for that was "Déjà créée ?".
        //
        // Third divergence of this exact shape in this one method's neighbourhood, after
        // ticket 25's transactional-DDL and backslash-escape defects. The pattern is always
        // the same: the templates are written for one dialect's tolerance.
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
     * Render a .sql.twig template for the current driver, substitute the {{keyword}}
     * placeholders and execute the resulting statements.
     */
    private function execSqlTemplate(string $templateName, array $replacements): bool
    {
        $sql = self::renderSqlTemplate(
            YESWIKI_SOURCE_DIR . '/templates/' . $templateName,
            ['driver' => $this->dbDriver()]
        );

        // MySQL and MariaDB treat a backslash as an escape character inside string
        // literals; standard SQL and SQLite do not. Since ticket 09 the seeded page bodies
        // are JSON, so they are full of \" and \n -- which MySQL silently ate, storing
        // invalid JSON. PageBody::decode() then falls back to treating the whole thing as
        // raw markup, so the page rendered its own JSON envelope. 39 of the 61 seeded
        // pages were affected on a fresh MySQL install; SQLite installs were fine, which
        // is why the phpunit suite never saw it.
        //
        // These templates already use the standard '' for a literal apostrophe, so asking
        // the server for standard behaviour is all that is needed. Scoped to this method
        // on purpose: mysqldump output (importBackup) DOES rely on backslash escapes.
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
            // after the sql_mode change, so quote() escapes the way the server now parses
            foreach ($replacements as $keyword => $value) {
                $quoted = $db->quote((string)$value);
                $sql = str_replace('{{' . $keyword . '}}', substr($quoted, 1, -1), $sql);
            }

            foreach (self::splitSqlStatements($sql) as $statement) {
                $db->exec($statement);
            }
        } catch (\PDOException $th) {
            // Kept, because the caller's message is a *guess* ("Déjà créée ?") and the
            // driver's is a fact. On PostgreSQL that difference was the whole debugging
            // session: "already created?" sent me looking for a stale database when the
            // real answer was in the statement the server refused.
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
     * Configuration values forced by the environment, as configKey => value: every
     * private/.env entry plus the known variables from the real environment. The
     * matching form fields are shown disabled.
     */
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

    /** An environment value by variable name (private/.env values are putenv()ed by YesWikiLoader). */
    private function envValue(string $name): ?string
    {
        $value = getenv($name);

        return $value === false ? null : $value;
    }

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
        $twigLoader = new \Twig\Loader\FilesystemLoader(YESWIKI_SOURCE_DIR . '/templates');
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
