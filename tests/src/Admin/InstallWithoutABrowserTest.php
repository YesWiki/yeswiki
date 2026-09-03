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
        $command = 'cd ' . escapeshellarg($dir)
            . ' && YESWIKI_INSTANCE_DIR=' . escapeshellarg($dir)
            . ' YESWIKI_CONFIG_FILE=' . escapeshellarg($dir . '/yeswiki.config.php')
            . ' ' . escapeshellarg(PHP_BINARY)
            . ' ' . escapeshellarg(\YESWIKI_PROGRAM_DIR . '/src/commands/console')
            . ' core:install --no-interaction ' . implode(' ', array_map('escapeshellarg', $options))
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
            $this->assertGreaterThan(10, $this->countRows($db, 'SELECT COUNT(*) FROM yeswiki_pages'), 'the default content is seeded');
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
