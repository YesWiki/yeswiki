<?php

namespace YesWiki\Test\Content;

use Symfony\Component\HttpFoundation\Request;
use YesWiki\Content\Controller\EntryController;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Exception\TagAlreadyUsedException;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Anyone may create an entry, nobody may create one over a page that is already there (GHSA-4388-phmh-jrw8).
 *
 * Ported from doryphore-dev; the expected tags are ectoplasme's lowercase slugs rather than that branch's CamelCase wiki names.
 */
class EntryCreationOverwriteTest extends YesWikiTestCase
{
    private const FORM_ID = '999906';
    private const TARGET_TAG = 'bazar-overwrite-target-page';
    private const TARGET_TITLE = 'Bazar Overwrite Target Page';
    private const TARGET_BODY = 'untouched';

    private \YesWiki\Core\YesWikiRuntime $wiki;
    private EntryManager $entryManager;
    private FormManager $formManager;
    private PageManager $pageManager;
    private AclService $aclService;
    /** @var list<string> */
    private array $createdTags = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->wiki = $this->getWiki();
        $this->entryManager = $this->wiki->services->get(EntryManager::class);
        $this->formManager = $this->wiki->services->get(FormManager::class);
        $this->pageManager = $this->wiki->services->get(PageManager::class);
        $this->aclService = $this->wiki->services->get(AclService::class);

        $this->formManager->create([
            'id' => self::FORM_ID,
            'label' => 'Entry creation test form',
            'template' => '',
            'condition' => '',
        ]);

        $this->pageManager->save(self::TARGET_TAG, [PageBody::CONTENT => self::TARGET_BODY], '', true);
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
        parent::tearDown();
    }

    private function targetBody(): string
    {
        $page = $this->pageManager->getOne(self::TARGET_TAG, null, false, true);

        return PageBody::content($page['body'] ?? []);
    }

    public function testAnEntryCannotBeCreatedOverAnExistingPage(): void
    {
        try {
            $this->entryManager->create(self::FORM_ID, [
                'antispam' => 1,
                'bf_titre' => 'whatever',
                'tag' => self::TARGET_TAG,
            ]);
            $this->fail('creating an entry over ' . self::TARGET_TAG . ' should have been refused');
        } catch (TagAlreadyUsedException $e) {
            $this->assertStringContainsString(self::TARGET_TAG, $e->getMessage());
        }

        $this->assertSame(self::TARGET_BODY, $this->targetBody());
    }

    public function testATitleThatMapsOntoAnExistingPageGetsAnotherTag(): void
    {
        $entry = $this->entryManager->create(self::FORM_ID, [
            'antispam' => 1,
            'bf_titre' => self::TARGET_TITLE,
        ]);
        $this->createdTags[] = $entry['tag'];

        $this->assertSame(self::TARGET_TAG . '-2', $entry['tag']);
        $this->assertSame(self::TARGET_BODY, $this->targetBody());
    }

    public function testAnAnonymousVisitorStillCreatesAnEntry(): void
    {
        $entry = $this->entryManager->create(self::FORM_ID, [
            'antispam' => 1,
            'bf_titre' => 'Bazar Overwrite Brand New Entry',
        ]);
        $this->createdTags[] = $entry['tag'];

        $this->assertSame('bazar-overwrite-brand-new-entry', $entry['tag']);
        $this->assertTrue($this->entryManager->isEntry($entry['tag']));
    }

    public function testTheEntryFormIgnoresAPostedTag(): void
    {
        $currentRequest = $this->wiki->services->get(CurrentRequest::class);
        $before = $currentRequest->get();
        $currentRequest->replace(Request::create('/', 'POST', [
            'antispam' => 1,
            'valider' => 1,
            'bf_titre' => 'Bazar Overwrite Posted Tag',
            'tag' => self::TARGET_TAG,
        ]));
        $this->createdTags[] = 'bazar-overwrite-posted-tag';

        try {
            $this->wiki->services->get(EntryController::class)->create(self::FORM_ID);
        } catch (\Throwable $thrown) {
            // create() ends the request with a redirect once the entry is written
        } finally {
            $currentRequest->replace($before);
        }

        $this->assertSame(self::TARGET_BODY, $this->targetBody());
        $this->assertTrue($this->entryManager->isEntry('bazar-overwrite-posted-tag'));
    }
}
