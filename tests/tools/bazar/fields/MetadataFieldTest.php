<?php

namespace YesWiki\Test\Bazar\Field;

use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * A form's theme settings land in the metadata of each entry, preset included.
 */
class MetadataFieldTest extends YesWikiTestCase
{
    private $wiki;
    private $entryManager;
    private $formManager;
    private $pageManager;
    private string $formId;
    private array $createdTags = [];

    protected function setUp(): void
    {
        $this->wiki = $this->getWiki();
        $GLOBALS['wiki'] = $this->wiki;
        $this->entryManager = $this->wiki->services->get(EntryManager::class);
        $this->formManager = $this->wiki->services->get(FormManager::class);
        $this->pageManager = $this->wiki->services->get(PageManager::class);
        $this->wiki->services->get(AuthController::class)->logout();

        $this->formId = $this->formManager->create([
            'bn_label_nature' => 'Metadata field test form',
            'bn_template' => implode("\n", [
                'texte***bf_titre***Titre***60***255*** *** ***text***1*** *** *** * *** * *** *** *** ***',
                'metadatas***margot***1col.tpl.html***margot.css*** ***fun.css*** *** *** *** *** *** *** *** *** *** ***',
            ]),
            'bn_condition' => '',
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->createdTags as $tag) {
            if ($this->entryManager->isEntry($tag)) {
                $this->entryManager->delete($tag, true);
            }
            $this->pageManager->deleteOrphaned($tag);
            $this->wiki->services->get(AclService::class)->delete($tag);
        }
        $this->createdTags = [];
        $this->formManager->delete($this->formId);
    }

    public function testAnEntryGetsTheFormThemeAndPreset()
    {
        $entry = $this->entryManager->create($this->formId, ['antispam' => 1, 'bf_titre' => 'Metadata field entry']);
        $this->createdTags[] = $entry['id_fiche'];

        $metadata = $this->pageManager->getMetadata($entry['id_fiche']) ?? [];

        $this->assertSame('margot', $metadata['theme'] ?? null);
        $this->assertSame('1col.tpl.html', $metadata['squelette'] ?? null);
        $this->assertSame('margot.css', $metadata['style'] ?? null);
        $this->assertSame('fun.css', $metadata['favorite_preset'] ?? null);
    }

    public function testAnUpdatedEntryKeepsTheFormThemeAndPreset()
    {
        $entry = $this->entryManager->create($this->formId, ['antispam' => 1, 'bf_titre' => 'Metadata field updated']);
        $this->createdTags[] = $entry['id_fiche'];
        $this->wiki->services->get(AclService::class)->save($entry['id_fiche'], 'write', '*');

        $this->entryManager->update($entry['id_fiche'], ['antispam' => 1, 'bf_titre' => 'Metadata field updated twice']);
        $metadata = $this->pageManager->getMetadata($entry['id_fiche']) ?? [];

        $this->assertSame('margot', $metadata['theme'] ?? null);
        $this->assertSame('fun.css', $metadata['favorite_preset'] ?? null);
    }
}
