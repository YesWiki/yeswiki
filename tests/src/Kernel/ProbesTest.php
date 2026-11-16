<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Kernel\Service\Probes;
use YesWiki\Kernel\Service\Replication;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Replicas 08: a kubelet's two questions, answered before the wiki boots a page. */
#[CoversClass(Probes::class)]
class ProbesTest extends YesWikiTestCase
{
    /** @var array<string, mixed> */
    private array $server;

    protected function setUp(): void
    {
        parent::setUp();
        $this->server = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        parent::tearDown();
    }

    /** @return iterable<string, array{string, string, ?string}> */
    public static function requests(): iterable
    {
        yield 'liveness at the root' => ['/healthz', '/index.php', Probes::LIVENESS];
        yield 'readiness at the root' => ['/readyz?verbose=1', '/index.php', Probes::READINESS];
        yield 'under the worker' => ['/healthz', '/worker.php', Probes::LIVENESS];
        yield 'a wiki in a folder' => ['/wiki/readyz', '/wiki/index.php', Probes::READINESS];
        yield 'a page named like a probe' => ['/?healthz', '/index.php', null];
        yield 'deeper than the wiki' => ['/files/healthz', '/index.php', null];
        yield 'a page' => ['/PagePrincipale', '/index.php', null];
    }

    #[DataProvider('requests')]
    public function testOnlyTheProbePathsAreProbes(string $uri, string $script, ?string $probe): void
    {
        $_SERVER['REQUEST_URI'] = $uri;
        $_SERVER['SCRIPT_NAME'] = $script;

        $this->assertSame($probe, Probes::asked());
    }

    public function testLivenessSaysOk(): void
    {
        ob_start();
        Probes::live();

        $this->assertSame('ok', ob_get_clean());
    }

    public function testReadinessNamesEachCheck(): void
    {
        self::getWiki();
        ob_start();
        Probes::ready();
        $answer = json_decode((string)ob_get_clean(), true);

        $this->assertSame(['ready' => true, 'checks' => ['database' => 'ok', 'storage' => 'ok']], $answer);
    }

    /** A replica's configuration file states only what the install wrote, so the defaults a booted wiki adds must not be needed. */
    public function testAReplicaWithABareConfigurationComparesItsMigrations(): void
    {
        self::getWiki();
        $bare = tempnam(sys_get_temp_dir(), 'yw-readyz-');
        file_put_contents($bare, preg_replace('/^\s*\'debug\'\s*=>.*$/m', '', (string)file_get_contents('yeswiki.config.php')));
        $was = ['YESWIKI_CONFIG_FILE' => getenv('YESWIKI_CONFIG_FILE'), 'YESWIKI_REPLICATED' => getenv('YESWIKI_REPLICATED')];
        putenv('YESWIKI_CONFIG_FILE=' . $bare);
        putenv('YESWIKI_REPLICATED=1');
        foreach (Replication::KEY_VARIABLES as $name) {
            $was[$name] = getenv($name);
            putenv($name . '=' . str_repeat('d', 64));
        }

        try {
            ob_start();
            Probes::ready();
            $answer = json_decode((string)ob_get_clean(), true);
        } finally {
            foreach ($was as $name => $value) {
                putenv($value === false ? $name : $name . '=' . $value);
            }
            @unlink($bare);
        }

        $this->assertSame('ok', $answer['checks']['migrations'] ?? null, (string)json_encode($answer));
    }

    /** A replica that would refuse to boot is not ready, so no load balancer sends it a visitor. */
    public function testAReplicaWhoseBootWouldRefuseIsNotReady(): void
    {
        self::getWiki();
        $was = ['YESWIKI_REPLICATED' => getenv('YESWIKI_REPLICATED')];
        foreach (Replication::KEY_VARIABLES as $name) {
            $was[$name] = getenv($name);
            putenv($name);
        }
        putenv('YESWIKI_REPLICATED=1');
        $log = (string)tempnam(sys_get_temp_dir(), 'yw-readyz-log-');
        $logWas = ini_set('error_log', $log);
        $readiness = static function (): array {
            ob_start();
            Probes::ready();

            return (array)json_decode((string)ob_get_clean(), true);
        };

        try {
            $refused = $readiness();
            foreach (Replication::KEY_VARIABLES as $name) {
                putenv($name . '=' . str_repeat('c', 64));
            }
            $given = $readiness();
        } finally {
            foreach ($was as $name => $value) {
                putenv($value === false ? $name : $name . '=' . $value);
            }
            ini_set('error_log', $logWas === false ? '' : $logWas);
            $logged = (string)file_get_contents($log);
            @unlink($log);
        }

        $this->assertFalse($refused['ready'] ?? null);
        $this->assertStringContainsString('YESWIKI_SIGNING_KEY', $logged, 'the reason goes to the server log, not the answer');
        $this->assertSame('failed', $refused['checks']['configuration'] ?? null, (string)json_encode($refused));
        $this->assertSame('ok', $given['checks']['configuration'] ?? null, (string)json_encode($given));
    }
}
