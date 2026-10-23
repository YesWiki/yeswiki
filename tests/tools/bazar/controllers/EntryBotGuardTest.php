<?php

namespace YesWiki\Test\Bazar\Controller;

use YesWiki\Bazar\Controller\EntryController;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\Exception\ExitException;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * The anti-bot guard decides on each submission, and its fields never end up in the entry.
 */
class EntryBotGuardTest extends YesWikiTestCase
{
    private $wiki;
    private $entryManager;
    private $formManager;
    private string $formId;
    private array $createdTags = [];

    protected function setUp(): void
    {
        $this->wiki = $this->getWiki();
        $GLOBALS['wiki'] = $this->wiki;
        $this->entryManager = $this->wiki->services->get(EntryManager::class);
        $this->formManager = $this->wiki->services->get(FormManager::class);
        $this->wiki->services->get(AuthController::class)->logout();

        $this->formId = $this->formManager->create([
            'bn_label_nature' => 'Entry bot guard test form',
            'bn_template' => 'texte***bf_titre***Titre***60***255*** *** ***text***1*** *** *** * *** * *** *** *** ***',
            'bn_condition' => '',
        ]);
    }

    protected function tearDown(): void
    {
        self::restoreBotGuard($this->wiki);
        $this->wiki->request->request->replace([]);
        foreach ($this->createdTags as $tag) {
            if ($this->entryManager->isEntry($tag)) {
                $this->entryManager->delete($tag, true);
            }
            $this->wiki->services->get(PageManager::class)->deleteOrphaned($tag);
            $this->wiki->services->get(AclService::class)->delete($tag);
        }
        $this->createdTags = [];
        $this->formManager->delete($this->formId);
    }

    private function submit(array $data, string $tag): void
    {
        $this->wiki->request->request->replace($data);
        $this->createdTags[] = $tag;
        try {
            $this->wiki->services->get(EntryController::class)->create($this->formId);
        } catch (ExitException $e) {
        }
    }

    public function testTheGuardFieldsAreNotSavedInTheEntry()
    {
        $guardFields = self::validBotGuardFields($this->wiki);
        $this->submit(['bf_titre' => 'Entry bot guard saved'] + $guardFields, 'EntryBotGuardSaved');

        $this->assertTrue($this->entryManager->isEntry('EntryBotGuardSaved'));
        $body = json_decode($this->wiki->services->get(PageManager::class)->getOne('EntryBotGuardSaved', null, false, true)['body'], true);
        $this->assertSame([], array_values(array_intersect(array_keys($guardFields), array_keys($body))));
    }

    public function testARefusedSubmissionDoesNotDecideForTheNextOne()
    {
        $this->submit(['bf_titre' => 'Entry bot guard refused'], 'EntryBotGuardRefused');
        $this->submit(['bf_titre' => 'Entry bot guard accepted'] + self::validBotGuardFields($this->wiki), 'EntryBotGuardAccepted');

        $this->assertFalse($this->entryManager->isEntry('EntryBotGuardRefused'));
        $this->assertTrue($this->entryManager->isEntry('EntryBotGuardAccepted'));
    }
}
