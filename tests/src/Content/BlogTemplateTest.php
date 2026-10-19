<?php

namespace YesWiki\Test\Content;

use YesWiki\Content\Controller\EntryController;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Federation\Service\ActivityPubInbox;
use YesWiki\Federation\Service\EntryOrigin;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Kernel\Service\TripleStore;
use YesWiki\Render\Service\Performer;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The blog template lists the newest entry first as its lead, credits the remote account or the wiki owner, and needs no image. */
class BlogTemplateTest extends YesWikiTestCase
{
    private const FORM_ID = '999941';
    private const ACTOR = 'https://mamot.fr/users/Khrys';
    private const NOTE = 'https://mamot.fr/users/Khrys/statuses/42';

    /** @var array<string, string> */
    private array $tags = [];
    private string $adminName = '';

    protected function setUp(): void
    {
        parent::setUp();
        $services = $this->getWiki()->services;
        $admin = $services->get(AuthenticationService::class)->connectFirstAdmin();
        if ($admin === null) {
            $this->markTestSkipped('no admin account in the test wiki');
        }
        $this->adminName = $services->get(AuthenticationService::class)->getLoggedUserName();

        $services->get(FormManager::class)->create([
            'id' => self::FORM_ID,
            'label' => 'Blog template test form',
            'template' => json_encode([
                ['type' => 'texte', 'name' => 'bf_titre', 'label' => 'Titre'],
                ['type' => 'textelong', 'name' => 'bf_chapeau', 'label' => 'Résumé', 'syntax' => 'wiki'],
                ['type' => 'image', 'name' => 'bf_image', 'label' => 'Image'],
                ['type' => 'textelong', 'name' => 'bf_description', 'label' => 'Billet', 'syntax' => 'wiki'],
            ]),
            'condition' => '',
        ]);

        $this->tags['old'] = $this->create('Billet ancien', '2026-01-01 10:00:00');
        $this->tags['remote'] = $this->create('Pouet récent', '2026-03-01 10:00:00', 'Le résumé de la une');
        $this->tags['owned'] = $this->create('Billet du milieu', '2026-02-01 10:00:00');
        $tripleStore = $services->get(TripleStore::class);
        $tripleStore->create($this->tags['remote'], ActivityPubInbox::REMOTE_ACTOR_URI, self::ACTOR, '', '');
        $tripleStore->create($this->tags['remote'], TripleStore::SOURCE_URL_URI, self::NOTE, '', '');
        $services->get(AuthenticationService::class)->logout();
    }

    protected function tearDown(): void
    {
        $services = $this->getWiki()->services;
        $services->get(AuthenticationService::class)->logout();
        $entryManager = $services->get(EntryManager::class);
        foreach ($this->tags as $tag) {
            $services->get(TripleStore::class)->deleteAll($tag, '');
            if ($entryManager->isEntry($tag)) {
                $entryManager->delete($tag, true);
            }
        }
        $services->get(FormManager::class)->delete(self::FORM_ID);
        parent::tearDown();
    }

    private function create(string $title, string $date, string $summary = '', string $description = ''): string
    {
        $entry = $this->getWiki()->services->get(EntryManager::class)->create(self::FORM_ID, [
            'antispam' => 1,
            'bf_titre' => $title,
            'bf_chapeau' => $summary,
            'bf_description' => $description,
            'created_at' => $date,
        ]);

        return (string)$entry['tag'];
    }

    /** @param array<string, string> $params */
    private function render(array $params = []): string
    {
        return (string)$this->getWiki()->services->get(Performer::class)->run('entrylist', 'action', [
            'id' => self::FORM_ID,
            'template' => 'blog',
        ] + $params);
    }

    private function article(string $html, string $tag): string
    {
        preg_match('#<article[^>]*data-tag="' . preg_quote($tag, '#') . '".*?</article>#s', $html, $match);

        return $match[0] ?? '';
    }

    public function testTheNewestEntryComesFirstAsTheLead(): void
    {
        $html = $this->render();

        $remote = strpos($html, 'Pouet récent');
        $owned = strpos($html, 'Billet du milieu');
        $old = strpos($html, 'Billet ancien');
        $this->assertTrue($remote < $owned && $owned < $old, 'newest first');
        $this->assertSame(1, substr_count($html, 'blog-entry--lead'));
        $this->assertStringContainsString('blog-entry--lead', $this->article($html, $this->tags['remote']));
        $this->assertStringContainsString('Le résumé de la une', $this->article($html, $this->tags['remote']));
    }

