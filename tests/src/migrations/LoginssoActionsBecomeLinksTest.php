<?php

namespace YesWiki\Test\Core\Migrations;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Render\Service\MarkdownFormatterService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * loginsso's `{{linktouserprofil}}` and `{{userentries}}` become links to the account screens (spec section 11), in one new revision of each page using them.
 * Only the pages the test creates are walked.
 */
class LoginssoActionsBecomeLinksTest extends YesWikiTestCase
{
    private const MENU = 'TestLoginssoActionsMenu';

    private const ENTRIES = 'TestLoginssoActionsEntries';

    private const OTHER = 'TestLoginssoActionsOther';

    private const TAGS = [self::MENU, self::ENTRIES, self::OTHER];

    private DbService $db;

    private PageManager $pages;

    public static function setUpBeforeClass(): void
    {
        self::getWiki();
        require_once 'src/migrations/20261111100100_LoginssoActionsBecomeLinks.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $services = $this->getWiki()->services;
        $this->db = $services->get(DbService::class);
        $this->pages = $services->get(PageManager::class);
        $this->removePages();
    }

    protected function tearDown(): void
    {
        $this->removePages();
        parent::tearDown();
    }

    private function removePages(): void
    {
        foreach (self::TAGS as $tag) {
            $this->pages->deleteOrphaned($tag);
        }
    }

    private function profileLink(): string
    {
        return '[' . _t('SIGN_IN_UPGRADE_PROFILE_LINK') . '](user)';
    }

    private function entriesLink(): string
    {
        return '[' . _t('SIGN_IN_UPGRADE_ENTRIES_LINK') . '](user/entries)';
    }

    /** A page as Doryphore left it: an older revision, then the latest one. */
    private function page(string $tag, string $older, string $latest): void
    {
        $this->pages->save($tag, [PageBody::CONTENT => $older], '', true);
        $this->pages->save($tag, [PageBody::CONTENT => $latest], '', true);
    }

    /** @return list<string> the tags the migration rewrote */
    private function migrate(): array
    {
        $migration = new \LoginssoActionsBecomeLinks();
        $migration->setServices($this->getWiki()->services);
        $migration->setDbService($this->db);

        return $migration->rewrite($this->db, self::TAGS);
    }

    /** @return list<array{body: array<array-key, mixed>, latest: string, owner: string, type: string}> oldest first */
    private function revisions(string $tag): array
    {
        $rows = $this->db->loadAll(
            "SELECT body, latest, owner, {$this->db->quoteIdentifier('type')} AS type FROM {$this->db->prefixTable('pages')} WHERE tag = ? ORDER BY id ASC",
            [$tag]
        );

        return array_map(fn (array $row): array => [
            'body' => PageBody::decode((string)$row['body']),
            'latest' => (string)$row['latest'],
            'owner' => (string)$row['owner'],
            'type' => (string)$row['type'],
        ], $rows);
    }

    private function latestContent(string $tag): string
    {
        $revisions = $this->revisions($tag);

        return PageBody::content($revisions[count($revisions) - 1]['body']);
    }

    public function testLinkToProfileBecomesALink(): void
    {
        $this->page(self::MENU, 'older', "[Mes paramètres](ParametresUtilisateur)\n{{linktouserprofil dash=\"1\"}}");

        $this->assertSame([self::MENU], $this->migrate());

        $this->assertSame("[Mes paramètres](ParametresUtilisateur)\n" . $this->profileLink(), $this->latestContent(self::MENU));
    }

    public function testUserEntriesBecomesALink(): void
    {
        $this->page(self::ENTRIES, 'older', "# Mes fiches\n\n{{userentries}}\n\n{{ UserEntries template=\"liste_accordeon\" }}");

        $this->migrate();

        $this->assertSame("# Mes fiches\n\n" . $this->entriesLink() . "\n\n" . $this->entriesLink(), $this->latestContent(self::ENTRIES), 'spelt any way, with a template or not');
    }

    public function testOneRevisionIsAddedAndTheOlderOnesKeepWhatTheySaid(): void
    {
        $this->page(self::MENU, '{{linktouserprofil}} in an older revision', '{{linktouserprofil}}');
        $this->db->query("UPDATE {$this->db->prefixTable('pages')} SET owner = ? WHERE tag = ? AND latest = 'Y'", ['TestLoginssoActionsOwner', self::MENU]);
        $before = $this->revisions(self::MENU);

        $this->migrate();

        $after = $this->revisions(self::MENU);
        $this->assertCount(count($before) + 1, $after, 'exactly one revision is added');
        $previous = $before[count($before) - 1];
        $this->assertSame(array_slice($before, 0, -1), array_slice($after, 0, count($before) - 1), 'the older revisions are left as they were');
        $this->assertSame(array_replace($previous, ['latest' => 'N']), $after[count($before) - 1], 'the previous latest revision is kept, as history');
        $this->assertSame('{{linktouserprofil}} in an older revision', PageBody::content($after[0]['body']));
        $latest = $after[count($after) - 1];
        $this->assertSame('Y', $latest['latest']);
        $this->assertSame($this->profileLink(), PageBody::content($latest['body']));
        $this->assertSame('TestLoginssoActionsOwner', $latest['owner'], 'the page keeps its owner');
        $this->assertSame($previous['type'], $latest['type']);
        $this->assertCount(1, array_filter($after, fn (array $r): bool => $r['latest'] === 'Y'));
    }

    public function testOtherPagesAreUntouched(): void
    {
        $this->page(self::OTHER, 'older', "{{userentriesmenu}} and {{linktouserprofilx}}, which are not loginsso's, nor is userentries in plain text");
        $this->page(self::MENU, '{{linktouserprofil}}', 'no longer calls it');
        $before = [self::OTHER => $this->revisions(self::OTHER), self::MENU => $this->revisions(self::MENU)];

        $this->assertSame([], $this->migrate());

        $this->assertSame($before, [self::OTHER => $this->revisions(self::OTHER), self::MENU => $this->revisions(self::MENU)], 'only the latest revision counts, and only the two actions');
    }

    public function testRunningTwiceChangesNothing(): void
    {
        $this->page(self::MENU, 'older', '{{linktouserprofil}}');
        $this->page(self::ENTRIES, 'older', '{{userentries}}');
        $this->migrate();
        $once = [self::MENU => $this->revisions(self::MENU), self::ENTRIES => $this->revisions(self::ENTRIES)];

        $this->assertSame([], $this->migrate());

        $this->assertSame($once, [self::MENU => $this->revisions(self::MENU), self::ENTRIES => $this->revisions(self::ENTRIES)]);
    }

    public function testTheLinksReachTheAccountScreens(): void
    {
        $this->page(self::MENU, 'older', "{{linktouserprofil}}\n\n{{userentries}}");
        $this->migrate();

        $html = $this->getWiki()->services->get(MarkdownFormatterService::class)->format($this->latestContent(self::MENU));

        $this->assertMatchesRegularExpression('#<a href="[^"]*\?user"[^>]*>' . preg_quote(_t('SIGN_IN_UPGRADE_PROFILE_LINK'), '#') . '</a>#', $html);
        $this->assertMatchesRegularExpression('#<a href="[^"]*\?user/entries"[^>]*>' . preg_quote(_t('SIGN_IN_UPGRADE_ENTRIES_LINK'), '#') . '</a>#', $html);
        $this->assertStringNotContainsString('data-missing-tag', $html, 'a route is not a page waiting to be written');
    }
}
