<?php

namespace YesWiki\Test\Content;

use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Render\Service\Performer;
use YesWiki\Render\Service\PresentationCatalog;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A wiki's own list template in `custom/templates/core/presentations/` is drawn on the server, from Items that carry their entry. */
class CustomPresentationTest extends YesWikiTestCase
{
    private const FORM_ID = '999943';
    private const TAG = 'CustomPresentationTestEntry';
    private const DIR = PresentationCatalog::CUSTOM_PREFIX . 'presentations';
    private const FILE = self::DIR . '/zz-probe-server.twig';

    protected function setUp(): void
    {
        parent::setUp();
        if (!is_dir(self::DIR)) {
            mkdir(self::DIR, 0777, true);
        }
        file_put_contents(self::FILE, "{# presentation\n   label: Sonde\n   category: card\n#}\n"
            . "{% for item in items %}<p class=\"probe\" data-town=\"{{ item.entry.bf_ville }}\">{{ item.title }}|{{ entryField(item.entry, 'bf_ville') }}</p>{% endfor %}");
        $this->freshCatalog();

        $services = $this->getWiki()->services;
        $services->get(FormManager::class)->create([
            'id' => self::FORM_ID,
            'label' => 'Formulaire de test des présentations du wiki',
            'template' => json_encode([
                ['type' => 'texte', 'name' => 'bf_titre', 'label' => 'Titre'],
                ['type' => 'texte', 'name' => 'bf_ville', 'label' => 'Ville'],
            ]),
            'condition' => '',
        ]);
        $services->get(EntryManager::class)->create(self::FORM_ID, ['antispam' => 1, 'tag' => self::TAG, 'bf_titre' => 'La halle', 'bf_ville' => 'Die']);
    }

    protected function tearDown(): void
    {
        $services = $this->getWiki()->services;
        if ($services->get(EntryManager::class)->isEntry(self::TAG)) {
            $services->get(EntryManager::class)->delete(self::TAG, true);
        }
        $services->get(FormManager::class)->delete(self::FORM_ID);
        @unlink(self::FILE);
        @rmdir(self::DIR);
        $this->freshCatalog();
        parent::tearDown();
    }

    private function freshCatalog(): void
    {
        (new \ReflectionProperty(PresentationCatalog::class, 'presentations'))->setValue($this->getWiki()->services->get(PresentationCatalog::class), null);
    }

    public function testTheTemplateIsDrawnWithTheEntryAndItsFields(): void
    {
        $catalog = $this->getWiki()->services->get(PresentationCatalog::class);
        $this->assertTrue($catalog->isCustomServer('zz-probe-server'));
        $this->assertTrue($catalog->get('zz-probe-server')?->custom);

        $html = (string)$this->getWiki()->services->get(Performer::class)
            ->run('entrylist', 'action', ['id' => self::FORM_ID, 'template' => 'zz-probe-server']);

        $this->assertStringContainsString('data-town="Die"', $html, 'the raw value reaches the template');
        $this->assertMatchesRegularExpression('/La halle\|.*Die/s', $html, 'a field renders as the form shows it');
        $this->assertStringNotContainsString('entry-list-dynamic-container', $html, 'it is not handed to Vue');
    }

    public function testTheGlobalSearchDoesNotOfferIt(): void
    {
        $names = array_column(array_merge(...array_column($this->getWiki()->services->get(PresentationCatalog::class)->sharedSwitcher(), 'presentations')), 'name');

        $this->assertNotContains('zz-probe-server', $names);
    }
}
