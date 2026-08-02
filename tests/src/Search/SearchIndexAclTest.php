<?php

namespace YesWiki\Test\Search;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Search\Service\SearchIndexer;
use YesWiki\Search\Service\SearchIndexQuery;
use YesWiki\Search\Service\SearchIndexSchema;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Access control over the search index (ticket 18 / ADR-0015).
 *
 * Two mechanisms, deliberately different, and both of them entirely in SQL -- which is the
 * whole reason the index lives in the wiki's own database rather than in a sidecar engine:
 *
 * - **Page-level** ACL is denormalised onto the index row and filtered with the same
 *   predicate `pages` is filtered with.
 * - **Field-level** ACL groups a Content's text into one row per distinct expression, and
 *   the query matches only the buckets the searcher passes.
 *
 * The load-bearing invariant is in testAnAclChangeCreatesARevision: `page_read_acl` is only
 * safe to denormalise because every ACL write forges a new `pages` revision, so `page.updated`
 * fires and the row is rewritten. A future write path that changed an ACL without
 * revisioning would leave the index authoritative and wrong, and nothing else would notice.
 */
class SearchIndexAclTest extends YesWikiTestCase
{
    private const PUBLIC_TAG = 'SearchIndexAclPublicPage';
    private const PRIVATE_TAG = 'SearchIndexAclPrivatePage';

    protected function setUp(): void
    {
        parent::setUp();
        if (!$this->getWiki()->services->get(SearchIndexSchema::class)->exists()) {
            $this->markTestSkipped('no search index on this wiki -- run ./yeswicli migrate');
        }
        $this->logout();
        $this->removeFixtures();
    }

    protected function tearDown(): void
    {
        $this->logout();
        parent::tearDown();
    }

    public static function tearDownAfterClass(): void
    {
        $wiki = self::getWiki();
        foreach ([self::PUBLIC_TAG, self::PRIVATE_TAG] as $tag) {
            $wiki->services->get(PageManager::class)->deleteOrphaned($tag);
            $wiki->services->get(SearchIndexer::class)->delete($tag);
        }
    }

    private function removeFixtures(): void
    {
        $wiki = $this->getWiki();
        foreach ([self::PUBLIC_TAG, self::PRIVATE_TAG] as $tag) {
            $wiki->services->get(PageManager::class)->deleteOrphaned($tag);
            $wiki->services->get(SearchIndexer::class)->delete($tag);
        }
    }

    private function logout(): void
    {
        $this->getWiki()->services->get(AuthenticationService::class)->logout();
    }

    private function loginAsAdmin(): string
    {
        $wiki = $this->getWiki();
        $aclService = $wiki->services->get(AclService::class);
        $admin = current(array_filter(
            $wiki->services->get(UserManager::class)->getAll(),
            fn ($user) => $aclService->isAdmin($user['name'])
        ));
        $this->assertNotFalse($admin, 'need an existing admin on this wiki');
        $wiki->services->get(AuthenticationService::class)->login($admin);

        return (string)$admin['name'];
    }

    private function savePage(string $tag, string $content, ?string $readAcl = null): void
    {
        $wiki = $this->getWiki();
        $wiki->services->get(PageManager::class)->save($tag, [
            PageBody::TITLE => $tag,
            PageBody::CONTENT => $content,
        ], '', true);
        if ($readAcl !== null) {
            $wiki->services->get(AclService::class)->save($tag, 'read', $readAcl);
        }
        $wiki->services->get(SearchIndexer::class)->index($tag);
    }

    /**
     * A fresh query service per call: it caches the readable ACL set for one request, and
     * these tests change who is logged in between calls.
     *
     * @return array{results: list<array<string, string>>, total: int}
     */
    private function search(string $phrase): array
    {
        $wiki = $this->getWiki();

        return (new SearchIndexQuery(
            $wiki->services->get(DbService::class),
            $wiki->services->get(AclService::class),
            $wiki->services->get(SearchIndexSchema::class),
            $wiki->services->get(\YesWiki\Search\Service\FormOptionTranslator::class),
        ))->search($phrase, null, 50);
    }

    public function testAPublicPageIsFoundByAnAnonymousVisitor(): void
    {
        $this->savePage(self::PUBLIC_TAG, 'un texte sur la mache publique');

        $this->assertSame(1, $this->search('mache')['total']);
    }

    /**
     * The page's text IS in the index -- only the query hides it. That is the deliberate
     * asymmetry of ADR-0015: private *pages* stay fully searchable by those entitled to
     * them, unlike private *fields*, which never enter the index at all.
     */
    public function testAPrivatePageIsHiddenFromAnAnonymousVisitorButIndexed(): void
    {
        $this->savePage(self::PRIVATE_TAG, 'un texte sur le cresson confidentiel', '@admins');

        $this->assertSame(0, $this->search('cresson')['total'], 'an anonymous visitor must not see it');

        $wiki = $this->getWiki();
        $rows = $wiki->services->get(DbService::class)->loadAll(
            'SELECT tag FROM ' . $wiki->services->get(SearchIndexSchema::class)->table()
            . " WHERE tag = '" . self::PRIVATE_TAG . "'"
        );
        $this->assertNotEmpty($rows, 'but the row is there -- only the query filtered it out');
    }

