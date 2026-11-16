<?php

namespace YesWiki\Test\Content;

use PHPUnit\Framework\Attributes\CoversClass;
use YesWiki\Admin\Message\PurgeRevisions;
use YesWiki\Admin\Message\PurgeRevisionsHandler;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Entity\PageType;
use YesWiki\Content\Exception\TrashedTagException;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Content\Service\Trash;
use YesWiki\Identity\Service\GroupManager;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\TripleStore;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** End-users 13: deleted content goes to a trash with every revision, and comes back as it was. */
#[CoversClass(Trash::class)]
#[CoversClass(PageManager::class)]
class TrashTest extends YesWikiTestCase
{
    private const PAGE = 'TrashTestPage';
    private const FORM_ID = '999912';
    private const PROPERTY = 'http://outils-reseaux.org/_vocabulary/trashtest';

    /** @var list<string> */
    private array $made = [];

    private string $admin = '';

    protected function setUp(): void
    {
        parent::setUp();
        $admins = self::getWiki()->services->get(GroupManager::class)->getMembers('admins');
        if ($admins === []) {
            $this->markTestSkipped('no admin account in the test wiki');
        }
        $this->admin = (string)$admins[0];
        self::actAs($this->admin);
    }

    protected function tearDown(): void
    {
        $services = self::getWiki()->services;
        $trash = $services->get(Trash::class);
        foreach (array_reverse($this->made) as $tag) {
            if ($trash->record($tag) !== null) {
                $trash->erase($tag);
            }
            $services->get(PageManager::class)->deleteOrphaned($tag);
        }
        $services->get(FormManager::class)->delete(self::FORM_ID);
        self::actAs(null);
        parent::tearDown();
    }

    public function testAPageGoesWithItsRevisionsCommentsAndTriplesAndComesBackTheSame(): void
    {
        $services = self::getWiki()->services;
        $pages = $services->get(PageManager::class);
        $trash = $services->get(Trash::class);
        foreach (['un', 'deux', 'trois'] as $text) {
            $pages->save(self::PAGE, [PageBody::CONTENT => $text, PageBody::KEYWORDS => ['corbeille']], '', true);
        }
        $comments = [self::PAGE . 'Comment1', self::PAGE . 'Comment2'];
        foreach ($comments as $comment) {
            $pages->save($comment, [PageBody::CONTENT => 'un avis'], self::PAGE, true, null, PageType::COMMENT);
        }
        $this->made = [self::PAGE, ...$comments];
        $services->get(TripleStore::class)->create(self::PAGE, self::PROPERTY, 'gardé', '', '');
        $revisions = array_column($pages->getRevisions(self::PAGE), 'id');
        $this->assertCount(3, $revisions);

        $this->assertTrue($trash->trash(self::PAGE));

        $this->assertNull($pages->getOne(self::PAGE, null, false));
        $this->assertNull($pages->getOne(self::PAGE, (int)$revisions[1], false, true), 'an old revision is no one\'s to read');
        $this->assertSame([], $pages->getRevisions(self::PAGE));
        $this->assertSame(0, $pages->countRevisions(self::PAGE));
        $this->assertNull($pages->getOne($comments[0], null, false, true));
        $this->assertTrue($pages->tagExists(self::PAGE), 'the tag stays taken');
        $this->assertNull($services->get(TripleStore::class)->getOne(self::PAGE, self::PROPERTY, '', ''));
        $this->assertSame([self::PAGE], array_column(array_filter($trash->list(), fn (array $item): bool => $item['tag'] === self::PAGE), 'tag'));

        try {
            $pages->save(self::PAGE, [PageBody::CONTENT => 'par-dessus'], '', true);
            $this->fail('saving a trashed tag is refused');
        } catch (TrashedTagException $refused) {
            $this->assertStringContainsString(self::PAGE, $refused->getMessage());
        }

        $this->assertTrue($trash->restore(self::PAGE));

        $page = $pages->getOne(self::PAGE, null, false, true);
        $this->assertSame('trois', PageBody::content($page['body'] ?? []));
        $this->assertSame(['corbeille'], $page['body'][PageBody::KEYWORDS] ?? null);
        $this->assertSame($revisions, array_column($pages->getRevisions(self::PAGE), 'id'), 'the same three revisions');
        $this->assertNotNull($pages->getOne($comments[1], null, false, true));
        $this->assertSame('gardé', $services->get(TripleStore::class)->getOne(self::PAGE, self::PROPERTY, '', ''));
        $this->assertNull($trash->record(self::PAGE));
    }

