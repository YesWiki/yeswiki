<?php

namespace YesWiki\Test\Admin;

use YesWiki\Admin\Service\ExtensionActivation;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';
require_once 'src/migrations/20261005170000_ExtensionsThatRanKeepRunning.php';

/** Switching an extension on or off is this Instance's configuration, and nothing else (ADR-0029). */
class ExtensionActivationTest extends YesWikiTestCase
{
    private string $config;

    private string $folders;

    protected function setUp(): void
    {
        parent::setUp();
        $this->getWiki();
        $this->config = sys_get_temp_dir() . '/extension-activation-' . bin2hex(random_bytes(4)) . '.php';
        file_put_contents($this->config, "<?php\n\n\$yeswikiConfig = ['wakka_name' => 'test'];\n");
        putenv('YESWIKI_CONFIG_FILE=' . $this->config);
        $this->folders = sys_get_temp_dir() . '/extension-folders-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        putenv('YESWIKI_CONFIG_FILE');
        @unlink($this->config);
        exec('rm -rf ' . escapeshellarg($this->folders));
        parent::tearDown();
    }

    public function testAnExtensionIsSwitchedOnAndOffInTheConfigurationOnly(): void
    {
        $activation = $this->getWiki()->services->get(ExtensionActivation::class);

        $this->assertSame([], $activation->active());
        $this->assertSame([], $activation->activate('helloworld'));
        $this->assertSame(['helloworld'], $activation->active());
        $this->assertStringContainsString("'active_extensions' =>", (string)file_get_contents($this->config));
        $this->assertSame([], $activation->activate('helloworld'), 'switching on twice changes nothing');

        $this->assertSame(['no extension named nowhere here'], $activation->activate('nowhere'));

        $this->assertSame([], $activation->deactivate('helloworld'));
        $this->assertSame([], $activation->active());
    }

    public function testOnlyWhatTheOldDescriptorSwitchedOnKeepsRunning(): void
    {
        foreach (['lms' => '1', 'stats' => '0', 'helloworld' => '1'] as $name => $active) {
            mkdir($this->folders . '/' . $name, 0755, true);
            file_put_contents($this->folders . "/{$name}/desc.xml", "<?xml version=\"1.0\"?>\n<plugin name=\"{$name}\" version=\"0.1\" active=\"{$active}\"><label>x</label></plugin>");
        }
        mkdir($this->folders . '/contrib', 0755, true);

        $folders = [];
        foreach (['contrib', 'helloworld', 'lms', 'stats'] as $name) {
            $folders[$name] = $this->folders . '/' . $name . '/';
        }

        $this->assertSame(['lms'], \ExtensionsThatRanKeepRunning::runningBefore($folders));
    }
}
