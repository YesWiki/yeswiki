<?php

namespace YesWiki\Test\Bazar\Controller;

use YesWiki\Bazar\Controller\EntryController;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * A custom entry template receives every field it can display, linked entries included.
 */
class EntryControllerCustomTemplateTest extends YesWikiTestCase
{
    private $wiki;
    private $entryManager;
    private $formManager;
    private $pageManager;
    private $aclService;
    private $linkedFormId;
    private $formId;
    private $createdTags = [];

    protected function setUp(): void
    {
        $this->wiki = $this->getWiki();
        $GLOBALS['wiki'] = $this->wiki;
        $this->entryManager = $this->wiki->services->get(EntryManager::class);
        $this->formManager = $this->wiki->services->get(FormManager::class);
        $this->pageManager = $this->wiki->services->get(PageManager::class);
        $this->aclService = $this->wiki->services->get(AclService::class);
        $this->wiki->services->get(AuthController::class)->logout();

        $this->linkedFormId = $this->formManager->create([
            'bn_label_nature' => 'Custom template linked form',
            'bn_template' => self::fieldLine(['texte', 'bf_titre', 'Titre']),
            'bn_condition' => '',
        ]);

        $this->formId = $this->formManager->create([
            'bn_label_nature' => 'Custom template form',
            'bn_template' => implode("\n", [
                self::fieldLine(['texte', 'bf_titre', 'Titre']),
                self::fieldLine([0 => 'listefiches', 1 => $this->linkedFormId, 7 => 'Linked entries']),
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
            $this->aclService->delete($tag);
        }
        $this->createdTags = [];
        $this->formManager->delete($this->formId);
        $this->formManager->delete($this->linkedFormId);
    }

    private static function fieldLine(array $values): string
    {
        $slots = array_fill(0, 16, ' ');
        foreach ($values as $index => $value) {
            $slots[$index] = $value;
        }

        return implode('***', $slots) . '***';
    }

    public function testALinkedEntriesFieldReachesTheCustomTemplate()
    {
        $entry = $this->entryManager->create($this->formId, ['antispam' => 1, 'bf_titre' => 'Custom template entry']);
        $this->createdTags[] = $entry['id_fiche'];
        $this->aclService->save($entry['id_fiche'], 'read', '*');

        $controller = $this->wiki->services->get(EntryController::class);
        $method = new \ReflectionMethod($controller, 'getValuesForCustomTemplate');
        $values = $method->invoke($controller, $entry, $this->formManager->getOne($this->formId));

        $this->assertArrayHasKey('listefiches' . $this->linkedFormId, $values['html']);
        $this->assertArrayHasKey('bf_titre', $values['html']);
    }
}