    public function testAFormGoesAsOneItemWithItsEntriesAndComesBackWithThem(): void
    {
        $services = self::getWiki()->services;
        $forms = $services->get(FormManager::class);
        $entries = $services->get(EntryManager::class);
        $trash = $services->get(Trash::class);
        $forms->create([
            'id' => self::FORM_ID,
            'label' => 'Trash test form',
            'template' => json_encode([['type' => 'texte', 'name' => 'bf_titre', 'label' => 'Titre']]),
        ]);
        $tags = [];
        foreach (['Une', 'Deux', 'Trois'] as $title) {
            $tags[] = $entries->create(self::FORM_ID, ['antispam' => 1, 'bf_titre' => "Fiche {$title} corbeille"])['tag'];
        }
        $formTag = (string)($forms->getOne(self::FORM_ID)['tag'] ?? '');
        $this->made = [...$tags, $formTag];

        $forms->delete(self::FORM_ID, true);

        $items = array_values(array_filter($trash->list(), fn (array $item): bool => $item['tag'] === $formTag));
        $this->assertCount(1, $items, 'one item');
        $this->assertEqualsCanonicalizing($tags, $items[0]['with']);
        $this->assertSame([], array_filter($trash->list(), fn (array $item): bool => in_array($item['tag'], $tags, true)), 'the entries are not items of their own');
        $this->assertFalse($entries->isEntry($tags[0]));
        $this->assertFalse($trash->restore($tags[0]), 'an entry that came along comes back with its form only');

        $this->assertTrue($trash->restore($formTag));

        $this->assertNotNull($forms->getOne(self::FORM_ID));
        foreach ($tags as $tag) {
            $this->assertTrue($entries->isEntry($tag), $tag);
        }
    }

    public function testTheRevisionPurgeLeavesATrashedPageAloneAndTheTrashPurgeErasesAnOldOne(): void
    {
        $services = self::getWiki()->services;
        $db = $services->get(DbService::class);
        $pages = $services->get(PageManager::class);
        $trash = $services->get(Trash::class);
        foreach (['un', 'deux', 'trois'] as $text) {
            $pages->save(self::PAGE, [PageBody::CONTENT => $text], '', true);
        }
        $this->made = [self::PAGE];
        $db->query('UPDATE ' . $db->prefixTable('pages') . " SET time = '2020-01-01 10:00:00' WHERE tag = ?", [self::PAGE]);
        $trash->trash(self::PAGE);

        $config = $this->createStub(RuntimeConfig::class);
        $config->method('getValue')->willReturn(30);
        (new PurgeRevisionsHandler($config, $db, $services->get(HibernationService::class)))(new PurgeRevisions());
        $this->assertSame(3, $db->countRows('SELECT * FROM ' . $db->prefixTable('pages') . ' WHERE tag = ?', [self::PAGE]));

        $record = (array)$trash->record(self::PAGE);
        $record['at'] = date('Y-m-d H:i:s', time() - 31 * 86400);
        $triples = $services->get(TripleStore::class);
        $triples->delete(self::PAGE, PageManager::TRASH_PROPERTY, null, '', '');
        $triples->create(self::PAGE, PageManager::TRASH_PROPERTY, (string)json_encode($record), '', '');

        $this->assertSame(0, $trash->purgeOlderThan(40));
        $this->assertGreaterThanOrEqual(1, $trash->purgeOlderThan(30));
        $this->assertFalse($pages->tagExists(self::PAGE), 'erased for good');
    }

    public function testOnlyAnAdminOrWhoeverDeletedItMayHandleIt(): void
    {
        $services = self::getWiki()->services;
        $services->get(PageManager::class)->save(self::PAGE, [PageBody::CONTENT => 'un'], '', true);
        $this->made = [self::PAGE];
        $trash = $services->get(Trash::class);
        $trash->trash(self::PAGE);

        $this->assertTrue($trash->mayHandle(self::PAGE));
        self::actAs(null);
        $this->assertFalse($trash->mayHandle(self::PAGE));
        self::actAs($this->admin);
    }
}
