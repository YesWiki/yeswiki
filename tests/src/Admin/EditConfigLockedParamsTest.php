<?php

namespace YesWiki\Test\Admin;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Admin\Action\EditConfigAction;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Render\Service\TemplateEngine;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A param listed in edit_config_locked_params is neither shown nor saved by the editconfig form. */
class EditConfigLockedParamsTest extends YesWikiTestCase
{
    private string $configFile;

    protected function setUp(): void
    {
        parent::setUp();
        if (empty($this->getWiki()->services->get(AuthenticationService::class)->connectFirstAdmin())) {
            $this->markTestSkipped('no admin account in the test wiki');
        }
        $this->configFile = 'cache/editconfig-test-' . bin2hex(random_bytes(4)) . '.php';
        file_put_contents($this->configFile, "<?php\n\$yeswikiConfig = ['contact_smtp_pass' => 'LockedSecretValue', 'contact_smtp_host' => 'VisibleHostValue'];\n");
        putenv('YESWIKI_CONFIG_FILE=' . $this->configFile);
    }

    protected function tearDown(): void
    {
        putenv('YESWIKI_CONFIG_FILE');
        if (isset($this->configFile) && is_file($this->configFile)) {
            unlink($this->configFile);
        }
        $this->getWiki()->services->get(AuthenticationService::class)->logout();
        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $extraParams
     * @param array<string, mixed> $post
     */
    private function renderForm(array $extraParams, array $post = []): string
    {
        $services = $this->getWiki()->services;
        $params = new ParameterBag(array_merge($services->get(ParameterBagInterface::class)->all(), $extraParams));

        $action = new EditConfigAction();
        $action->setServices($services);
        $action->setParams($params);
        $action->setTwig($services->get(TemplateEngine::class));
        $arguments = ['saving' => false, 'saved' => false, 'post' => $post];
        $action->setArguments($arguments);

        return (string)$action->run();
    }

    public function testALockedParamIsLeftOutOfTheForm(): void
    {
        $output = $this->renderForm(['edit_config_locked_params' => ['contact_smtp_host', 'yeswiki_name']]);

        $this->assertStringContainsString('name="contact_smtp_port"', $output);
        $this->assertStringNotContainsString('name="contact_smtp_host"', $output);
        $this->assertStringNotContainsString('name="yeswiki_name"', $output);
    }

    public function testEveryParamIsShownWhenNothingIsLocked(): void
    {
        $output = $this->renderForm([]);

        $this->assertStringContainsString('name="contact_smtp_host"', $output);
        $this->assertStringContainsString('name="yeswiki_name"', $output);
        $this->assertStringContainsString('name="contact_smtp_verify_peer"', $output);
    }

    public function testALockedValueIsNotInThePage(): void
    {
        $output = $this->renderForm(['edit_config_locked_params' => ['contact_smtp_pass']]);

        $this->assertStringContainsString('VisibleHostValue', $output);
        $this->assertStringNotContainsString('LockedSecretValue', $output);
    }

    public function testTheHelpTextHasNoBrokenAriaReference(): void
    {
        $output = $this->renderForm([]);

        $this->assertStringNotContainsString('aria-describedby', $output);
    }
}