    public function testAMirroredEntryCreditsTheRemoteAccount(): void
    {
        $article = $this->article($this->render(), $this->tags['remote']);

        $this->assertStringContainsString('href="' . self::ACTOR . '"', $article);
        $this->assertStringContainsString('title="@Khrys@mamot.fr"', $article);
        $this->assertStringContainsString('>Khrys</a>', $article);
    }

    public function testALocalEntryCreditsItsWikiOwner(): void
    {
        $article = $this->article($this->render(), $this->tags['owned']);

        $this->assertStringContainsString('<span class="blog-entry__author">' . htmlspecialchars($this->adminName) . '</span>', $article);
    }

    public function testAnEntryWithoutImageHasNoMedia(): void
    {
        $article = $this->article($this->render(), $this->tags['old']);

        $this->assertStringContainsString('blog-entry--no-media', $article);
        $this->assertStringNotContainsString('blog-entry__media', $article);
    }

    public function testTheDateIsMachineReadableForTheRelativeLabel(): void
    {
        $article = $this->article($this->render(), $this->tags['old']);

        $this->assertMatchesRegularExpression('#<time class="yw-relative-time" datetime="2026-01-01T10:00:00[+-]\d\d:\d\d">#', $article);
    }

    public function testAGeneratedTitleShowsTheSummaryOrElseTheText(): void
    {
        $services = $this->getWiki()->services;
        $services->get(AuthenticationService::class)->connectFirstAdmin();
        $this->tags['generated'] = $this->create('Un pouet tronqué…', '2026-04-01 10:00:00', '', 'Un pouet **entier** avec [un lien](https://example.org)');
        $this->tags['generatedWithSummary'] = $this->create('Avec avertissement…', '2026-03-15 10:00:00', 'Avertissement de contenu', 'Le texte caché');
        $services->get(AuthenticationService::class)->logout();
        foreach (['generated', 'generatedWithSummary'] as $key) {
            $services->get(TripleStore::class)->create($this->tags[$key], EntryOrigin::GENERATED_TITLE_URI, '1', '', '');
        }
        $html = $this->render();

        $text = $this->article($html, $this->tags['generated']);
        $this->assertStringNotContainsString('Un pouet tronqué', $text);
        $this->assertStringContainsString('<strong>entier</strong>', $text);
        $this->assertStringContainsString('href="https://example.org"', $text);
        $this->assertStringContainsString('class="blog-entry__permalink" href="', $text);

        $withSummary = $this->article($html, $this->tags['generatedWithSummary']);
        $this->assertStringContainsString('Avertissement de contenu', $withSummary);
        $this->assertStringNotContainsString('Le texte caché', $withSummary);
        $this->assertStringNotContainsString('Avec avertissement', $withSummary);
    }

    public function testTheEntryFooterCreditsTheRemoteAuthorAndLinksTheOriginalPost(): void
    {
        $services = $this->getWiki()->services;
        $services->get(AuthenticationService::class)->connectFirstAdmin();
        $html = (string)$services->get(EntryController::class)->view($this->tags['remote']);

        $this->assertStringContainsString('<a class="entry-author" href="' . self::ACTOR . '" title="@Khrys@mamot.fr"', $html);
        $this->assertStringContainsString('href="' . self::NOTE . '"', $html);
        $this->assertStringContainsString(htmlspecialchars(_t('BAZ_ORIGINAL_POST'), ENT_QUOTES), $html);
        $this->assertStringNotContainsString('>Source</a>', $html);
    }

    public function testAnExplicitOrderStillWins(): void
    {
        $html = $this->render(['field' => 'bf_titre', 'order' => 'asc']);

        $this->assertTrue(strpos($html, 'Billet ancien') < strpos($html, 'Pouet récent'));
    }

    public function testHeaderNoDropsTheLeadAndShowAuthorZeroHidesTheAuthor(): void
    {
        $html = $this->render(['header' => 'no', 'show_author' => '0']);

        $this->assertStringNotContainsString('blog-entry--lead', $html);
        $this->assertStringNotContainsString('blog-entry__author', $html);
    }
}
