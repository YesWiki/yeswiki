<?php

namespace YesWiki\Test\Core\Controller;

use PHPUnit\Framework\Attributes\CoversMethod;
use YesWiki\Content\Api\RelationApiController;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Regression test for ticket 14 (qrcode absorbed into core): ApiController::getAllRelations()/ createRelation() replace yeswiki-extension-qrcode's own ApiController, storing paired qrcode-scan relations as Bazar entries (unchanged EntryManager-backed storage).
 */
#[CoversMethod(RelationApiController::class, 'getAllRelations')]
#[CoversMethod(RelationApiController::class, 'createRelation')]
class ApiControllerRelationsTest extends YesWikiTestCase
{
    private const RELATION_FORM_ID = '999906';
    private const ENTITY_FORM_ID = '999907';
    private const ENTITY1_TAG = 'ApiControllerRelationsTestEntity1';
    private const ENTITY2_TAG = 'ApiControllerRelationsTestEntity2';

    public function testCreateThenGetAllRelationsReturnsBothDistinctEntries(): void
    {
        $wiki = $this->getWiki();
        $formManager = $wiki->services->get(FormManager::class);
        $entryManager = $wiki->services->get(EntryManager::class);
        $controller = $wiki->services->get(RelationApiController::class);

        $GLOBALS['yeswikiServices'] = $wiki->services;

        $formManager->create([
            'id' => self::ENTITY_FORM_ID,
            'label' => 'ApiControllerRelationsTest entity form',
            'template' => '',
        ]);
        $formManager->create([
            'id' => self::RELATION_FORM_ID,
            'label' => 'ApiControllerRelationsTest relation form',
            'template' => '',
        ]);

        $originalRelationFormId = $wiki->services->get(\YesWiki\Kernel\Service\RuntimeConfig::class)['qrcode_config']['relation_form_id'];
        $wiki->services->get(\YesWiki\Kernel\Service\RuntimeConfig::class)['qrcode_config']['relation_form_id'] = self::RELATION_FORM_ID;

        $relationTag = null;
        try {
            $entry1 = $entryManager->create(self::ENTITY_FORM_ID, [
                'antispam' => 1,
                'bf_titre' => 'First entity',
                'tag' => self::ENTITY1_TAG,
            ]);
            $entry2 = $entryManager->create(self::ENTITY_FORM_ID, [
                'antispam' => 1,
                'bf_titre' => 'Second entity',
                'tag' => self::ENTITY2_TAG,
            ]);
            $this->assertNotEmpty($entry1);
            $this->assertNotEmpty($entry2);

            $_POST = [
                'antispam' => 1,
                'bf_titre' => 'Relation test',
                'bf_relation' => 'contact',
                'bf_fiche1' => self::ENTITY1_TAG,
                'bf_fiche2' => self::ENTITY2_TAG,
            ];
            $response = $controller->createRelation();
            $this->assertSame(201, $response->getStatusCode());

            $_GET = [];
            $listResponse = $controller->getAllRelations('');
            $listContent = $listResponse->getContent();
            $this->assertIsString($listContent);
            $relations = json_decode($listContent, true);
            $this->assertIsArray($relations);
            $this->assertNotEmpty($relations);

            $found = current(array_filter(
                $relations,
                fn ($r) => $r['bf_fiche1'] === self::ENTITY1_TAG && $r['bf_fiche2'] === self::ENTITY2_TAG
            ));
            $this->assertNotFalse($found, 'the created relation must show up in getAllRelations()');
            $relationTag = $found['tag'] ?? null;
            $this->assertSame(self::ENTITY1_TAG, $found['entry1']['tag']);
            $this->assertSame(self::ENTITY2_TAG, $found['entry2']['tag']);
            $this->assertNotSame(
                $found['entry1']['tag'],
                $found['entry2']['tag'],
                'entry1 and entry2 must be the two distinct linked entities, not the same one twice'
            );
        } finally {
            $wiki->services->get(\YesWiki\Kernel\Service\RuntimeConfig::class)['qrcode_config']['relation_form_id'] = $originalRelationFormId;
            $entryManager->delete(self::ENTITY1_TAG, true);
            $entryManager->delete(self::ENTITY2_TAG, true);
            if ($relationTag) {
                $entryManager->delete($relationTag, true);
            }
            $formManager->delete(self::ENTITY_FORM_ID);
            $formManager->delete(self::RELATION_FORM_ID);
        }
    }
}
