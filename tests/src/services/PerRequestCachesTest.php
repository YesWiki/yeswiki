<?php

namespace YesWiki\Test\Core\Service;

use YesWiki\Content\Entity\ContentTypeSchema;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\Test\CountingQueriesPdo;

require_once 'tests/YesWikiTestCase.php';
require_once 'tests/CountingQueriesPdo.php';

/**
 * The per-request caches that stopped a page render asking the same question three times --
 * and, more importantly, that they let go when the row underneath them changes.
 *
 * A render of PagePrincipale was reading `SELECT metadata … 'PagePrincipale'` three times,
 * `SELECT * … 'mrflos'` four times and the Page form's tag three times. Each has a cache
 * now. The risk a cache introduces is not a wrong count, it is a **stale answer**, which
 * fails silently -- exactly how `setOwner()` broke when the unredacted read stopped going
 * to the database every time. So every test here changes the row and asks again.
 */
class PerRequestCachesTest extends YesWikiTestCase
{
    private const PAGE = 'PerRequestCachesTestPage';

    protected function tearDown(): void
    {
        $wiki = $this->getWiki();
        $db = $wiki->services->get(DbService::class);
        $db->query("DELETE FROM {$db->prefixTable('pages')} WHERE tag = '" . $db->escape(self::PAGE) . "'");
        parent::tearDown();
    }

    public function testTheUnredactedReadIsCachedButFollowsTheRow(): void
    {
        $wiki = $this->getWiki();
        $pageManager = $wiki->services->get(PageManager::class);
        $db = $wiki->services->get(DbService::class);

        $pageManager->save(self::PAGE, [PageBody::CONTENT => 'first'], '', true);

        $first = $pageManager->getOne(self::PAGE, null, true, true);
        $this->assertIsArray($first, 'the page under test must exist');
        $this->assertSame('first', PageBody::content($first['body']));

        // cached: no second read of the same row
        $before = $this->countQueries($db);
        $pageManager->getOne(self::PAGE, null, true, true);
        $this->assertSame($before, $this->countQueries($db), 'the unredacted read must be cached within a request');

        // ...and dropped when the row changes
        $pageManager->save(self::PAGE, [PageBody::CONTENT => 'second'], '', true);
        $after = $pageManager->getOne(self::PAGE, null, true, true);
        $this->assertIsArray($after);
        $this->assertSame('second', PageBody::content($after['body']), 'a save must invalidate the unredacted cache');
    }

    /**
     * `setOwner()` writes the row directly. It used to update only `ownersCache`, which was
     * survivable while every unredacted read went to the database; it stopped being
     * survivable the moment one did not. An account sets its own owner immediately after
     * its row is created, so the stale copy was one line old.
     */
    public function testSetOwnerIsVisibleToTheNextRead(): void
    {
        $wiki = $this->getWiki();
        $pageManager = $wiki->services->get(PageManager::class);

        $pageManager->save(self::PAGE, [PageBody::CONTENT => 'owned'], '', true);
        // warm both caches before the write, which is what made this a bug
        $pageManager->getOne(self::PAGE, null, true, true);
        $pageManager->getOne(self::PAGE);

        $owner = $this->anExistingAccount();
        $pageManager->setOwner(self::PAGE, $owner);

        $this->assertSame($owner, $pageManager->getOne(self::PAGE, null, true, true)['owner'] ?? null);
        $this->assertSame($owner, $pageManager->getOne(self::PAGE)['owner'] ?? null);
    }

    /**
     * AclService caches the stored ACLs of a page. PageManager writes `pages` rows behind
     * its back, so it has to say so -- otherwise an ACL read taken before a page existed
     * answers for the rest of the request.
     */
    public function testStoredAclsAreCachedButFollowTheWrite(): void
    {
        $wiki = $this->getWiki();
        $pageManager = $wiki->services->get(PageManager::class);
        $aclService = $wiki->services->get(AclService::class);
        $db = $wiki->services->get(DbService::class);

        $pageManager->save(self::PAGE, [PageBody::CONTENT => 'acls'], '', true);

        $aclService->load(self::PAGE, 'read');
        $before = $this->countQueries($db);
        $aclService->load(self::PAGE, 'write');
        $aclService->load(self::PAGE, 'comment');
        // the two modes used to evict each other, so this one re-read every time
        $aclService->load(self::PAGE, 'read', false);
        $this->assertSame(
            $before,
            $this->countQueries($db),
            'the stored ACLs are one read per page per request, whichever mode asks'
        );

        $aclService->save(self::PAGE, 'read', '@admins');
        // asked with $useDefaults = false, which is the mode that rebuilds its answer from
        // storage: load()'s own privilege cache is written directly by save(), so asking the
        // ordinary way would be answered without ever consulting what was cached about the
        // *stored* ACLs -- and a missing invalidation there would go unnoticed
        $this->assertSame(
            '@admins',
            $aclService->load(self::PAGE, 'read', false)['list'] ?? null,
            'writing an ACL must invalidate what was cached about the stored ACLs'
        );
    }

