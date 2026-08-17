<?php

namespace YesWiki\Test\Content\Controller;

use YesWiki\Content\Entity\ContentTypeSchema;
use YesWiki\Content\Service\FormManager;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * The designer cannot be the protection -- the server enforces locked fields whatever the UI does -- but it does have to be honest about them, or a webmaster hits a delete button that appears to work and silently doesn't.
 */
class FormDesignerLockedFieldsTest extends YesWikiTestCase
{
    private function loginAsAdmin(): void
    {
        $wiki = $this->getWiki();
        $aclService = $wiki->services->get(AclService::class);
        $admin = current(array_filter(
            $wiki->services->get(UserManager::class)->getAll(),
            fn ($user) => $aclService->isAdmin($user['name'])
        ));
        $this->assertNotFalse($admin, 'need an existing admin to render the designer');
        $wiki->services->get(AuthenticationService::class)->login($admin);
    }

    public function testTheDesignerIsToldWhichFieldsAreLocked(): void
    {
        $wiki = $this->getWiki();
        $form = $wiki->services->get(FormManager::class)->getByContentType(ContentTypeSchema::TYPE_PAGE);
        $this->assertNotNull($form, 'the Page form should exist -- run ./yeswicli migrate');

        $this->loginAsAdmin();
        try {
            $html = $wiki->services->get(\YesWiki\Content\Controller\FormController::class)->update($form['id']);
        } finally {
            $wiki->services->get(AuthenticationService::class)->logout();
        }

        $this->assertIsString($html);
        $this->assertStringContainsString('data-locked-fields=', $html);
        foreach (['title', 'content', 'keywords'] as $locked) {
            $this->assertMatchesRegularExpression(
                '/data-locked-fields="[^"]*' . $locked . '/',
                $html,
                "the designer must know $locked is locked"
            );
        }
    }

    public function testAnOrdinaryFormTellsTheDesignerNothingIsLocked(): void
    {
        $wiki = $this->getWiki();
        $formManager = $wiki->services->get(FormManager::class);

        $ordinary = current(array_filter(
            $formManager->getAll(),
            fn ($form) => ($form[ContentTypeSchema::CONTENT_TYPE] ?? ContentTypeSchema::TYPE_ENTRY) === ContentTypeSchema::TYPE_ENTRY
        ));
        $this->assertNotFalse($ordinary, 'need a seeded bazar form');

        $this->loginAsAdmin();
        try {
            $html = $wiki->services->get(\YesWiki\Content\Controller\FormController::class)->update($ordinary['id']);
        } finally {
            $wiki->services->get(AuthenticationService::class)->logout();
        }

        $this->assertIsString($html);
        $this->assertStringContainsString('data-locked-fields="[]"', $html);
    }
}
