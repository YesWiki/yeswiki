<?php

namespace YesWiki\Test\Search;

use YesWiki\Content\Service\PageManager;
use YesWiki\Content\Service\PageSummary;
use YesWiki\Kernel\Service\LanguageService;
use YesWiki\Search\Service\SearchIndexer;
use YesWiki\Search\Service\SearchIndexQuery;
use YesWiki\Search\Service\SearchIndexSchema;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Ticket 66: the index holds one row per language, and a reader searches the wiki in the language they read. */
class SearchIndexLanguagesTest extends YesWikiTestCase
{
    private const TRANSLATED = 'TestTicket66TranslatedPage';
    private const UNTRANSLATED = 'TestTicket66UntranslatedPage';

    private string $previousLanguage = '';

    protected function setUp(): void
    {
        $wiki = $this->getWiki();
        if (!$wiki->services->get(SearchIndexSchema::class)->exists()) {
            $this->markTestSkipped('no search index on this wiki');
        }
        $this->previousLanguage = $wiki->services->get(LanguageService::class)->preferredLanguage();

        $pageManager = $wiki->services->get(PageManager::class);
        $pageManager->save(self::TRANSLATED, ['title' => 'Atelier', 'content' => 'un atelier ticketsixtysix'], '', true);
        $pageManager->saveTranslations(self::TRANSLATED, 'en', ['title' => 'Workshop', 'content' => 'a workshop englishonlysixtysix'], true);
        $pageManager->save(self::UNTRANSLATED, ['title' => 'Atelier seul', 'content' => 'un autre atelier ticketsixtysix'], '', true);

        $indexer = $wiki->services->get(SearchIndexer::class);
        $indexer->enqueue([self::TRANSLATED, self::UNTRANSLATED]);
        $indexer->drain(50);
    }

    protected function tearDown(): void
    {
        $services = $this->getWiki()->services;
        $services->get(LanguageService::class)->serveIn($this->previousLanguage);
        foreach ([self::TRANSLATED, self::UNTRANSLATED] as $tag) {
            $services->get(SearchIndexer::class)->delete($tag);
            $services->get(PageManager::class)->deleteOrphaned($tag);
        }
    }

    /** @return array<string, string> tag => title */
    private function found(string $language, string $query): array
    {
        $services = $this->getWiki()->services;
        $services->get(LanguageService::class)->serveIn($language);
        $results = $services->get(SearchIndexQuery::class)->search($query, null, 50)['results'];

        return array_column($results, 'title', 'tag');
    }

    public function testAFrenchReaderSearchesTheFrenchWiki(): void
    {
        $this->assertSame(
            [self::TRANSLATED => 'Atelier', self::UNTRANSLATED => 'Atelier seul'],
            array_intersect_key($this->found('fr', 'ticketsixtysix'), [self::TRANSLATED => 1, self::UNTRANSLATED => 1])
        );
        $this->assertSame([], $this->found('fr', 'englishonlysixtysix'));
    }

    public function testAnEnglishReaderFindsTheTranslationAndTheSourceOfWhatIsNotTranslated(): void
    {
        $this->assertSame([self::TRANSLATED => 'Workshop'], $this->found('en', 'englishonlysixtysix'));
        $this->assertSame([self::UNTRANSLATED => 'Atelier seul'], array_intersect_key($this->found('en', 'ticketsixtysix'), [self::TRANSLATED => 1, self::UNTRANSLATED => 1]));
    }

    public function testTheExcerptIsInTheReadersLanguage(): void
    {
        $services = $this->getWiki()->services;
        $services->get(LanguageService::class)->serveIn('en');

        $this->assertStringContainsString('englishonlysixtysix', $services->get(SearchIndexQuery::class)->textFor(self::TRANSLATED));
    }

    public function testAListingShowsTheTranslatedTitle(): void
    {
        $services = $this->getWiki()->services;
        $page = $services->get(PageManager::class)->getOne(self::TRANSLATED, null, false, true);
        $this->assertNotNull($page);

        $services->get(LanguageService::class)->serveIn('en');
        $this->assertSame('Workshop', $services->get(PageSummary::class)->title($page));
        $services->get(LanguageService::class)->serveIn('fr');
        $this->assertSame('Atelier', $services->get(PageSummary::class)->title($page));
    }
}
