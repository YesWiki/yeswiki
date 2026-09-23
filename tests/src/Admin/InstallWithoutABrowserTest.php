<?php

namespace YesWiki\Test\Admin;

use PHPUnit\Framework\Attributes\CoversMethod;
use YesWiki\Admin\Controller\InstallationController;
use YesWiki\Admin\Service\InstallationService;
use YesWiki\Kernel\Service\ConfigurationService;
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
        $command = 'cd ' . escapeshellarg($dir)
            . ' && YESWIKI_INSTANCE_DIR=' . escapeshellarg($dir)
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
            $this->assertFileExists($dir . '/yeswiki.config.php', 'the installer writes the configuration file');
            $this->assertFileExists($dir . '/private/yeswiki.db');
            $this->assertFileExists($dir . '/robots.txt');

            $written = (new ConfigurationService())->getConfiguration($dir . '/yeswiki.config.php');
            $written->load();
            $this->assertSame('http://cli-installed.test/?', $written['base_url']);
            $this->assertSame('sqlite', $written['db_driver']);
            $this->assertSame('cli-installed.test', $written['mail_domain']);

            $this->assertSame('MenuNavigation', $written['layout_navbar'], 'a fresh wiki has a navbar to draw');
            $this->assertSame('MenuAccesRapide', $written['layout_quick_menu']);

            $db = new \PDO('sqlite:' . $dir . '/private/yeswiki.db');
            $this->assertSame(
                ['BacASable', 'MenuAccesRapide', 'MenuNavigation', 'PageFooter', 'PageHeader', 'WikiAdmin', 'comptes', 'fichiers', 'pages'],
                $this->tags($db),
                'the seed holds what a wiki needs to run, and the home page is left for the onboarding to create'
            );
            $this->assertSame([], array_values(array_diff(scandir($dir . '/files') ?: [], ['.', '..'])), 'a fresh wiki has no files');
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
            $this->assertStringContainsString('already configures', $second['out']);
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

    /** The ticket's second Done-when, stated as an assertion: the controller handles the request and nothing else. */
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
