<?php

namespace YesWiki\Test\Content;

use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\TemplateFieldCatalog;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A template declares nothing: its path says what it is, its source says which form it reads. */
class TemplateFieldCatalogTest extends YesWikiTestCase
{
    private const FORM_ID = '999963';

    public static function setUpBeforeClass(): void
    {
        $formManager = self::getWiki()->services->get(FormManager::class);
        if ($formManager->getOne(self::FORM_ID) !== null) {
            $formManager->delete(self::FORM_ID);
        }
        $formManager->create([
            'id' => self::FORM_ID,
            'label' => 'Template field catalog test form',
            'template' => json_encode([
                ['type' => 'texte', 'name' => 'bf_catalogue_titre', 'label' => '<b>Le titre</b>', 'required' => '1'],
                ['type' => 'textelong', 'name' => 'bf_catalogue_long', 'label' => 'Le texte'],
            ]),
            'condition' => '',
        ]);
    }

    public static function tearDownAfterClass(): void
    {
        self::getWiki()->services->get(FormManager::class)->delete(self::FORM_ID);
    }

    private function catalog(): TemplateFieldCatalog
    {
        return $this->getWiki()->services->get(TemplateFieldCatalog::class);
    }

    public function testAnEntryTemplateIsTiedToTheFormInItsName(): void
    {
        $found = $this->catalog()->forTemplate('core/fiche-' . self::FORM_ID . '.twig', '');

        $this->assertNotNull($found);
        $this->assertSame('entry', $found['kind']);
        $this->assertSame([self::FORM_ID], array_column($found['forms'], 'id'));
        $this->assertSame('Le titre', $found['forms'][0]['fields'][0]['label']);
    }

    public function testAnEntryTemplateOfAMissingFormOffersNothing(): void
    {
        $this->assertNull($this->catalog()->forTemplate('core/fiche-999962.twig', ''));
    }

    public function testAListTemplateFindsItsFormFromWhatItReads(): void
    {
        $source = "{% set necessary_fields = ['bf_catalogue_long'] %}\n<a v-text=\"entry.bf_catalogue_titre\"></a>";

        $found = $this->catalog()->forTemplate('core/entries/index-dynamic-templates/probe.twig', $source);

        $this->assertNotNull($found);
        $this->assertSame('list', $found['kind']);
        $this->assertSame(self::FORM_ID, $found['selected']);
    }

    public function testOnlyLongTextIsOfferedAsHtml(): void
    {
        $found = $this->catalog()->forTemplate('core/fiche-' . self::FORM_ID . '.twig', '');

        $this->assertNotNull($found);
        $this->assertSame([false, true], array_column($found['forms'][0]['fields'], 'html'));
    }

    public function testAnyOtherTemplateHasNoFields(): void
    {
        $this->assertNull($this->catalog()->forTemplate('core/admin/keywords.twig', '{{ entry.bf_titre }}'));
    }
}
