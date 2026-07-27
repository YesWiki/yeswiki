<?php

namespace YesWiki\Test\Core\Service;

use YesWiki\Core\Service\EntryManager;
use YesWiki\Core\Service\FormManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Regression test for a bug found while testing the {{newtextsearch}} SQL-injection fix :
 * EntryManager::getHtmlDataAttributes() used to fall back to
 * $GLOBALS['wiki']->services->get(FormManager::class) when the form wasn't already in its
 * local $formtab cache (appendDisplayData() always calls it with the default
 * $formtab = ''). That global is only populated by the production HTTP bootstrap, so any
 * getOne()/appendDisplayData() call outside a real request threw "Call to a member
 * function get() on null". Fixed to use $this->wiki (already injected), matching every
 * other FormManager lookup in this file.
 *
 * The isEntry()/getOne() assertions after create() are a plain sanity check (EntryManager
 * already handles its isEntry() cache correctly around PageManager::save()'s internal
 * getOne() call, at the end of create() : no fix needed there) rather than a second
 * regression, kept because they exercise getHtmlDataAttributes() through the real
 * create()-then-read path this bug was found in.
 */
class EntryManagerTest extends YesWikiTestCase
{
    private const FORM_ID = '999903';
    private const ENTRY_TAG = 'EntryManagerRegressionTestEntry';

    public function testCreateThenGetOneWorksWithinTheSameRequest()
    {
        $wiki = $this->getWiki();
        $formManager = $wiki->services->get(FormManager::class);
        $entryManager = $wiki->services->get(EntryManager::class);

        $formManager->create([
            'id' => self::FORM_ID,
            'label' => 'EntryManager regression test form',
            'template' => '',
            'condition' => '',
        ]);

        // proves getOne() no longer needs the production HTTP bootstrap's global
        unset($GLOBALS['wiki']);

        $tag = null;
        try {
            // id_fiche supplied explicitly to avoid the unrelated legacy genere_nom_wiki()
            // global function (also $GLOBALS['wiki']-dependent, out of scope here)
            $entry = $entryManager->create(self::FORM_ID, [
                'antispam' => 1,
                'bf_titre' => 'Test entry',
                'id_fiche' => self::ENTRY_TAG,
            ]);
            $this->assertIsArray($entry);
            $this->assertArrayHasKey('id_fiche', $entry);
            $tag = $entry['id_fiche'];

            $this->assertTrue($entryManager->isEntry($tag));
            $fetched = $entryManager->getOne($tag);
            $this->assertIsArray($fetched);
            $this->assertArrayHasKey('html_data', $fetched);
        } finally {
            if ($tag !== null) {
                $entryManager->delete($tag, true);
            }
            $formManager->delete(self::FORM_ID);
        }
    }
}
