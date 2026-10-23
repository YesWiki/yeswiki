<?php

namespace YesWiki\Test\Bazar\Controller;

use Tamtamchik\SimpleFlash\Flash;
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
 * Saving an entry leaves a message for the next page, and only sends the visitor back to a page of this wiki.
 */
class EntryControllerSavedMessageTest extends YesWikiTestCase
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
        Flash::clear();

        $this->formId = $this->formManager->create([
            'bn_label_nature' => 'Saved message test form',
            'bn_template' => 'texte***bf_titre***Titre***60***255*** *** ***text***1*** *** *** * *** * *** *** *** ***',
            'bn_condition' => '',
        ]);
    }

    protected function tearDown(): void
    {
        Flash::clear();
        $this->wiki->request->request->replace([]);
        $this->wiki->request->query->remove('incomingurl');
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

    private function submit(string $title, string $tag): void
    {
        $this->wiki->request->request->replace(['bf_titre' => $title] + self::validBotGuardFields($this->wiki));
        $this->createdTags[] = $tag;
        try {
            $this->wiki->services->get(EntryController::class)->create($this->formId);
        } catch (ExitException $e) {
        } finally {
            self::restoreBotGuard($this->wiki);
        }
    }

    public function testACreatedEntryLeavesAMessageWithALinkBackToTheForm()
    {
        $this->submit('Saved message entry', 'SavedMessageEntry');

        $this->assertTrue($this->entryManager->isEntry('SavedMessageEntry'));
        $this->assertTrue(Flash::some('success'));
        $message = Flash::display('success');
        $this->assertStringContainsString(_t('BAZ_FICHE_ENREGISTREE'), $message);
        $this->assertStringContainsString('vue=saisir', $message);
        $this->assertStringContainsString('id=' . $this->formId, $message);
    }

    public function testAnIncomingUrlStillGetsTheMessage()
    {
        $this->wiki->request->query->set('incomingurl', $this->wiki->href('', 'PagePrincipale', null, false));
        $this->submit('Saved message incoming', 'SavedMessageIncoming');

        $this->assertTrue($this->entryManager->isEntry('SavedMessageIncoming'));
        $this->assertTrue(Flash::some('success'));
    }

    public function testAnIncomingUrlOnTheWikiIsKept()
    {
        $url = $this->wiki->href('', 'PagePrincipale', ['course' => 'a', 'module' => 'b'], false);
        $this->wiki->request->query->set('incomingurl', $url);

        $this->assertSame($url, $this->wiki->services->get(EntryController::class)->getIncomingUrl());
    }

    public function testAnIncomingUrlOutsideTheWikiIsIgnored()
    {
        $this->wiki->request->query->set('incomingurl', 'https://elsewhere.example.org/?PagePrincipale');

        $this->assertSame('', $this->wiki->services->get(EntryController::class)->getIncomingUrl());
    }
}
