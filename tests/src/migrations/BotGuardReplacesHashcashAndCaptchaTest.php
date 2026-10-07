<?php

namespace YesWiki\Test\Core\Migrations;

use YesWiki\Kernel\Entity\ConfigurationFile;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Only the hashcash and captcha settings leave the configuration. */
class BotGuardReplacesHashcashAndCaptchaTest extends YesWikiTestCase
{
    private string $file = '';

    public static function setUpBeforeClass(): void
    {
        self::getWiki();
        require_once 'src/migrations/20261007180000_BotGuardReplacesHashcashAndCaptcha.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->file = (string)tempnam(sys_get_temp_dir(), 'botguard-config');
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        parent::tearDown();
    }

    /** @param array<string, mixed> $values */
    private function configuration(array $values): ConfigurationFile
    {
        file_put_contents($this->file, "<?php\n\n\$yeswikiConfig = " . var_export($values, true) . ";\n");
        $configuration = $this->getWiki()->services->get(ConfigurationService::class)->getConfiguration($this->file);
        $configuration->load();

        return $configuration;
    }

    public function testTheRetiredSettingsGoAndTheOthersStay(): void
    {
        $configuration = $this->configuration(['wakka_name' => 'test', 'use_hashcash' => true, 'use_captcha' => false, 'altcha' => false]);

        $this->assertSame(['use_hashcash', 'use_captcha'], \BotGuardReplacesHashcashAndCaptcha::retire($configuration));
        $this->assertFalse(isset($configuration['use_hashcash']));
        $this->assertFalse(isset($configuration['use_captcha']));
        $this->assertSame('test', $configuration['wakka_name']);
        $this->assertFalse($configuration['altcha']);
    }

    public function testAConfigurationWithoutThemIsLeftAlone(): void
    {
        $configuration = $this->configuration(['wakka_name' => 'test']);

        $this->assertSame([], \BotGuardReplacesHashcashAndCaptcha::retire($configuration));
    }
}