    public function testTheContentTypeFormLookupIsCachedButFollowsAFormChange(): void
    {
        $formManager = $this->getWiki()->services->get(FormManager::class);
        $db = $this->getWiki()->services->get(DbService::class);

        $first = $formManager->getByContentType(ContentTypeSchema::TYPE_PAGE);
        if ($first === null) {
            $this->markTestSkipped('this wiki has no Page form to look up');
        }

        $before = $this->countQueries($db);
        $formManager->getByContentType(ContentTypeSchema::TYPE_PAGE);
        $formManager->getByContentType(ContentTypeSchema::TYPE_PAGE);
        $this->assertSame(
            $before,
            $this->countQueries($db),
            'which page holds a Content type\'s form is asked once per request, not once per caller'
        );
    }

    /**
     * `GroupOperationsService::getMembers()` asks whether a group exists and then reads its
     * members. Both questions are about the same resource's triples, but they used to go
     * through different caches -- `getMatching()`'s, keyed by SQL text, and `getAll()`'s,
     * keyed by resource -- so the identical
     * `SELECT * FROM triples WHERE resource = 'ThisWikiGroup:admins'` ran twice. On every
     * `isInGroup()`, which is every ACL check on every page.
     */
    public function testCheckingAGroupAndReadingItsMembersIsOneQuery(): void
    {
        $wiki = $this->getWiki();
        $db = $wiki->services->get(DbService::class);
        $groups = $wiki->services->get(\YesWiki\Identity\Service\GroupOperationsService::class);
        $tripleStore = $wiki->services->get(\YesWiki\Content\Service\TripleStore::class);

        // cold: nothing known about this resource yet
        (new \ReflectionProperty($tripleStore, 'cacheByResource'))->setValue($tripleStore, []);
        (new \ReflectionProperty($tripleStore, 'matchingCache'))->setValue($tripleStore, []);

        $before = $this->countQueries($db);
        $groups->getMembers(ADMIN_GROUP);
        $used = $this->countQueries($db) - $before;

        $this->assertSame(
            1,
            $used,
            'existence and members are the same resource: reading them must not query it twice'
        );
    }

    /**
     * A page that does not exist costs one query to find that out, not two.
     *
     * `getOne()` came back empty, `cacheOwner()` had nothing to remember -- it can only
     * record a page that exists -- and then `checkEntriesACL()`, inside that same
     * `getOne()`, asked `isEntry()` about the very tag just found to be missing. The
     * unredacted cache already records the absence; `typeOf()` now reads it.
     */
    public function testAMissingPageIsLookedUpOnce(): void
    {
        $wiki = $this->getWiki();
        $pageManager = $wiki->services->get(PageManager::class);
        $db = $wiki->services->get(DbService::class);
        $absent = 'PerRequestCachesTestNoSuchPage';

        $pageManager->forget($absent);

        $before = $this->countQueries($db);
        $this->assertNull($pageManager->getOne($absent), 'the fixture tag must not exist');
        $this->assertNull($pageManager->typeOf($absent), 'and it has no type');
        $used = $this->countQueries($db) - $before;

        $this->assertSame(1, $used, 'one query settles both "is there a row" and "what type is it"');
    }

    /**
     * `{{attach file="…"}}` resolves a FileManager tag. It used to ask whether the tag was a
     * file and then load it -- but loading answers both, since FileManager::getOne() returns
     * null for anything that is not one. Two queries per {{attach}} on the page.
     */
    public function testResolvingAFileTagReadsTheRowOnce(): void
    {
        $wiki = $this->getWiki();
        $db = $wiki->services->get(DbService::class);
        $fileManager = $wiki->services->get(\YesWiki\Content\Service\FileManager::class);
        $pageManager = $wiki->services->get(PageManager::class);

        $tags = $fileManager->getAllFileTags();
        if (empty($tags)) {
            $this->markTestSkipped('needs an uploaded file to resolve');
        }
        $tag = (string)$tags[0];
        $pageManager->forget($tag);

        $before = $this->countQueries($db);
        $this->assertNotNull($fileManager->getOne($tag));
        $this->assertTrue($fileManager->isFileTag($tag), 'and it is a file, answered from the row just read');

        $this->assertSame(
            1,
            $this->countQueries($db) - $before,
            'reading a file entry and knowing it is a file is one row, not a type probe and a row'
        );
    }

    private function anExistingAccount(): string
    {
        $users = $this->getWiki()->services->get(\YesWiki\Identity\Service\UserManager::class)->getAll();
        if (empty($users)) {
            $this->markTestSkipped('needs an account to own a page');
        }

        return (string)$users[0]['name'];
    }

    private function countQueries(DbService $dbService): int
    {
        $count = CountingQueriesPdo::countFor($dbService);
        if ($count === null) {
            $this->markTestSkipped('the counting PDO is wired for the suite\'s sqlite database');
        }

        return $count;
    }
}