    public function testAnAdminFindsThePrivatePage(): void
    {
        $this->savePage(self::PRIVATE_TAG, 'un texte sur le cresson confidentiel', '@admins');

        $this->assertSame(0, $this->search('cresson')['total']);

        $this->loginAsAdmin();

        $this->assertSame(
            1,
            $this->search('cresson')['total'],
            'page-level ACL filters in SQL, so an entitled reader sees the page'
        );
    }

    /**
     * THE invariant `page_read_acl` rests on.
     *
     * Denormalising the read ACL onto the index is only correct because changing an ACL
     * creates a `pages` revision, which fires `page.updated`, which rewrites the row. If a
     * future write path ever mutates an ACL in place instead, this test is what says so --
     * everything else would keep passing while search quietly served the old permissions.
     */
    public function testAnAclChangeCreatesARevision(): void
    {
        $wiki = $this->getWiki();
        $db = $wiki->services->get(DbService::class);
        $pages = $db->prefixTable('pages');

        $this->savePage(self::PUBLIC_TAG, 'un texte quelconque');
        $before = (int)$db->scalar(
            "SELECT COUNT(*) FROM {$pages} WHERE tag = '" . self::PUBLIC_TAG . "'",
            0
        );

        $wiki->services->get(AclService::class)->save(self::PUBLIC_TAG, 'read', '@admins');

        $after = (int)$db->scalar(
            "SELECT COUNT(*) FROM {$pages} WHERE tag = '" . self::PUBLIC_TAG . "'",
            0
        );

        $this->assertGreaterThan(
            $before,
            $after,
            'changing an ACL must create a revision -- the search index denormalises the read ACL '
            . 'and relies on page.updated firing to rewrite it (ADR-0015)'
        );
    }

    /** And the consequence: tightening an ACL takes the page out of anonymous results. */
    public function testTighteningAnAclRemovesThePageFromAnonymousResults(): void
    {
        // the read ACL is set explicitly rather than left to the default: AclService caches
        // per tag for the life of the request, and an earlier test in this class made this
        // tag private -- deleting the page does not clear that cache, so a recreated page
        // would silently inherit the restriction
        $this->savePage(self::PUBLIC_TAG, 'un texte sur le raifort', '*');
        $this->assertSame(1, $this->search('raifort')['total']);

        $wiki = $this->getWiki();
        $wiki->services->get(AclService::class)->save(self::PUBLIC_TAG, 'read', '@admins');
        $wiki->services->get(SearchIndexer::class)->index(self::PUBLIC_TAG);

        $this->assertSame(
            0,
            $this->search('raifort')['total'],
            'the denormalised page_read_acl must have followed the ACL change'
        );
    }

    /**
     * Field-level ACL: text guarded by an expression this visitor fails is in its own bucket
     * row, and neither the results nor the excerpt may quote it back at them.
     */
    public function testRestrictedFieldTextIsBucketedAndHiddenFromAnonymous(): void
    {
        $wiki = $this->getWiki();
        $db = $wiki->services->get(DbService::class);
        $schema = $wiki->services->get(SearchIndexSchema::class);

        // written straight into the index: building a form with a restricted field would
        // test FormManager, and what is under test here is the bucket filter
        $db->query(
            "INSERT INTO {$schema->table()}"
            . ' (tag, acl, acl_hash, page_read_acl, owner, content_type, form_id, title, text, updated_at)'
            . " VALUES ('" . self::PRIVATE_TAG . "', '', '" . md5('') . "', '*', '', 'entry', '',"
            . " 'Une fiche', 'partie publique topinambour', '2026-01-01 00:00:00'),"
            . " ('" . self::PRIVATE_TAG . "', '@admins', '" . md5('@admins') . "', '*', '', 'entry', '',"
            . " 'Une fiche', 'partie reservee salsifis', '2026-01-01 00:00:00')"
        );

        $this->assertSame(1, $this->search('topinambour')['total'], 'the public bucket is searchable');
        $this->assertSame(0, $this->search('salsifis')['total'], 'the restricted bucket is not');

        $this->loginAsAdmin();

        $this->assertSame(
            1,
            $this->search('salsifis')['total'],
            'an entitled reader searches the restricted bucket too -- that is why buckets exist '
            . 'rather than simply omitting restricted text'
        );
    }

    /** An excerpt is built from the index, so it inherits the same bucket filter. */
    public function testAnExcerptNeverQuotesARestrictedField(): void
    {
        $wiki = $this->getWiki();
        $db = $wiki->services->get(DbService::class);
        $schema = $wiki->services->get(SearchIndexSchema::class);

        $db->query(
            "INSERT INTO {$schema->table()}"
            . ' (tag, acl, acl_hash, page_read_acl, owner, content_type, form_id, title, text, updated_at)'
            . " VALUES ('" . self::PRIVATE_TAG . "', '', '" . md5('') . "', '*', '', 'entry', '',"
            . " 'Une fiche', 'partie publique', '2026-01-01 00:00:00'),"
            . " ('" . self::PRIVATE_TAG . "', '@admins', '" . md5('@admins') . "', '*', '', 'entry', '',"
            . " 'Une fiche', 'numero de telephone prive', '2026-01-01 00:00:00')"
        );

        $text = $wiki->services->get(SearchIndexQuery::class)->textFor(self::PRIVATE_TAG);

        $this->assertStringContainsString('partie publique', $text);
        $this->assertStringNotContainsString('telephone', $text);
    }
}
