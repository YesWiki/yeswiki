<?php

namespace YesWiki\Test\Search\Service;

use YesWiki\Content\Entity\ContentTypeSchema;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Search\Service\SearchManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Listing the rows of a form whose Content type is not `entry`. */
class ContentTypeSearchTest extends YesWikiTestCase
{
    private const PAGE_TAG = 'ContentTypeSearchRegressionPage';

    public static function tearDownAfterClass(): void
    {
        self::getWiki()->services->get(PageManager::class)->deleteOrphaned(self::PAGE_TAG);
    }

    /**
     * @return array<string, mixed>
     */
    private function builtInForm(string $contentType): array
    {
        $form = $this->getWiki()->services->get(FormManager::class)->getByContentType($contentType);
        $this->assertNotNull($form, "the {$contentType} form should exist -- run ./yeswicli migrate");

        return $form;
    }

    /**
     * @return array<string, mixed>
     */
    private function rowsOf(string $contentType): array
    {
        return $this->getWiki()->services->get(SearchManager::class)->search([
            'formsIds' => [$this->builtInForm($contentType)['id']],
        ]);
    }

    public function testAnOrdinaryWikiPageIsListedByThePageForm(): void
    {
        $wiki = $this->getWiki();
        $wiki->services->get(PageManager::class)->save(self::PAGE_TAG, [
            PageBody::CONTENT => 'du contenu',
            PageBody::TITLE => 'Une page trouvable',
        ], '', true);

        $rows = $this->rowsOf(ContentTypeSchema::TYPE_PAGE);

        $this->assertArrayHasKey(self::PAGE_TAG, $rows, 'a page must be listed by the Page form');
        $this->assertSame('Une page trouvable', $rows[self::PAGE_TAG]['title']);

        $this->assertSame(self::PAGE_TAG, $rows[self::PAGE_TAG]['tag']);
        $this->assertSame(
            (string)$this->builtInForm(ContentTypeSchema::TYPE_PAGE)['id'],
            (string)$rows[self::PAGE_TAG]['form_id'],
            'a page carries no form_id of its own: the search says which form describes it'
        );
    }

    public function testABazarEntryIsNotListedByThePageForm(): void
    {
        $wiki = $this->getWiki();
        $entryTags = array_keys($wiki->services->get(SearchManager::class)->search([]));
        if (empty($entryTags)) {
            $this->markTestSkipped('this wiki has no bazar entry to check against');
        }

        $pageRows = $this->rowsOf(ContentTypeSchema::TYPE_PAGE);

        $this->assertEmpty(
            array_intersect($entryTags, array_keys($pageRows)),
            'an entry is typed fiche_bazar, so it is not one of the untyped rows'
        );
    }

    public function testAnUnfilteredSearchStillMeansEveryBazarEntry(): void
    {
        $wiki = $this->getWiki();
        $entryManager = $wiki->services->get(EntryManager::class);

        $rows = $wiki->services->get(SearchManager::class)->search([]);
        $this->assertNotEmpty($rows, 'this wiki should have seeded bazar entries');

        foreach (array_keys($rows) as $tag) {
            $this->assertTrue($entryManager->isEntry($tag), "$tag came back from an unfiltered search but is not an entry");
        }
        $this->assertArrayNotHasKey(self::PAGE_TAG, $rows);
    }

    public function testEachBuiltInFormListsOnlyItsOwnRows(): void
    {
        $pages = array_keys($this->rowsOf(ContentTypeSchema::TYPE_PAGE));
        $users = array_keys($this->rowsOf(ContentTypeSchema::TYPE_USER));
        $files = array_keys($this->rowsOf(ContentTypeSchema::TYPE_FILE));

        $this->assertNotEmpty($users, 'a wiki always has at least one account');
        $this->assertEmpty(array_intersect($pages, $users));
        $this->assertEmpty(array_intersect($pages, $files));
        $this->assertEmpty(array_intersect($users, $files));
    }

    /** A form of an ordinary Content type still filters on its own id, not just its type. */
    public function testAnOrdinaryFormListsOnlyItsOwnEntries(): void
    {
        $wiki = $this->getWiki();
        $forms = $wiki->services->get(FormManager::class)->getAll();
        $ordinary = array_filter(
            $forms,
            fn ($form) => ContentTypeSchema::acceptsEntryOnlyProperties($form[ContentTypeSchema::CONTENT_TYPE] ?? null)
        );
        if (count($ordinary) < 2) {
            $this->markTestSkipped('needs at least two ordinary forms to tell their entries apart');
        }

        $searchManager = $wiki->services->get(SearchManager::class);
        foreach ($ordinary as $form) {
            foreach ($searchManager->search(['formsIds' => [$form['id']]]) as $tag => $entry) {
                $this->assertSame(
                    (string)$form['id'],
                    (string)$entry['form_id'],
                    "$tag came back for form {$form['id']} but belongs to form {$entry['form_id']}"
                );
            }
        }
    }
}
