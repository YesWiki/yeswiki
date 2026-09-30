<?php

namespace YesWiki\Test\Actions;

use YesWiki\Core\Controller\AuthController;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

class EditConfigActionTest extends YesWikiTestCase
{
    private $wiki;
    private $previousLocked;
    private $configFile;

    protected function setUp(): void
    {
        $this->wiki = $this->getWiki();
        $this->previousLocked = $this->wiki->config['edit_config_locked_params'] ?? null;
        if (empty($this->wiki->services->get(AuthController::class)->connectFirstAdmin())) {
            $this->markTestSkipped('no admin account in the test wiki');
        }
    }

    protected function tearDown(): void
    {
        if ($this->configFile !== null) {
            putenv('WAKKA_CONFIG_FILE');
            unlink($this->configFile);
        }
        $this->wiki->services->get(AuthController::class)->logout();
        if ($this->previousLocked === null) {
            unset($this->wiki->config['edit_config_locked_params']);
        } else {
            $this->wiki->config['edit_config_locked_params'] = $this->previousLocked;
        }
    }

    public function testALockedParamIsLeftOutOfTheForm()
    {
        $this->wiki->config['edit_config_locked_params'] = ['contact_smtp_host', 'wakka_name'];

        $output = $this->wiki->Action('editconfig');

        $this->assertStringContainsString('name="contact_smtp_port"', $output);
        $this->assertStringNotContainsString('name="contact_smtp_host"', $output);
        $this->assertStringNotContainsString('name="wakka_name"', $output);
    }

    public function testEveryParamIsShownWhenNothingIsLocked()
    {
        unset($this->wiki->config['edit_config_locked_params']);

        $output = $this->wiki->Action('editconfig');

        $this->assertStringContainsString('name="contact_smtp_host"', $output);
        $this->assertStringContainsString('name="wakka_name"', $output);
    }

    public function testALockedValueIsNotInThePage()
    {
        $base = tempnam(sys_get_temp_dir(), 'wakka');
        unlink($base);
        $this->configFile = $base . '.php';
        file_put_contents($this->configFile, "<?php\n\$wakkaConfig = ['contact_smtp_pass' => 'LockedSecretValue', 'contact_smtp_host' => 'VisibleHostValue'];\n");
        putenv('WAKKA_CONFIG_FILE=' . $this->configFile);
        $this->wiki->config['edit_config_locked_params'] = ['contact_smtp_pass'];

        $output = $this->wiki->Action('editconfig');

        $this->assertStringContainsString('VisibleHostValue', $output);
        $this->assertStringNotContainsString('LockedSecretValue', $output);
    }
}
