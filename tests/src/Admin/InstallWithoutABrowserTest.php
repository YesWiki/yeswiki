<?php

namespace YesWiki\Test\Admin;

use PHPUnit\Framework\Attributes\CoversMethod;
use YesWiki\Admin\Controller\InstallationController;
use YesWiki\Admin\Service\InstallationService;
use YesWiki\Kernel\Service\DataFolderGuard;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Ticket 44: a wiki installs from the terminal, through the service the web wizard also runs. */
#[CoversMethod(InstallationService::class, 'install')]
class InstallWithoutABrowserTest extends YesWikiTestCase
{
    private function countRows(\PDO $db, string $query): int
    {
        $statement = $db->query($query);
        $this->assertNotFalse($statement, $query);

        return (int)$statement->fetchColumn();
    }

    private function instanceDir(): string
    {
        return (string)realpath((string)sys_get_temp_dir()) . '/yw-cli-install-' . getmypid();
    }

    private function removeInstance(string $dir): void
    {
        foreach (['private', 'cache', 'custom', 'files', 'src/assets', 'src'] as $folder) {
            foreach (glob($dir . '/' . $folder . '/*') ?: [] as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
            @rmdir($dir . '/' . $folder);
        }
        foreach (glob($dir . '/*') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        @rmdir($dir);
    }

    /**
     * @param list<string> $options
     *
     * @return array{status: int, out: string}
     */
    private function install(string $dir, array $options): array
    {
        return $this->console($dir, 'core:install', ['--no-interaction', ...$options]);
    }

    /**
     * @param list<string> $arguments
     *
     * @return array{status: int, out: string}
     */
    private function console(string $dir, string $name, array $arguments): array
    {
        self::getWiki();
        $command = 'cd ' . escapeshellarg($dir)
            . ' && ' . self::withoutTheWikisEnvironment() . 'YESWIKI_INSTANCE_DIR=' . escapeshellarg($dir)
            . ' YESWIKI_CONFIG_FILE=' . escapeshellarg($dir . '/yeswiki.config.php')
            . ' ' . escapeshellarg(PHP_BINARY)
            . ' ' . escapeshellarg(\YESWIKI_PROGRAM_DIR . '/src/commands/console')
            . ' ' . $name . ' ' . implode(' ', array_map('escapeshellarg', $arguments))
            . ' 2>&1';

        $out = [];
        $status = 0;
        exec($command, $out, $status);

        return ['status' => $status, 'out' => implode("\n", $out)];
    }

    public function testACompleteWikiInstallsFromTheTerminal(): void
    {
        $dir = $this->instanceDir();
        mkdir($dir, 0o755, true);

        try {
            $result = $this->install($dir, [
                '--driver=sqlite',
                '--table-prefix=yeswiki_',
                '--base-url=http://cli-installed.test/?',
                '--root-page=PagePrincipale',
                '--wiki-name=Installed without a browser',
                '--language=en',
                '--admin-name=WikiAdmin',
                '--admin-email=admin@example.tld',
                '--admin-password=InstalledFromTheTerminal',
            ]);

            $this->assertSame(0, $result['status'], $result['out']);
            $this->assertFileDoesNotExist($dir . '/yeswiki.config.php', 'a wiki needs no configuration file (replicas 20)');
            $this->assertFileExists($dir . '/private/yeswiki.db');
            $this->assertFileExists($dir . '/robots.txt');

            $env = (new \Symfony\Component\Dotenv\Dotenv())->parse((string)file_get_contents($dir . '/private/.env'));
            $this->assertSame('http://cli-installed.test/?', $env['BASE_URL'] ?? null, 'the host settings go to private/.env');
            $this->assertSame('sqlite', $env['DB_DRIVER'] ?? null);
            $this->assertSame('yeswiki_', $env['TABLE_PREFIX'] ?? null);
            $this->assertArrayNotHasKey('YESWIKI_NAME', $env, 'and nothing the admin decides');

            $settings = $this->storedSettings(new \PDO('sqlite:' . $dir . '/private/yeswiki.db'), 'yeswiki_');
            $this->assertSame('Installed without a browser', $settings['yeswiki_name'] ?? null, 'what the admin decides goes to the database');
            $this->assertSame('PagePrincipale', $settings['root_page'] ?? null);
            $this->assertSame('MenuNavigation', $settings['layout_navbar'] ?? null, 'a fresh wiki has a navbar to draw');
            $this->assertSame('MenuAccesRapide', $settings['layout_quick_menu'] ?? null);
            $this->assertSame([], array_values(array_intersect(array_keys($settings), ['base_url', 'db_driver', 'yeswiki_version', 'yeswiki_release'])), 'and nothing the host decides or the program knows');

            $db = new \PDO('sqlite:' . $dir . '/private/yeswiki.db');
            $this->assertSame(
                ['BacASable', 'MenuAccesRapide', 'MenuNavigation', 'PageFooter', 'PageHeader', 'WikiAdmin', 'comptes', 'fichiers', 'pages'],
                $this->tags($db),
                'the seed holds what a wiki needs to run, and the home page is left for the onboarding to create'
            );
            $this->assertSame(
                9,
                $this->countRows($db, "SELECT COUNT(*) FROM yeswiki_queue WHERE message_type = 'YesWiki\\Search\\Message\\ReindexContent' AND delivered_at IS NULL"),
                'every seeded Content waits in the queue to be indexed'
            );
            $this->assertSame([], array_values(array_diff(scandir($dir . '/files') ?: [], ['.', '..', '.htaccess'])), 'a fresh wiki has no files');
            $this->assertStringContainsString(DataFolderGuard::BEGIN, (string)file_get_contents($dir . '/files/.htaccess'), 'and Apache runs no PHP out of files/');
            $this->assertSame(
                2,
                $this->countRows(
                    $db,
                    "SELECT COUNT(*) FROM yeswiki_pages WHERE type = 'menu' AND latest = 'Y'"
                    . " AND tag IN ('MenuNavigation', 'MenuAccesRapide')"
                ),
                'and the rows it names are seeded beside it'
            );
            $this->assertSame(
                0,
                $this->countRows($db, "SELECT COUNT(*) FROM yeswiki_pages WHERE latest = 'Y' AND body LIKE '%{{nav links=%'"),
                'no seeded page still carries its navigation inside the call'
            );
            $this->assertSame(
                1,
                $this->countRows($db, "SELECT COUNT(*) FROM yeswiki_pages WHERE tag = 'WikiAdmin' AND type = 'user' AND latest = 'Y'"),
                'the first account exists'
            );
            $this->assertSame(
                0,
                $this->countRows(
                    $db,
                    'SELECT COUNT(*) FROM (SELECT tag FROM yeswiki_pages WHERE latest = \'Y\''
                    . ' GROUP BY tag HAVING COUNT(*) > 1) AS collisions'
                ),
                'a tag names one Content (ADR-0001), so no tag has two current rows'
            );
            $adminRow = $db->query("SELECT type FROM yeswiki_pages WHERE tag = 'WikiAdmin' AND latest = 'Y'");
            $this->assertNotFalse($adminRow);
            $this->assertSame(
                'user',
                (string)$adminRow->fetchColumn(),
                'and ?WikiAdmin is the account, not a page shadowing it'
            );
        } finally {
            $this->removeInstance($dir);
        }
    }

    /** A replica's install Job writes its configuration file where no replica reads it, so the wiki's own settings go to the database (replicas 04). */
    public function testAReplicaInstallKeepsTheWikisSettingsInItsDatabase(): void
    {
        $config = self::getWiki()->config;
        if (($config['db_driver'] ?? 'mysql') === 'sqlite') {
            $this->markTestSkipped('a replica needs MySQL, MariaDB or PostgreSQL');
        }
        $dir = $this->instanceDir() . '-replica';
        mkdir($dir, 0o755, true);
        $prefix = 'ywreplica' . getmypid() . '_';
        $db = new \PDO(
            ($config['db_driver'] === 'pgsql' ? 'pgsql' : 'mysql') . ':host=' . $config['db_host'] . (empty($config['db_port']) ? '' : ';port=' . $config['db_port']) . ';dbname=' . $config['db_database'],
            $config['db_user'],
            $config['db_password'],
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
        $was = getenv('YESWIKI_REPLICATED');
        putenv('YESWIKI_REPLICATED=1');

        try {
            $result = $this->install($dir, [
                '--driver=' . $config['db_driver'],
                '--db-host=' . $config['db_host'],
                ...(empty($config['db_port']) ? [] : ['--db-port=' . $config['db_port']]),
                '--db-database=' . $config['db_database'],
                '--db-user=' . $config['db_user'],
                '--db-password=' . $config['db_password'],
                '--table-prefix=' . $prefix,
                '--base-url=http://replica.test/?',
                '--root-page=PagePrincipale',
                '--wiki-name=Installed for replicas',
                '--language=fr',
                '--other-languages=en,es',
                '--allow-raw-html',
                '--admin-name=WikiAdmin',
                '--admin-email=admin@example.tld',
                '--admin-password=InstalledFromTheTerminal',
            ]);
            $this->assertSame(0, $result['status'], $result['out']);

            $statement = $db->query("SELECT property, value FROM {$prefix}triples WHERE resource = 'yeswiki_settings' AND property LIKE 'setting:%'");
            $this->assertNotFalse($statement);
            $stored = [];
            foreach ($statement->fetchAll(\PDO::FETCH_KEY_PAIR) as $property => $value) {
                $stored[substr((string)$property, strlen('setting:'))] = json_decode((string)$value, true);
            }

            $this->assertSame('Installed for replicas', $stored['yeswiki_name'] ?? null);
            $this->assertSame('fr', $stored['default_language'] ?? null);
            $this->assertSame(['en', 'es'], $stored['other_languages'] ?? null);
            $this->assertTrue($stored['allow_raw_html'] ?? \YesWiki\Core\YesWikiInit::defaults()['allow_raw_html'], 'raw HTML is allowed, stored or by default');
            $this->assertSame('MenuNavigation', $stored['layout_navbar'] ?? null);
            $this->assertSame([], array_values(array_intersect(array_keys($stored), ['base_url', 'db_driver', 'db_password', 'table_prefix', 'yeswiki_version'])), 'what the operator decides stays in the environment');
        } finally {
            putenv($was === false ? 'YESWIKI_REPLICATED' : 'YESWIKI_REPLICATED=' . $was);
            $this->removeInstance($dir);
            $tables = $config['db_driver'] === 'pgsql'
                ? $db->query("SELECT tablename FROM pg_tables WHERE schemaname = current_schema() AND tablename LIKE '{$prefix}%'")
                : $db->query("SHOW TABLES LIKE '{$prefix}%'");
            foreach ($tables ? $tables->fetchAll(\PDO::FETCH_COLUMN) : [] as $table) {
                $db->exec('DROP TABLE ' . $table . ($config['db_driver'] === 'pgsql' ? ' CASCADE' : ''));
            }
        }
    }

    /** A wiki that has database settings and finds no schema creates it, with an admin only when ADMIN_* name one (replicas 20). */
    public function testAConfiguredWikiCreatesItsOwnSchema(): void
    {
        foreach (['without' => '', 'with' => "ADMIN_NAME=FirstAdmin\nADMIN_EMAIL=first@example.tld\nADMIN_PASSWORD=CreatedOnFirstStart\n"] as $case => $admin) {
            $dir = $this->instanceDir() . '-bootstrap-' . $case;
            mkdir($dir . '/private', 0o755, true);
            file_put_contents($dir . '/private/.env', "DB_DRIVER=sqlite\nBASE_URL=http://bootstrap.test/?\nTABLE_PREFIX=boot_\n" . $admin);

            try {
                $listed = $this->console($dir, 'user:list', []);
                $this->assertSame(0, $listed['status'], $listed['out']);
                $this->assertFileDoesNotExist($dir . '/yeswiki.config.php');

                $db = new \PDO('sqlite:' . $dir . '/private/yeswiki.db');
                $this->assertContains('BacASable', $this->tagsUnder($db, 'boot_'), "the default content is there ($case an admin)");
                $this->assertSame(
                    $admin === '' ? 0 : 1,
                    $this->countRows($db, "SELECT COUNT(*) FROM boot_pages WHERE type = 'user' AND latest = 'Y'"),
                    $admin === '' ? 'no ADMIN_*, no account: nobody becomes admin by signing up first' : 'ADMIN_* make the first account'
                );
                $this->assertStringContainsString($admin === '' ? 'no admin account' : 'admin account FirstAdmin', $listed['out'], 'the log says where the schema went and who runs it');

                $again = $this->console($dir, 'user:list', []);
                $this->assertStringNotContainsString('schema created', $again['out'], 'a second start finds it there');
            } finally {
                exec('rm -rf ' . escapeshellarg($dir));
            }
        }
    }

    /** A wiki upgraded with a configuration file keeps every setting: the wiki's in its database, the host's in private/.env, and the file is renamed (replicas 20). */
    public function testAnUpgradeMovesTheConfigurationFileOut(): void
    {
        $dir = $this->instanceDir() . '-upgrade';
        mkdir($dir, 0o755, true);

        try {
            $installed = $this->install($dir, ['--driver=sqlite', '--table-prefix=up_', '--base-url=http://upgraded.test/?', '--root-page=PagePrincipale', '--admin-name=WikiAdmin', '--admin-email=admin@example.tld', '--admin-password=InstalledFromTheTerminal']);
            $this->assertSame(0, $installed['status'], $installed['out']);
            unlink($dir . '/private/.env');
            file_put_contents($dir . '/yeswiki.config.php', "<?php\n\n\$wakkaConfig = [\n  'db_driver' => 'sqlite',\n  'table_prefix' => 'up_',\n  'base_url' => 'http://upgraded.test/?',\n  'yeswiki_name' => 'Named in the file',\n  'yeswiki_version' => 'doryphore',\n  'trusted_proxies' => ['10.0.0.0/8'],\n];\n");
            $db = new \PDO('sqlite:' . $dir . '/private/yeswiki.db');
            $db->exec("DELETE FROM up_triples WHERE resource LIKE '%TheConfigurationFileMovesOut%'");
            $db->exec("DELETE FROM up_triples WHERE resource = 'yeswiki_settings' AND property = 'setting:yeswiki_name'");

            $migrated = $this->console($dir, 'migrate', []);
            $this->assertSame(0, $migrated['status'], $migrated['out']);

            $this->assertFileDoesNotExist($dir . '/yeswiki.config.php', 'the file is gone');
            $this->assertFileExists($dir . '/yeswiki.config.php.migrated', 'renamed, not deleted');
            $env = (new \Symfony\Component\Dotenv\Dotenv())->parse((string)file_get_contents($dir . '/private/.env'));
            $this->assertSame('http://upgraded.test/?', $env['BASE_URL'] ?? null, 'the host settings move to private/.env');
            $this->assertSame('["10.0.0.0/8"]', $env['TRUSTED_PROXIES'] ?? null, 'a list too, as JSON');
            $this->assertArrayNotHasKey('YESWIKI_VERSION', $env, 'what the program knows itself is carried nowhere');
            $this->assertSame('Named in the file', $this->storedSettings($db, 'up_')['yeswiki_name'] ?? null, 'the wiki\'s settings move to its database');

            $listed = $this->console($dir, 'user:list', []);
            $this->assertSame(0, $listed['status'], 'and the wiki still boots without the file: ' . $listed['out']);
        } finally {
            exec('rm -rf ' . escapeshellarg($dir));
        }
    }

    /** Another wiki's settings, opened from its folder, are read and written where that wiki keeps them: the host's in its private/.env, the rest in its database (replicas 20). */
    public function testAnotherWikisSettingsAreReadAndWrittenWhereItKeepsThem(): void
    {
        $dir = $this->instanceDir() . '-child';
        mkdir($dir, 0o755, true);

        try {
            $installed = $this->install($dir, ['--driver=sqlite', '--table-prefix=child_', '--base-url=http://child.test/?', '--root-page=PagePrincipale', '--wiki-name=The Child', '--admin-name=WikiAdmin', '--admin-email=admin@example.tld', '--admin-password=InstalledFromTheTerminal']);
            $this->assertSame(0, $installed['status'], $installed['out']);

            $child = new \YesWiki\Kernel\Service\InstanceConfiguration($dir);
            $this->assertTrue($child->exists());
            $loaded = $child->load();
            $this->assertSame('The Child', $loaded['yeswiki_name'] ?? null, 'its own settings come from its database');
            $this->assertSame('http://child.test/?', $loaded['base_url'] ?? null, 'the host\'s from its private/.env');
            $this->assertNotSame(self::getWiki()->config['base_url'], $loaded['base_url'], 'and nothing from the wiki that opens it');

            $hostWas = getenv('CONTACT_SMTP_HOST');
            $this->assertTrue($child->store(['yeswiki_name' => 'Renamed by the farm', 'contact_smtp_host' => 'smtp.farm.test'], ['root_page']));
            $this->assertSame('Renamed by the farm', $this->storedSettings(new \PDO('sqlite:' . $dir . '/private/yeswiki.db'), 'child_')['yeswiki_name'] ?? null);
            $env = (new \Symfony\Component\Dotenv\Dotenv())->parse((string)file_get_contents($dir . '/private/.env'));
            $this->assertSame('smtp.farm.test', $env['CONTACT_SMTP_HOST'] ?? null, 'a host setting goes to its private/.env');
            $this->assertArrayNotHasKey('root_page', (new \YesWiki\Kernel\Service\InstanceConfiguration($dir))->load(), 'and a key taken away is gone');
            $this->assertSame($hostWas, getenv('CONTACT_SMTP_HOST'), 'without touching the environment of the wiki that wrote it');
        } finally {
            exec('rm -rf ' . escapeshellarg($dir));
        }
    }

    /** @return list<string> */
    private function tagsUnder(\PDO $db, string $prefix): array
    {
        $statement = $db->query("SELECT tag FROM {$prefix}pages WHERE latest = 'Y'");
        $this->assertNotFalse($statement);

        return array_values(array_map('strval', $statement->fetchAll(\PDO::FETCH_COLUMN)));
    }

    /** @return array<string, mixed> the settings a wiki keeps in its database */
    private function storedSettings(\PDO $db, string $prefix): array
    {
        $statement = $db->query("SELECT property, value FROM {$prefix}triples WHERE resource = 'yeswiki_settings' AND property LIKE 'setting:%'");
        $this->assertNotFalse($statement);
        $stored = [];
        foreach ($statement->fetchAll(\PDO::FETCH_KEY_PAIR) as $property => $value) {
            $stored[substr((string)$property, strlen('setting:'))] = json_decode((string)$value, true);
        }

        return $stored;
    }

    /** @return list<string> */
    private function tags(\PDO $db): array
    {
        $statement = $db->query("SELECT tag FROM yeswiki_pages WHERE latest = 'Y'");
        $this->assertNotFalse($statement);
        $tags = array_map('strval', $statement->fetchAll(\PDO::FETCH_COLUMN));
        sort($tags);

        return $tags;
    }

    /** @return array<string, mixed> */
    private function body(\PDO $db, string $tag): array
    {
        $statement = $db->prepare("SELECT body FROM yeswiki_pages WHERE latest = 'Y' AND tag = ?");
        $statement->execute([$tag]);

        return json_decode((string)$statement->fetchColumn(), true) ?? [];
    }

    public function testTheOnboardingCreatesTheChosenStartersAndTheHomePage(): void
    {
        $dir = $this->instanceDir() . '-onboarding';
        mkdir($dir, 0o755, true);

        try {
            $installed = $this->install($dir, [
                '--driver=sqlite',
                '--base-url=http://onboarded.test/?',
                '--root-page=PagePrincipale',
                '--wiki-name=Onboarded',
                '--admin-name=WikiAdmin',
                '--admin-email=admin@example.tld',
                '--admin-password=InstalledFromTheTerminal',
            ]);
            $this->assertSame(0, $installed['status'], $installed['out']);

            $applied = $this->console($dir, 'onboarding:apply', ['annuaire']);
            $this->assertSame(0, $applied['status'], $applied['out']);

            $db = new \PDO('sqlite:' . $dir . '/private/yeswiki.db');
            $tags = $this->tags($db);
            foreach (['PagePrincipale', 'annuaire', 'TrombiAnnuaire', 'SaisirAnnuaire', 'MenuAnnuaire'] as $tag) {
                $this->assertContains($tag, $tags);
            }
            $this->assertNotContains('VueAgenda', $tags, 'a Starter nobody chose is not created');

            $formId = (string)($this->body($db, 'annuaire')['id'] ?? '');
            $this->assertNotSame('', $formId);
            $this->assertStringContainsString('id="' . $formId . '"', (string)$this->body($db, 'TrombiAnnuaire')['content'], 'the pages name the form that was just created');
            $this->assertContains('TrombiAnnuaire', array_column($this->body($db, 'MenuNavigation')['nodes'], 'link'), 'the Starter is reachable from the navbar');

            $again = $this->console($dir, 'onboarding:apply', ['agenda']);
            $this->assertSame(0, $again['status'], $again['out']);
            $this->assertNotContains('VueAgenda', $this->tags($db), 'once the home page exists the onboarding is over');
        } finally {
            $this->removeInstance($dir);
        }
    }

    public function testTheOnboardingCanPublishTheBlogOnTheFediverse(): void
    {
        $dir = $this->instanceDir() . '-fediverse';
        mkdir($dir, 0o755, true);

        try {
            $installed = $this->install($dir, [
                '--driver=sqlite',
                '--base-url=https://federated.test/?',
                '--root-page=PagePrincipale',
                '--wiki-name=Federated',
                '--admin-name=WikiAdmin',
                '--admin-email=admin@example.tld',
                '--admin-password=InstalledFromTheTerminal',
            ]);
            $this->assertSame(0, $installed['status'], $installed['out']);

            $applied = $this->console($dir, 'onboarding:apply', ['--activitypub', 'blog', 'annuaire']);
            $this->assertSame(0, $applied['status'], $applied['out']);

            $db = new \PDO('sqlite:' . $dir . '/private/yeswiki.db');
            $statement = $db->query("SELECT body, metadata FROM yeswiki_pages WHERE latest = 'Y' AND type = 'form'");
            $this->assertNotFalse($statement);
            $federation = [];
            foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $label = (string)(json_decode((string)$row['body'], true)['label'] ?? '');
                $federation[$label] = json_decode((string)$row['metadata'], true)['activitypub'] ?? [];
            }

            $this->assertSame('1', $federation['Blog-actu']['enabled'] ?? null);
            $this->assertSame('blog', $federation['Blog-actu']['username'] ?? null);
            $this->assertNotSame('1', $federation['Annuaire']['enabled'] ?? null, 'a Starter without an account stays off the fediverse');
        } finally {
            $this->removeInstance($dir);
        }
    }

    public function testItRefusesToInstallOverAWikiThatIsAlreadyThere(): void
    {
        $dir = $this->instanceDir() . '-twice';
        mkdir($dir, 0o755, true);
        $options = [
            '--driver=sqlite',
            '--base-url=http://already.test/?',
            '--root-page=PagePrincipale',
            '--admin-name=WikiAdmin',
            '--admin-email=admin@example.tld',
            '--admin-password=InstalledFromTheTerminal',
        ];

        try {
            $first = $this->install($dir, $options);
            $this->assertSame(0, $first['status'], $first['out']);

            $second = $this->install($dir, $options);

            $this->assertNotSame(0, $second['status'], 'installing over a wiki would drop what is in it');
            $this->assertStringContainsString('("yeswiki_")', $second['out'], 'the tables already under its prefix are what refuses it');
        } finally {
            $this->removeInstance($dir);
        }
    }

    public function testAWikiWithoutABaseUrlIsRefusedRatherThanGuessed(): void
    {
        $dir = $this->instanceDir() . '-nourl';
        mkdir($dir, 0o755, true);

        try {
            $result = $this->install($dir, ['--driver=sqlite']);

            $this->assertNotSame(0, $result['status'], 'a CLI has no request to infer a base URL from');
            $this->assertStringContainsString('--base-url', $result['out']);
        } finally {
            $this->removeInstance($dir);
        }
    }

    /** A wiki moved with its backup: the archive is restored at install, under the new prefix and address, with its files and settings. */
    public function testAWikiInstallsFromABackupArchive(): void
    {
        $source = $this->instanceDir() . '-source';
        $copy = $this->instanceDir() . '-copy';
        mkdir($source, 0o755, true);
        mkdir($copy . '/private/backups', 0o755, true);

        try {
            $installed = $this->install($source, [
                '--driver=sqlite',
                '--table-prefix=yeswiki_',
                '--base-url=http://source.test/?',
                '--root-page=PagePrincipale',
                '--wiki-name=The Source Wiki',
                '--language=en',
                '--admin-name=SourceAdmin',
                '--admin-email=admin@example.tld',
                '--admin-password=InstalledFromTheTerminal',
            ]);
            $this->assertSame(0, $installed['status'], $installed['out']);
            @mkdir($source . '/custom', 0o755, true);
            file_put_contents($source . '/custom/source.css', 'body {}');
            file_put_contents($source . '/index.php', '<?php');
            $archived = $this->console($source, 'core:archive', []);
            $archives = glob($source . '/private/backups/*_source-test_archive.zip') ?: [];
            $this->assertCount(1, $archives, $archived['out']);
            copy($archives[0], $copy . '/private/backups/' . basename($archives[0]));

            $restored = $this->install($copy, [
                '--driver=sqlite',
                '--table-prefix=copy_',
                '--base-url=http://copy.test/?',
                '--root-page=PagePrincipale',
                '--wiki-name=Not the source',
                '--language=en',
                '--from-archive=' . basename($archives[0]),
            ]);

            $this->assertSame(0, $restored['status'], $restored['out']);
            $this->assertFileExists($copy . '/custom/source.css', 'the files of the backup come along');
            $env = (new \Symfony\Component\Dotenv\Dotenv())->parse((string)file_get_contents($copy . '/private/.env'));
            $this->assertSame('http://copy.test/?', $env['BASE_URL'] ?? null);
            $this->assertSame('copy_', $env['TABLE_PREFIX'] ?? null);
            $db = new \PDO('sqlite:' . $copy . '/private/yeswiki.db');
            $this->assertSame('The Source Wiki', $this->storedSettings($db, 'copy_')['yeswiki_name'] ?? null, 'the settings of the backup come along in its database');

            $this->assertSame(1, $this->countRows($db, "SELECT COUNT(*) FROM copy_pages WHERE tag = 'SourceAdmin' AND type = 'user' AND latest = 'Y'"), 'the accounts are the backup\'s');
            $this->assertSame(0, $this->countRows($db, "SELECT COUNT(*) FROM sqlite_master WHERE name LIKE 'yeswiki\\_%' ESCAPE '\\'"), 'nothing is left under the source prefix');
            $this->assertSame(0, $this->countRows($db, "SELECT COUNT(*) FROM copy_pages WHERE body LIKE '%source.test%'"), 'the links point at the new address');
        } finally {
            exec('rm -rf ' . escapeshellarg($source) . ' ' . escapeshellarg($copy));
        }
    }

    /** The ticket's second Done-when, stated as an assertion: the controller handles the request and nothing else. */
    /** private/.env states the queue's mode as YESWIKI_QUEUE_CONSUMER, the configuration knows it as queue_consumer: the installer must force the key the wiki reads. */
    public function testTheEnvironmentFileForcesTheKeysTheWikiReads(): void
    {
        $dir = $this->instanceDir() . '-env';
        mkdir($dir . '/private', 0o755, true);
        file_put_contents($dir . '/private/.env', "YESWIKI_QUEUE_CONSUMER=worker\nYESWIKI_PAGES_PURGE_TIME=30\nADMIN_PASSWORD=never-in-the-configuration\n");
        file_put_contents($dir . '/forced.php', "<?php\nrequire " . var_export(\YESWIKI_PROGRAM_DIR . '/src/bootstrap_paths.php', true) . ";\nrequire " . var_export(\YESWIKI_PROGRAM_DIR . '/vendor/autoload.php', true) . ";\necho json_encode(\\YesWiki\\Admin\\Service\\InstallationService::environmentForcedValues());\n");

        try {
            $out = [];
            exec(
                'cd ' . escapeshellarg($dir)
                . ' && env -u YESWIKI_QUEUE_CONSUMER -u YESWIKI_PAGES_PURGE_TIME'
                . ' YESWIKI_INSTANCE_DIR=' . escapeshellarg($dir)
                . ' YESWIKI_PROGRAM_DIR=' . escapeshellarg(\YESWIKI_PROGRAM_DIR)
                . ' ' . escapeshellarg(PHP_BINARY) . ' forced.php 2>&1',
                $out
            );
            $forced = json_decode(implode("\n", $out), true);

            $this->assertIsArray($forced, implode("\n", $out));
            $this->assertSame('worker', $forced['queue_consumer'] ?? null, 'the variable is forced under the key the wiki reads');
            $this->assertArrayNotHasKey('yeswiki_queue_consumer', $forced);
            $this->assertArrayNotHasKey('admin_password', $forced, 'credentials of the automated installation never become configuration');
        } finally {
            @unlink($dir . '/private/.env');
            @unlink($dir . '/private/.htaccess');
            $this->removeInstance($dir);
        }
    }

    public function testTheControllerHoldsNoInstallLogic(): void
    {
        $controller = new \ReflectionClass(InstallationController::class);
        $methods = array_map(
            fn (\ReflectionMethod $method): string => $method->getName(),
            $controller->getMethods()
        );

        foreach ([
            'connectDatabase',
            'checkTablePrefix',
            'validateAdminAccount',
            'validateRootPage',
            'installDatabaseContent',
            'importBackup',
            'writeRobotsTxtFile',
            'writeConfigFile',
        ] as $step) {
            $this->assertNotContains($step, $methods, "$step belongs to InstallationService, not to a controller");
        }
    }
}
