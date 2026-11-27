<?php

/** Saving a form whose role map names a field the way the designer's select does. */

namespace YesWiki\Test\Content\Controller;

use Symfony\Component\HttpFoundation\Request;
use YesWiki\Content\Controller\FormController;
use YesWiki\Content\Entity\FieldRole;
use YesWiki\Content\Service\FormManager;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Exception\ExitException;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\RequestScope;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

class FormRoleValidationTest extends YesWikiTestCase
{
    private const FORM_ID = '9650';

    private const TEMPLATE = [
        ['type' => 'texte', 'name' => 'bf_titre', 'label' => 'Titre'],
        ['type' => 'image', 'name' => 'bf_image', 'label' => 'Image'],
    ];

    protected function tearDown(): void
    {
        $wiki = $this->getWiki();
        $wiki->services->get(AuthenticationService::class)->logout();
        $wiki->services->get(FormManager::class)->delete(self::FORM_ID);
        parent::tearDown();
    }

    public function testAnImageFieldNamedAsInTheTemplateCanPlayTheImageRole(): void
    {
        $wiki = $this->getWiki();
        $admin = current(array_filter(
            $wiki->services->get(UserManager::class)->getAll(),
            fn ($user) => $wiki->services->get(AclService::class)->isAdmin($user['name'])
        ));
        if ($admin === false) {
            $this->markTestSkipped('this wiki has no admin to edit as');
        }
        $wiki->services->get(AuthenticationService::class)->login($admin);

        $formManager = $wiki->services->get(FormManager::class);
        $formManager->delete(self::FORM_ID);
        $formManager->create([
            'id' => self::FORM_ID,
            'label' => 'FormRoleValidationTest',
            'entry_title_template' => '{{bf_titre}}',
            'template' => json_encode(self::TEMPLATE),
        ]);

        $wiki->services->get(RequestScope::class)->startNewRequest();
        $wiki->services->get(CurrentRequest::class)->replace(new Request([], [
            'valider' => '1',
            'id' => self::FORM_ID,
            'label' => 'FormRoleValidationTest',
            'entry_title_template' => '{{bf_titre}}',
            'template' => json_encode(self::TEMPLATE),
            FieldRole::FORM_PROPERTY => [FieldRole::IMAGE => 'bf_image'],
        ]));

        $saved = false;
        try {
            $wiki->services->get(FormController::class)->update(self::FORM_ID);
        } catch (ExitException) {
            $saved = true;
        }

        $this->assertTrue($saved, 'the controller redirects once it has saved');
        $stored = $formManager->getUntranslated(self::FORM_ID);
        $this->assertSame('bf_image', FieldRole::normalizeMap($stored[FieldRole::FORM_PROPERTY] ?? null)[FieldRole::IMAGE] ?? null);
    }
}
