<?php

namespace YesWiki\Test\Content;

use YesWiki\Content\Service\BazarListService;
use YesWiki\Content\Service\FormManager;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A facet over a field the reader may not read would show its label and an empty choice, so it is not offered. */
class FacetReadAccessTest extends YesWikiTestCase
{
    private const FORM_ID = 8652;

    protected function setUp(): void
    {
        $forms = $this->getWiki()->services->get(FormManager::class);
        if ($forms->getOne(self::FORM_ID) !== null) {
            $forms->delete(self::FORM_ID);
        }
        $forms->create([
            'id' => self::FORM_ID,
            'label' => 'FacetReadAccessTest',
            'template' => json_encode([
                ['type' => 'texte', 'name' => 'bf_titre', 'label' => 'Titre'],
                ['type' => 'texte', 'name' => 'bf_ville', 'label' => 'Ville'],
                ['type' => 'texte', 'name' => 'bf_etat', 'label' => 'Etat de la fiche', 'read_access' => '@admins'],
                ['type' => 'texte', 'name' => 'bf_personne', 'label' => 'Personne concernée', 'read_access' => '%'],
            ]),
            'condition' => '',
        ]);
        $this->getWiki()->services->get(AuthenticationService::class)->logout();
    }

    protected function tearDown(): void
    {
        $this->getWiki()->services->get(AuthenticationService::class)->logout();
        $this->getWiki()->services->get(FormManager::class)->delete(self::FORM_ID);
    }

    /** @return list<string> */
    private function offeredFacets(): array
    {
        $services = $this->getWiki()->services;
        $form = $services->get(FormManager::class)->getOne(self::FORM_ID);
        $this->assertNotNull($form);
        $entries = [
            ['tag' => 'FacetReadAccessUn', 'owner' => 'Quelquun', 'form_id' => (string)self::FORM_ID, 'bf_ville' => 'Nantes', 'bf_etat' => 'brouillon', 'bf_personne' => 'Ada'],
        ];
        $filters = $services->get(BazarListService::class)->getFilters(
            ['groups' => ['bf_ville', 'bf_etat', 'bf_personne'], 'filterposition' => 'top'],
            $entries,
            [self::FORM_ID => $form]
        );

        return array_column($filters, 'propName');
    }

    public function testASignedOutReaderOnlyGetsTheFacetsOverPublicFields(): void
    {
        $this->assertSame(['bf_ville'], $this->offeredFacets());
    }

    public function testAnAdminGetsEveryFacet(): void
    {
        $services = $this->getWiki()->services;
        $aclService = $services->get(AclService::class);
        $admin = current(array_filter(
            $services->get(UserManager::class)->getAll(),
            fn ($user) => $aclService->isAdmin($user['name'])
        ));
        $this->assertNotFalse($admin, 'need an existing admin on this wiki');
        $services->get(AuthenticationService::class)->login($admin);

        $this->assertSame(['bf_ville', 'bf_etat', 'bf_personne'], $this->offeredFacets());
    }
}
