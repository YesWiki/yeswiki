<?php

namespace YesWiki\Test\Actions;

use YesWiki\Bazar\Service\FormManager;
use YesWiki\Bazar\Service\ListManager;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * {{newtextsearch}} must not let a list option id inject SQL into its search query.
 */
class NewtextsearchActionTest extends YesWikiTestCase
{
    private const LIST_LABEL = 'ZZZ123WXDTESTFLAG';
    private const SECRET_MARKER = 'NEWTEXTSEARCH_SQLI_ORACLE_SECRET_MARKER';
    private const SECRET_PAGE_TAG = 'NewtextsearchSqliSecretPage';
    private const CONTROL_PAGE_TAG = 'NewtextsearchSqliControlPage';
    private const LIST_ID = 'NewtextsearchRegressionTestList';
    private const FORM_ID = '999901';
    private const FIELD_NAME = 'bf_regressiontestenum';

    public function testMaliciousListOptionIdCannotLeakDataAndLegitimateSearchStillWorks()
    {
        $wiki = $this->getWiki();
        $GLOBALS['wiki'] = $wiki;
        $pageManager = $wiki->services->get(PageManager::class);
        $listManager = $wiki->services->get(ListManager::class);
        $formManager = $wiki->services->get(FormManager::class);
        $aclService = $wiki->services->get(AclService::class);

        $pageManager->save(self::SECRET_PAGE_TAG, self::SECRET_MARKER, '', true);
        $pageManager->save(self::CONTROL_PAGE_TAG, self::LIST_LABEL, '', true);
        $aclService->save(self::SECRET_PAGE_TAG, 'read', '*');
        $aclService->save(self::CONTROL_PAGE_TAG, 'read', '*');

        $maliciousKey = "x' OR body LIKE '%" . self::SECRET_MARKER . "%' ))) #";
        $listManager->create('Newtextsearch regression test list', [
            ['id' => $maliciousKey, 'label' => self::LIST_LABEL],
        ], self::LIST_ID);

        $templateRow = array_fill(0, 16, '');
        $templateRow[0] = 'liste';
        $templateRow[1] = self::LIST_ID;
        $templateRow[6] = self::FIELD_NAME;
        $formManager->create([
            'bn_id_nature' => self::FORM_ID,
            'bn_label_nature' => 'Newtextsearch regression test form',
            'bn_template' => implode('***', $templateRow),
            'bn_condition' => '',
        ]);

        try {
            $html = $wiki->Action('newtextsearch', 1, ['phrase' => self::LIST_LABEL]);

            $this->assertStringNotContainsString(
                self::SECRET_PAGE_TAG,
                $html,
                'the SQL injection leaked an unrelated page through the boolean-oracle condition'
            );
            $this->assertStringContainsString(self::CONTROL_PAGE_TAG, $html);
        } finally {
            $pageManager->deleteOrphaned(self::SECRET_PAGE_TAG);
            $pageManager->deleteOrphaned(self::CONTROL_PAGE_TAG);
            $aclService->delete(self::SECRET_PAGE_TAG);
            $aclService->delete(self::CONTROL_PAGE_TAG);
            $pageManager->deleteOrphaned(self::LIST_ID);
            $wiki->services->get(TripleStore::class)->delete(self::LIST_ID, TripleStore::TYPE_URI, null, '', '');
            $formManager->delete(self::FORM_ID);
            unset($GLOBALS['wiki']);
        }
    }
}
