<?php

namespace YesWiki\Test\Content;

use YesWiki\Content\Service\EntryLimit;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Render\Service\ActionRunner;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A form's `max_entries` replaces its entry form with a message once it holds that many entries, wherever the entry form is shown. */
class FormEntryLimitTest extends YesWikiTestCase
{
    private const FORM_ID = '999931';

    private ?string $entryTag = null;

    protected function tearDown(): void
    {
        $services = $this->getWiki()->services;
        if ($this->entryTag !== null) {
            $services->get(EntryManager::class)->delete($this->entryTag, true);
        }
        $services->get(FormManager::class)->delete(self::FORM_ID);
        parent::tearDown();
    }

    public function testTheEntryFormGivesWayToTheMessageOnceTheLimitIsReached(): void
    {
        $services = $this->getWiki()->services;
        $formManager = $services->get(FormManager::class);
        $formManager->create([
            'id' => self::FORM_ID,
            'label' => 'Entry limit test form',
            'template' => '',
            'max_entries' => '1',
            'max_entries_message' => 'Complet : {nb} sur {limit}',
            'max_entries_count_message' => 'Places prises : {nb} sur {limit}',
        ]);
        $form = $formManager->getOne(self::FORM_ID);
        $this->assertSame('1', $form['max_entries'] ?? null, 'the limit is stored with the form');

        $limit = $services->get(EntryLimit::class);
        $this->assertNull($limit->refusal($form));
        $this->assertSame('Places prises : 0 sur 1', $limit->counter($form));
        $open = (string)$services->get(ActionRunner::class)->action('bazar', ['id' => self::FORM_ID, 'view' => 'saisir', 'showmenu' => '0']);
        $this->assertStringContainsString('Places prises : 0 sur 1', $open);
        $this->assertStringNotContainsString('Complet', $open);

        $this->entryTag = $services->get(EntryManager::class)->create(self::FORM_ID, ['antispam' => 1, 'bf_titre' => 'Entry limit test entry'])['tag'];

        $this->assertSame('Complet : 1 sur 1', $limit->refusal($form));
        $full = (string)$services->get(ActionRunner::class)->action('bazar', ['id' => self::FORM_ID, 'view' => 'saisir', 'showmenu' => '0']);
        $this->assertStringContainsString('Complet : 1 sur 1', $full);
        $this->assertStringNotContainsString('Places prises', $full);
    }

    public function testAFormWithoutALimitHasNoMessage(): void
    {
        $limit = $this->getWiki()->services->get(EntryLimit::class);

        $this->assertSame(0, EntryLimit::limitOf(['id' => self::FORM_ID]));
        $this->assertNull($limit->refusal(['id' => self::FORM_ID]));
        $this->assertNull($limit->counter(['id' => self::FORM_ID, 'max_entries_count_message' => '{nb}']));
    }
}
