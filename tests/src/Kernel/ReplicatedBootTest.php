<?php

namespace YesWiki\Test\Kernel;

use YesWiki\Core\YesWikiInit;
use YesWiki\Files\Entity\S3Settings;
use YesWiki\Identity\Service\BotGuard;
use YesWiki\Kernel\Service\Replication;
use YesWiki\Kernel\Service\SignedTokens;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** ADR-0031: a replica refuses to boot on what it would otherwise guess, its address from the first Host header, on a database only one machine can open, and on keys of its own. */
class ReplicatedBootTest extends YesWikiTestCase
{
    private string $configFile = '';

    /** @var array<string, string|false> */
    private array $keysBefore = [];

    protected function setUp(): void
    {
        parent::setUp();
        self::getWiki();
        $this->withoutTheWikisEnvFile();
        $this->configFile = sys_get_temp_dir() . '/yw-replicated-' . bin2hex(random_bytes(4)) . '.php';
        foreach (Replication::KEY_VARIABLES as $name) {
            $this->keysBefore[$name] = getenv($name);
            putenv($name);
        }
    }

    protected function tearDown(): void
    {
        putenv(Replication::ENV);
        foreach ($this->keysBefore as $name => $value) {
            putenv($value === false ? $name : "{$name}={$value}");
        }
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

    public function testAReplicaThatWouldMakeItsOwnKeysRefusesToBoot(): void
    {
        putenv(Replication::KEY_VARIABLES[1] . '=' . str_repeat('b', 64));

        $this->expectExceptionMessage(Replication::KEY_VARIABLES[0] . ' is not set');
        $this->configFor(['db_driver' => 'mysql', 'base_url' => 'https://wiki.example.org/?'], replicated: true);
    }

    public function testAReplicaGivenBothKeysBoots(): void
    {
        foreach (Replication::KEY_VARIABLES as $name) {
            putenv($name . '=' . str_repeat('a', 64));
        }

        $config = $this->configFor(['db_driver' => 'mysql', 'base_url' => 'https://wiki.example.org/?'], replicated: true);

        $this->assertSame('https://wiki.example.org/?', $config['base_url']);
    }

    public function testKeysOnS3AreSharedWithoutVariables(): void
    {
        $s3 = static fn (string ...$tiers): S3Settings => new S3Settings('bucket', 'us-east-1', 'https://s3.example', 'key', 'secret', publicUrl: 'https://cdn.example', tiers: array_values($tiers));

        $this->assertSame([], Replication::unsharedKeys($s3('public', 'protected')));
        $this->assertSame(Replication::KEY_VARIABLES, Replication::unsharedKeys($s3('public')), 'public files on S3 do not carry the keys');
        $this->assertSame(Replication::KEY_VARIABLES, Replication::unsharedKeys(null));
    }

    public function testTheKeysAskedForAreTheOnesTheWikiReads(): void
    {
        $this->assertSame([SignedTokens::KEY_ENV, BotGuard::KEY_ENV], Replication::KEY_VARIABLES);
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
