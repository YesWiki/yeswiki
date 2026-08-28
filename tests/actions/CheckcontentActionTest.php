<?php

namespace YesWiki\Test\Actions;

use YesWiki\Content\Service\FormManager;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\GroupManager;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Render\Service\ActionRunner;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** `{{checkcontent}}` is an admin screen, and its template has to render for one. */
class CheckcontentActionTest extends YesWikiTestCase
{
    private const FORM_ID = '999907';

    private \YesWiki\Core\YesWikiRuntime $wiki;
    private ?string $createdForm = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wiki = $this->getWiki();
    }

    protected function tearDown(): void
    {
        if ($this->createdForm !== null) {
            $this->wiki->services->get(FormManager::class)->delete($this->createdForm);
        }
        $this->wiki->services->get(AuthenticationService::class)->logout();
        parent::tearDown();
    }

    public function testAVisitorIsRefused(): void
    {
        $html = (string)$this->wiki->services->get(ActionRunner::class)->action('checkcontent', []);

        $this->assertStringContainsString('yw-alert--danger', $html);
        $this->assertStringContainsString(_t('BAZ_NEED_ADMIN_RIGHTS'), $html);
    }

    public function testTheFormPickerRendersForAnAdmin(): void
    {
        $this->loginAsAdmin();

        $formManager = $this->wiki->services->get(FormManager::class);
        $formManager->create([
            'id' => self::FORM_ID,
            'label' => 'CheckcontentActionTest form',
            'template' => '[{"type": "texte", "name": "bf_titre", "label": "Titre", "required": "1"}]',
            'condition' => '',
        ]);
        $this->createdForm = self::FORM_ID;

        $html = (string)$this->wiki->services->get(ActionRunner::class)->action('checkcontent', []);

        $this->assertStringNotContainsString('yw-alert--danger', $html);
        $this->assertStringContainsString('checkcontent', $html);
        $this->assertStringContainsString('CheckcontentActionTest form', $html, 'the form picker lists the forms');
    }

    public function testCheckingAFormRendersItsReport(): void
    {
        $this->loginAsAdmin();

        $formManager = $this->wiki->services->get(FormManager::class);
        $formManager->create([
            'id' => self::FORM_ID,
            'label' => 'CheckcontentActionTest form',
            'template' => '[{"type": "texte", "name": "bf_titre", "label": "Titre", "required": "1"}]',
            'condition' => '',
        ]);
        $this->createdForm = self::FORM_ID;

        $html = (string)$this->wiki->services->get(ActionRunner::class)
            ->action('checkcontent', ['id' => self::FORM_ID]);

        $this->assertStringNotContainsString('yw-alert--danger', $html);
        $this->assertStringContainsString(_t('BAZ_CHECKCONTENT_NO_PROBLEM', ['entries' => 0]), $html);
    }

    private function loginAsAdmin(): void
    {
        $groupManager = $this->wiki->services->get(GroupManager::class);
        $userManager = $this->wiki->services->get(UserManager::class);
        foreach ($groupManager->getMembers('admins') as $member) {
            if (str_starts_with($member, '@')) {
                continue;
            }
            $user = $userManager->getOneByName($member);
            if (!empty($user)) {
                $this->wiki->services->get(AuthenticationService::class)->login($user);
                if ($this->wiki->services->get(AclService::class)->isAdmin()) {
                    return;
                }
            }
        }
        $this->markTestSkipped('needs an administrator account to render the screen as');
    }
}
