<?php

namespace YesWiki\Test\Kernel;

use YesWiki\Core\YesWikiInit;
use YesWiki\Kernel\Service\Replication;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** ADR-0031: a replica refuses to boot on what it would otherwise guess, its address from the first Host header, and on a database only one machine can open. */
class ReplicatedBootTest extends YesWikiTestCase
{
    private string $configFile = '';

    protected function setUp(): void
    {
        parent::setUp();
        self::getWiki();
        $this->configFile = sys_get_temp_dir() . '/yw-replicated-' . bin2hex(random_bytes(4)) . '.php';
    }

    protected function tearDown(): void
    {
        putenv(Replication::ENV);
        @unlink($this->configFile);
        parent::tearDown();
    }

    public function testAReplicaWithoutABaseUrlRefusesToBoot(): void
    {
        $this->expectExceptionMessage('base_url');
        $this->configFor(['db_driver' => 'mysql'], replicated: true);
    }

    public function testAReplicaOnSqliteRefusesToBoot(): void
    {
        $this->expectExceptionMessage('SQLite');
        $this->configFor(['db_driver' => 'sqlite', 'base_url' => 'https://wiki.example.org/?'], replicated: true);
    }

    public function testASingleMachineStillLearnsItsAddressFromTheRequest(): void
    {
        $config = $this->configFor(['db_driver' => 'sqlite'], replicated: false);

        $this->assertNotEmpty($config['base_url']);
    }

    /**
     * @param array<string, mixed> $wakkaConfig
     *
     * @return array<string, mixed>
     */
    private function configFor(array $wakkaConfig, bool $replicated): array
    {
        file_put_contents($this->configFile, '<?php $wakkaConfig = ' . var_export($wakkaConfig, true) . ';');
        putenv($replicated ? Replication::ENV . '=1' : Replication::ENV);
        $init = (new \ReflectionClass(YesWikiInit::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(YesWikiInit::class, 'configFile'))->setValue($init, $this->configFile);

        return $init->getConfig();
    }
}
