<?php

namespace YesWiki\Test\Content;

use YesWiki\Content\Controller\EntryController;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** An entry of a form with a semantic template shows, the template reading the stored values rather than their HTML. */
class EntrySemanticViewTest extends YesWikiTestCase
{
    private const FORM_ID = '999942';
    private const TAG = 'EntrySemanticViewTestAtelier';

    protected function setUp(): void
    {
        parent::setUp();
        $services = $this->getWiki()->services;
        $services->get(FormManager::class)->create([
            'id' => self::FORM_ID,
            'label' => 'Formulaire de test sémantique',
            'template' => json_encode([
                ['type' => 'texte', 'name' => 'bf_titre', 'label' => 'Titre'],
                ['type' => 'listedatedeb', 'name' => 'bf_date_debut_evenement', 'label' => 'Début'],
            ]),
            'condition' => '',
            'sem_type' => 'Event',
            'sem_template' => '{"@context": "https://www.w3.org/ns/activitystreams", "type": "Event", "startTime": {{ bf_date_debut_evenement | date("c") | json_encode }}}',
        ]);
        $services->get(EntryManager::class)->create(self::FORM_ID, [
            'antispam' => 1,
            'tag' => self::TAG,
            'bf_titre' => 'Atelier',
            'bf_date_debut_evenement' => '2026-11-24',
        ]);
    }

    protected function tearDown(): void
    {
        $services = $this->getWiki()->services;
        if ($services->get(EntryManager::class)->isEntry(self::TAG)) {
            $services->get(EntryManager::class)->delete(self::TAG, true);
        }
        $services->get(FormManager::class)->delete(self::FORM_ID);
        parent::tearDown();
    }

    public function testADateTheTemplateFormatsDoesNotBreakTheEntry(): void
    {
        $html = (string)$this->getWiki()->services->get(EntryController::class)->view(self::TAG, '', true);

        $this->assertStringContainsString('Atelier', $html);
        $this->assertStringNotContainsString('Failed to parse', $html);
    }
}
