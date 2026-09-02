<?php

namespace YesWiki\Test\Core\Service;

use YesWiki\Content\Service\EntryDisplay;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Regression test for a bug found while testing the {{newtextsearch}} SQL-injection fix : EntryManager::$this->getService(EntryDisplay::class)->dataAttributes() used to fall back to $GLOBALS['wiki']->services->get(FormManager::class) when the form wasn't already in its local $formtab cache (appendDisplayData() always calls it with the default $formtab = '').
 */
class EntryManagerTest extends YesWikiTestCase
{
    private const FORM_ID = '999903';
    private const ENTRY_TAG = 'EntryManagerRegressionTestEntry';

    public function testCreateThenGetOneWorksWithinTheSameRequest(): void
    {
        $wiki = $this->getWiki();
        $formManager = $wiki->services->get(FormManager::class);
        $entryManager = $wiki->services->get(EntryManager::class);

        $formManager->create([
            'id' => self::FORM_ID,
            'label' => 'EntryManager regression test form',
            'template' => '',
        ]);

        unset($GLOBALS['wiki']);

        $tag = null;
        try {
            $entry = $entryManager->create(self::FORM_ID, [
                'antispam' => 1,
                'bf_titre' => 'Test entry',
                'tag' => self::ENTRY_TAG,
            ]);
            $this->assertArrayHasKey('tag', $entry);
            $tag = $entry['tag'];

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
