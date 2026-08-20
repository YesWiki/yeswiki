<?php

namespace YesWiki\Test\Bazar\Service;

use YesWiki\Bazar\Controller\EntryController;
use YesWiki\Bazar\Exception\TagAlreadyUsedException;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\Exception\ExitException;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Anyone may create an entry, nobody may create one over a page that is already there.
 */
class EntryCreationOverwriteTest extends YesWikiTestCase
{
    private const FORM_ID = '999905';
    private const TARGET_TAG = 'BazarOverwriteTargetPage';
    private const TARGET_BODY = 'untouched';

    private $wiki;
    private $entryManager;
    private $formManager;
    private $pageManager;
    private $aclService;
    private $createdTags = [];

    protected function setUp(): void
    {
        $this->wiki = $this->getWiki();
        $GLOBALS['wiki'] = $this->wiki;
        $this->entryManager = $this->wiki->services->get(EntryManager::class);
        $this->formManager = $this->wiki->services->get(FormManager::class);
        $this->pageManager = $this->wiki->services->get(PageManager::class);
        $this->aclService = $this->wiki->services->get(AclService::class);

        $this->formManager->create([
            'bn_id_nature' => self::FORM_ID,
            'bn_label_nature' => 'Entry creation test form',
            'bn_template' => '',
            'bn_condition' => '',
        ]);

        $this->pageManager->save(self::TARGET_TAG, self::TARGET_BODY, '', true);
        $this->aclService->save(self::TARGET_TAG, 'write', '@admins');

        unset($_SESSION['user']);
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
        $this->pageManager->deleteOrphaned(self::TARGET_TAG);
        $this->aclService->delete(self::TARGET_TAG);
        $this->formManager->delete(self::FORM_ID);
    }

    public function testAnEntryCannotBeCreatedOverAnExistingPage()
    {
        try {
            $this->entryManager->create(self::FORM_ID, [
                'antispam' => 1,
                'bf_titre' => 'whatever',
                'id_fiche' => self::TARGET_TAG,
            ]);
            $this->fail('creating an entry over ' . self::TARGET_TAG . ' should have been refused');
        } catch (TagAlreadyUsedException $e) {
            $this->assertStringContainsString(self::TARGET_TAG, $e->getMessage());
        }

        $this->assertSame(self::TARGET_BODY, $this->pageManager->getOne(self::TARGET_TAG, null, false, true)['body']);
    }

    public function testATitleThatMapsOntoAnExistingPageGetsAnotherTag()
    {
        $entry = $this->entryManager->create(self::FORM_ID, [
            'antispam' => 1,
            'bf_titre' => 'Bazar Overwrite Target Page',
        ]);
        $this->createdTags[] = $entry['id_fiche'];

        $this->assertSame(self::TARGET_TAG . '2', $entry['id_fiche']);
        $this->assertSame(self::TARGET_BODY, $this->pageManager->getOne(self::TARGET_TAG, null, false, true)['body']);
    }

    public function testAnAnonymousVisitorStillCreatesAnEntry()
    {
        $entry = $this->entryManager->create(self::FORM_ID, [
            'antispam' => 1,
            'bf_titre' => 'Bazar Overwrite Brand New Entry',
        ]);
        $this->createdTags[] = $entry['id_fiche'];

        $this->assertSame('BazarOverwriteBrandNewEntry', $entry['id_fiche']);
        $this->assertTrue($this->entryManager->isEntry($entry['id_fiche']));
    }

    public function testTheEntryFormIgnoresAPostedTag()
    {
        $this->wiki->request->request->replace([
            'antispam' => 1,
            'bf_titre' => 'Bazar Overwrite Posted Tag',
            'id_fiche' => self::TARGET_TAG,
        ]);
        $this->createdTags[] = 'BazarOverwritePostedTag';

        try {
            $this->wiki->services->get(EntryController::class)->create(self::FORM_ID);
        } catch (ExitException $e) {
        } finally {
            $this->wiki->request->request->replace([]);
        }

        $this->assertSame(self::TARGET_BODY, $this->pageManager->getOne(self::TARGET_TAG, null, false, true)['body']);
        $this->assertTrue($this->entryManager->isEntry('BazarOverwritePostedTag'));
    }
}
