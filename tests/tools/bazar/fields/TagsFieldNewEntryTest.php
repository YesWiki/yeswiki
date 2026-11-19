<?php

namespace YesWiki\Test\Bazar\Field;

use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * The tags of an entry are stored against the entry, including when it is created without a given tag.
 */
class TagsFieldNewEntryTest extends YesWikiTestCase
{
    private const TAG_PREDICATE = 'http://outils-reseaux.org/_vocabulary/tag';

    private $wiki;
    private string $formId;
    private ?string $entryTag = null;

    protected function setUp(): void
    {
        $this->wiki = $this->getWiki();
        $GLOBALS['wiki'] = $this->wiki;
        unset($GLOBALS['delete_tags']);
        $this->formId = $this->wiki->services->get(FormManager::class)->create([
            'bn_label_nature' => 'Tags field new entry test form',
            'bn_template' => implode("\n", [
                'texte***bf_titre***Titre***60***255*** *** ***text***1*** *** *** * *** * *** *** *** ***',
                'tags***bf_tags***Mots clés*** *** *** *** *** ***0*** *** *** * *** * *** *** *** ***',
            ]),
            'bn_condition' => '',
        ]);
    }

    protected function tearDown(): void
    {
        $tripleStore = $this->wiki->services->get(TripleStore::class);
        foreach (['', $this->entryTag] as $resource) {
            if ($resource !== null) {
                foreach ($tripleStore->getMatching($resource, self::TAG_PREDICATE, null, '=', '=', '') as $triple) {
                    if (in_array($triple['value'], ['tagsfieldtestun', 'tagsfieldtestdeux'], true)) {
                        $tripleStore->delete($resource, self::TAG_PREDICATE, $triple['value'], '', '');
                    }
                }
            }
        }
        if ($this->entryTag !== null) {
            $this->wiki->services->get(EntryManager::class)->delete($this->entryTag, true);
        }
        $this->wiki->services->get(FormManager::class)->delete($this->formId);
        unset($GLOBALS['delete_tags']);
    }

    public function testTagsOfANewEntryAreStoredAgainstIt()
    {
        $warnings = [];
        set_error_handler(function ($errno, $message) use (&$warnings) {
            $warnings[] = $message;

            return true;
        }, E_WARNING);
        try {
            $entry = $this->wiki->services->get(EntryManager::class)->create($this->formId, [
                'antispam' => 1,
                'bf_titre' => 'Tags field new entry ' . bin2hex(random_bytes(4)),
                'bf_tags' => 'tagsfieldtestun, tagsfieldtestdeux',
            ]);
        } finally {
            restore_error_handler();
        }
        $this->entryTag = $entry['id_fiche'];

        $this->assertSame([], $warnings);
        $stored = array_column($this->wiki->services->get(TripleStore::class)->getMatching($this->entryTag, self::TAG_PREDICATE, null, '=', '=', ''), 'value');
        sort($stored);
        $this->assertSame(['tagsfieldtestdeux', 'tagsfieldtestun'], $stored);
    }
}
