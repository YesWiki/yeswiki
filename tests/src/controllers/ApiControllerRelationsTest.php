<?php

namespace YesWiki\Test\Core\Controller;

use PHPUnit\Framework\Attributes\CoversMethod;
use YesWiki\Core\Controller\ApiController;
use YesWiki\Core\Service\EntryManager;
use YesWiki\Core\Service\FormManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Regression test for ticket 14 (qrcode absorbed into core): ApiController::getAllRelations()/
 * createRelation() replace yeswiki-extension-qrcode's own ApiController, storing paired
 * qrcode-scan relations as Bazar entries (unchanged EntryManager-backed storage).
 *
 * Also covers a real copy-paste bug found while relocating: getAllRelations()'s entry-cache
 * population populated $entryCache[bf_fiche1] by calling getOne(bf_fiche2) instead of
 * getOne(bf_fiche1) -- meaning 'entry1' in the API response silently duplicated 'entry2''s
 * data instead of showing the actual first linked entry.
 */
#[CoversMethod(ApiController::class, 'getAllRelations')]
#[CoversMethod(ApiController::class, 'createRelation')]
class ApiControllerRelationsTest extends YesWikiTestCase
{
    private const RELATION_FORM_ID = '999906';
    private const ENTITY_FORM_ID = '999907';
    private const ENTITY1_TAG = 'ApiControllerRelationsTestEntity1';
    private const ENTITY2_TAG = 'ApiControllerRelationsTestEntity2';

    public function testCreateThenGetAllRelationsReturnsBothDistinctEntries()
    {
        $wiki = $this->getWiki();
        $formManager = $wiki->services->get(FormManager::class);
        $entryManager = $wiki->services->get(EntryManager::class);
        $controller = $wiki->services->get(ApiController::class);
        // EntryManager::create() reaches src/bazar.functions.php helpers relying on $GLOBALS['wiki'],
        // normally populated by the production HTTP bootstrap (same workaround as
        // AceditorWidgetTest/FiltertagsActionTest).
        $GLOBALS['wiki'] = $wiki;

        $formManager->create([
            'bn_id_nature' => self::ENTITY_FORM_ID,
            'bn_label_nature' => 'ApiControllerRelationsTest entity form',
            'bn_template' => '',
            'bn_condition' => '',
        ]);
        $formManager->create([
            'bn_id_nature' => self::RELATION_FORM_ID,
            'bn_label_nature' => 'ApiControllerRelationsTest relation form',
            'bn_template' => '',
            'bn_condition' => '',
        ]);

        $originalRelationFormId = $wiki->config['qrcode_config']['relation_form_id'];
        $wiki->config['qrcode_config']['relation_form_id'] = self::RELATION_FORM_ID;

        $relationTag = null;
        try {
            $entry1 = $entryManager->create(self::ENTITY_FORM_ID, [
                'antispam' => 1,
                'bf_titre' => 'First entity',
                'id_fiche' => self::ENTITY1_TAG,
            ]);
            $entry2 = $entryManager->create(self::ENTITY_FORM_ID, [
                'antispam' => 1,
                'bf_titre' => 'Second entity',
                'id_fiche' => self::ENTITY2_TAG,
            ]);
            $this->assertIsArray($entry1);
            $this->assertIsArray($entry2);

            $_POST = [
                'antispam' => 1,
                'bf_titre' => 'Relation test',
                'bf_relation' => 'contact',
                'bf_fiche1' => self::ENTITY1_TAG,
                'bf_fiche2' => self::ENTITY2_TAG,
            ];
            $response = $controller->createRelation();
            $this->assertSame(201, $response->getStatusCode());

            // empty type: getAllRelations() skips the bf_relation query filter, relying only
            // on the formsIds scoping -- avoids depending on Bazar's field-query internals,
            // which aren't this test's concern
            $_GET = [];
            $listResponse = $controller->getAllRelations('');
            $relations = json_decode($listResponse->getContent(), true);
            $this->assertIsArray($relations);
            $this->assertNotEmpty($relations);

            $found = current(array_filter(
                $relations,
                fn ($r) => $r['bf_fiche1'] === self::ENTITY1_TAG && $r['bf_fiche2'] === self::ENTITY2_TAG
            ));
            $this->assertNotFalse($found, 'the created relation must show up in getAllRelations()');
            $relationTag = $found['id_fiche'] ?? null;
            $this->assertSame(self::ENTITY1_TAG, $found['entry1']['id_fiche']);
            $this->assertSame(self::ENTITY2_TAG, $found['entry2']['id_fiche']);
            $this->assertNotSame(
                $found['entry1']['id_fiche'],
                $found['entry2']['id_fiche'],
                'entry1 and entry2 must be the two distinct linked entities, not the same one twice'
            );
        } finally {
            $wiki->config['qrcode_config']['relation_form_id'] = $originalRelationFormId;
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
