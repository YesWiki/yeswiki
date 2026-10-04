<?php

namespace YesWiki\Test\Bazar\Controller;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use YesWiki\Bazar\Controller\EntryController;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\Exception\ExitException;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Security\Controller\CaptchaController;
use YesWiki\Security\Controller\SecurityController;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * The captcha guards an entry's creation and is never saved in it.
 */
class EntryCaptchaTest extends YesWikiTestCase
{
    private $wiki;
    private $entryManager;
    private $formManager;
    private string $formId;
    private $originalParams;
    private array $createdTags = [];

    protected function setUp(): void
    {
        $this->wiki = $this->getWiki();
        $GLOBALS['wiki'] = $this->wiki;
        $this->entryManager = $this->wiki->services->get(EntryManager::class);
        $this->formManager = $this->wiki->services->get(FormManager::class);
        $this->wiki->services->get(AuthController::class)->logout();

        $securityController = $this->wiki->services->get(SecurityController::class);
        $params = new \ReflectionProperty($securityController, 'params');
        $this->originalParams = $params->getValue($securityController);
        $params->setValue($securityController, new ParameterBag(array_merge($this->originalParams->all(), ['use_captcha' => true])));

        $this->formId = $this->formManager->create([
            'bn_label_nature' => 'Entry captcha test form',
            'bn_template' => 'texte***bf_titre***Titre***60***255*** *** ***text***1*** *** *** * *** * *** *** *** ***',
            'bn_condition' => '',
        ]);
    }

    protected function tearDown(): void
    {
        $securityController = $this->wiki->services->get(SecurityController::class);
        (new \ReflectionProperty($securityController, 'params'))->setValue($securityController, $this->originalParams);
        $this->wiki->request->request->replace([]);
        foreach ($this->createdTags as $tag) {
            if ($this->entryManager->isEntry($tag)) {
                $this->entryManager->delete($tag, true);
            }
            $this->wiki->services->get(PageManager::class)->deleteOrphaned($tag);
            $this->wiki->services->get(AclService::class)->delete($tag);
        }
        $this->createdTags = [];
        $this->formManager->delete($this->formId);
    }

    private function captcha(): array
    {
        $captchaController = $this->wiki->services->get(CaptchaController::class);
        $hash = $captchaController->generateHash();
        $word = (new \ReflectionMethod($captchaController, 'getTextFromHash'))->invoke($captchaController, $hash);

        return [$word, $hash];
    }

    private function submit(string $title, string $tag, string $word, string $hash): void
    {
        $this->wiki->request->request->replace(['antispam' => 1, 'bf_titre' => $title, 'captcha' => $word, 'captcha_hash' => $hash]);
        $this->createdTags[] = $tag;
        try {
            $this->wiki->services->get(EntryController::class)->create($this->formId);
        } catch (ExitException $e) {
        }
    }

    public function testTheCaptchaIsNotSavedInTheEntry()
    {
        [$word, $hash] = $this->captcha();
        $this->submit('Entry captcha right', 'EntryCaptchaRight', $word, $hash);

        $this->assertTrue($this->entryManager->isEntry('EntryCaptchaRight'));
        $body = json_decode($this->wiki->services->get(PageManager::class)->getOne('EntryCaptchaRight', null, false, true)['body'], true);
        $this->assertArrayNotHasKey('captcha', $body);
        $this->assertArrayNotHasKey('captcha_hash', $body);
    }

    public function testAWrongCaptchaCreatesNothing()
    {
        [, $hash] = $this->captcha();
        $this->submit('Entry captcha wrong', 'EntryCaptchaWrong', 'not-the-word', $hash);

        $this->assertFalse($this->entryManager->isEntry('EntryCaptchaWrong'));
    }
}
