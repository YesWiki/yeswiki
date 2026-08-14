<?php

namespace YesWiki\Test\Content;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Entity\PageType;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Every Content says when it was created and when it was last changed.
 *
 * `created_at` and `updated_at` read like fields of a form, and the settings rail offers
 * them beside a form's own (`Setting::extraFields`) -- but only an entry saved through
 * `EntryManager::create()` has ever carried them in its body. A page, an account and a file
 * do not, so **a list of files mapped onto either of them showed nothing at all**: no
 * subtitle, no badge, and no date on the card, since the Item's date comes from the same
 * place (reported on `{{entrylist id="7" template="card" displayfields="…,subtitle=updated_at"}}`).
 *
 * The row is where the answer lives for those: the revision being read is the current one,
 * and the oldest revision is the creation. The body still wins where it has a value -- it
 * is written in PHP's timezone and `time` in the database's, and swapping which one is
 * displayed would shift every entry's dates by that offset.
 */
class ContentDatesTest extends YesWikiTestCase
{
    private const TAG = 'ContentDatesTestPage';

    protected function setUp(): void
    {
        parent::setUp();
        $this->removeFixture();
    }

    public static function tearDownAfterClass(): void
    {
        self::getWiki()->services->get(PageManager::class)->deleteOrphaned(self::TAG);
    }

    private function removeFixture(): void
    {
        $this->getWiki()->services->get(PageManager::class)->deleteOrphaned(self::TAG);
    }

    /** A Content whose body says nothing about dates, which is every page ever saved. */
    private function savePageContent(): void
    {
        $this->getWiki()->services->get(PageManager::class)->save(
            self::TAG,
            [
                PageBody::TITLE => 'Content dates',
                PageBody::CONTENT => 'du texte',
            ],
            '',
            true,
            null,
            PageType::PAGE
        );
    }

    /** @return array<string, mixed>|null */
    private function listed(): ?array
    {
        $form = $this->getWiki()->services->get(FormManager::class)->getByContentType(PageType::PAGE);
        $this->assertNotNull($form, 'the Pages form must exist -- ADR-0011');

        $entries = $this->getWiki()->services->get(EntryManager::class)->search([
            'formsIds' => [$form['id']],
        ]);

        return $entries[self::TAG] ?? null;
    }

    public function testAPageListedAsAnEntryCarriesBothDates(): void
    {
        $this->savePageContent();

        $listed = $this->listed();
        $this->assertNotNull($listed, self::TAG . ' is listed by the Pages form');
        $this->assertNotEmpty(
            $listed['updated_at'] ?? null,
            'a Content that records no updated_at is still one that was last changed'
        );
        $this->assertNotEmpty(
            $listed['created_at'] ?? null,
            'and one that appeared at some point'
        );
    }

    /** What it says is the row's own stamps, not today's date or an empty string. */
    public function testTheDatesAreTheRowsOwn(): void
    {
        $this->savePageContent();
        $page = $this->getWiki()->services->get(PageManager::class)->getOne(self::TAG);
        $this->assertNotNull($page);

        $listed = $this->listed();

        $this->assertSame($page['time'], $listed['updated_at'] ?? null);
        $this->assertSame(
            $this->getWiki()->services->get(PageManager::class)->getCreateTime(self::TAG),
            $listed['created_at'] ?? null,
            'the creation is the oldest revision, not the one being read'
        );
    }

    /**
     * An entry keeps its own, which is the half that already worked: what the body records
     * is written in a different timezone from the row's `time`, so preferring the row here
     * would move every entry's dates by that offset.
     */
    public function testAnEntrysOwnDatesAreLeftAlone(): void
    {
        $entry = [
            'tag' => 'AnyTag',
            'created_at' => '2019-01-02 03:04:05',
            'updated_at' => '2020-06-07 08:09:10',
        ];
        $page = ['tag' => 'AnyTag', 'time' => '2026-01-01 00:00:00', 'owner' => '', 'user' => ''];

        $this->getWiki()->services->get(EntryManager::class)->appendDisplayData($entry, false, '', $page);

        $this->assertSame('2020-06-07 08:09:10', $entry['updated_at']);
        $this->assertSame('2019-01-02 03:04:05', $entry['created_at']);
    }
}
