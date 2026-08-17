<?php

namespace YesWiki\Test\Core\Service;

use YesWiki\Content\Service\FormManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Regression test found while testing the {{newtextsearch}} SQL-injection fix : FormManager::getMany() unconditionally cached getOne()'s result into $cachedForms, including null when the form id doesn't correspond to an existing `nature` row.
 */
class FormManagerTest extends YesWikiTestCase
{
    private const NONEXISTENT_FORM_ID = '999904';

    public function testGetManyWithNonExistentIdDoesNotPoisonGetAll()
    {
        $wiki = $this->getWiki();
        $formManager = $wiki->services->get(FormManager::class);

        $this->assertNull($formManager->getOne(self::NONEXISTENT_FORM_ID));

        $results = $formManager->getMany([self::NONEXISTENT_FORM_ID]);
        $this->assertNull($results[self::NONEXISTENT_FORM_ID]);

        foreach ($formManager->getAll() as $id => $form) {
            $this->assertIsArray($form, "getAll() returned a non-array entry for id $id");
        }
    }
}
