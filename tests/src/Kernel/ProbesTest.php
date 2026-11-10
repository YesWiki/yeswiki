<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Kernel\Service\Probes;
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
}
