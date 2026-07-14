<?php

namespace YesWiki\Test\Bazar\Service;

use YesWiki\Bazar\Service\FormManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Regression test found while testing the {{newtextsearch}} SQL-injection fix :
 * FormManager::getMany() unconditionally cached getOne()'s result into $cachedForms,
 * including null when the form id doesn't correspond to an existing `nature` row.
 * getAll()'s own cache-population loop only overwrites entries for ids that actually
 * exist, so that cached null lingered forever ; getAll()'s return filter only checked
 * the *key* was numeric, not that the *value* was an array, so the null leaked into
 * every later getAll() call for the rest of the request (e.g. a second
 * {{newtextsearch}} call would crash inside SearchManager::searchInFormOptions() with
 * "Argument #2 ($form) must be of type array, null given").
 *
 * Reproduced via a genuinely orphaned form id (a SelectEntryField/CheckboxEntryField
 * pointing at a form that no longer exists is a realistic way for getMany() to be
 * called with a non-existent id in production).
 */
class FormManagerTest extends YesWikiTestCase
{
    private const NONEXISTENT_FORM_ID = '999904';

    public function testGetManyWithNonExistentIdDoesNotPoisonGetAll()
    {
        $wiki = $this->getWiki();
        $formManager = $wiki->services->get(FormManager::class);

        // sanity : this id genuinely doesn't exist
        $this->assertNull($formManager->getOne(self::NONEXISTENT_FORM_ID));

        $results = $formManager->getMany([self::NONEXISTENT_FORM_ID]);
        $this->assertNull($results[self::NONEXISTENT_FORM_ID]);

        foreach ($formManager->getAll() as $id => $form) {
            $this->assertIsArray($form, "getAll() returned a non-array entry for id $id");
        }
    }
}
