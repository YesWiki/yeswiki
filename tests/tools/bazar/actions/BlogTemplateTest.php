<?php

namespace YesWiki\Test\Bazar\Actions;

use YesWiki\Bazar\Controller\EntryController;
use YesWiki\Bazar\Service\ActivityPubInbox;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\EntryOrigin;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * The blog template lists the newest entry first as its lead, credits the remote account or the wiki owner, and needs no image.
 */
class BlogTemplateTest extends YesWikiTestCase
{
    private const ACTOR = 'https://mamot.fr/users/Khrys';
    private const NOTE = 'https://mamot.fr/users/Khrys/statuses/42';

    private $wiki;
    private $entryManager;
    private $formManager;
    private string $formId;
    private array $tags = [];
    private string $adminName = '';

    protected function setUp(): void
    {
        $this->wiki = $this->getWiki();
        $GLOBALS['wiki'] = $this->wiki;
        $this->entryManager = $this->wiki->services->get(EntryManager::class);
        $this->formManager = $this->wiki->services->get(FormManager::class);
        $auth = $this->wiki->services->get(AuthController::class);
        $auth->logout();

        $this->formId = $this->formManager->create([
            'bn_label_nature' => 'Blog template test form',
            'bn_template' => implode("\n", [
                'texte***bf_titre***Titre***60***255*** *** ***text***1*** *** *** * *** * *** *** *** ***',
                'textelong***bf_chapeau***Résumé*** *** *** *** ***wiki*** *** *** *** * *** * *** *** *** ***',
                'image***bf_image***Image***400***300***1200***900***right*** *** *** *** * *** * *** *** *** ***',
                'textelong***bf_description***Billet*** *** *** *** ***wiki*** *** *** *** * *** * *** *** *** ***',
            ]),
            'bn_condition' => '',
        ]);

        $this->tags['old'] = $this->create('Billet ancien', '2026-01-01 10:00:00');
        $this->tags['remote'] = $this->create('Pouet récent', '2026-03-01 10:00:00', 'Le résumé de la une');
        $this->wiki->services->get(TripleStore::class)->create($this->tags['remote'], ActivityPubInbox::REMOTE_ACTOR_URI, self::ACTOR, '', '');
        $this->wiki->services->get(TripleStore::class)->create($this->tags['remote'], TripleStore::SOURCE_URL_URI, self::NOTE, '', '');

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $admin = $auth->connectFirstAdmin();
        $this->adminName = $admin ? $auth->getLoggedUserName() : '';
        $this->tags['owned'] = $this->create('Billet du milieu', '2026-02-01 10:00:00');
        $auth->logout();
        foreach ($this->tags as $tag) {
            $this->wiki->services->get(AclService::class)->save($tag, 'read', '*');
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->tags as $tag) {
            $this->wiki->services->get(TripleStore::class)->deleteAll($tag, '');
        }
        foreach ($this->tags as $tag) {
            if ($this->entryManager->isEntry($tag)) {
                $this->entryManager->delete($tag, true);
            }
            $this->wiki->services->get(PageManager::class)->deleteOrphaned($tag);
            $this->wiki->services->get(AclService::class)->delete($tag);
        }
        $this->formManager->delete($this->formId);
    }

    private function create(string $title, string $date, string $summary = '', string $description = ''): string
    {
        $entry = $this->entryManager->create($this->formId, [
            'antispam' => 1,
            'bf_titre' => $title,
            'bf_chapeau' => $summary,
            'bf_description' => $description,
            'date_creation_fiche' => $date,
        ]);

        return $entry['id_fiche'];
    }

    private function render(string $params = ''): string
    {
        return $this->wiki->Format('{{bazarliste id="' . $this->formId . '" template="blog"' . $params . '}}');
    }

    private function article(string $html, string $tag): string
    {
        preg_match('#<article[^>]*data-id_fiche="' . $tag . '".*?</article>#s', $html, $match);

        return $match[0] ?? '';
    }

    public function testTheTwigTemplateRendersTheNewestEntryFirstAsTheLead()
    {
        $html = $this->render();

        $this->assertStringContainsString('data-template="blog.twig"', $html);
        $remote = strpos($html, 'Pouet récent');
        $owned = strpos($html, 'Billet du milieu');
        $old = strpos($html, 'Billet ancien');
        $this->assertTrue($remote < $owned && $owned < $old, 'newest first');
        $this->assertSame(1, substr_count($html, 'blog-entry--lead'));
        $this->assertStringContainsString('blog-entry--lead', $this->article($html, $this->tags['remote']));
        $this->assertStringContainsString('Le résumé de la une', $this->article($html, $this->tags['remote']));
    }

    public function testAMirroredEntryCreditsTheRemoteAccount()
    {
        $article = $this->article($this->render(), $this->tags['remote']);

        $this->assertStringContainsString('href="' . self::ACTOR . '"', $article);
        $this->assertStringContainsString('title="@Khrys@mamot.fr"', $article);
        $this->assertStringContainsString('>Khrys</a>', $article);
    }

    public function testALocalEntryCreditsItsWikiOwner()
    {
        if ($this->adminName === '') {
            $this->markTestSkipped('no admin account in the test wiki');
        }
        $article = $this->article($this->render(), $this->tags['owned']);

        $this->assertStringContainsString('<span class="blog-entry__author">' . htmlspecialchars($this->adminName) . '</span>', $article);
    }

    public function testAnEntryWithoutImageHasNoMedia()
    {
        $article = $this->article($this->render(), $this->tags['old']);

        $this->assertStringContainsString('blog-entry--no-media', $article);
        $this->assertStringNotContainsString('blog-entry__media', $article);
    }

    public function testTheDateIsMachineReadableForTheRelativeLabel()
    {
        $article = $this->article($this->render(), $this->tags['old']);

        $this->assertMatchesRegularExpression('#<time class="yw-relative-time" datetime="2026-01-01T10:00:00[+-]\d\d:\d\d">#', $article);
    }

    public function testAGeneratedTitleShowsTheSummaryOrElseTheText()
    {
        $this->tags['generated'] = $this->create('Un pouet tronqué…', '2026-04-01 10:00:00', '', 'Un pouet **entier** avec [un lien](https://example.org)');
        $this->tags['generatedWithSummary'] = $this->create('Avec avertissement…', '2026-03-15 10:00:00', 'Avertissement de contenu', 'Le texte caché');
        foreach (['generated', 'generatedWithSummary'] as $key) {
            $this->wiki->services->get(AclService::class)->save($this->tags[$key], 'read', '*');
            $this->wiki->services->get(TripleStore::class)->create($this->tags[$key], EntryOrigin::GENERATED_TITLE_URI, '1', '', '');
        }
        $html = $this->render();

        $text = $this->article($html, $this->tags['generated']);
        $this->assertStringNotContainsString('Un pouet tronqué', $text);
        $this->assertStringContainsString('<b>entier</b>', $text);
        $this->assertStringContainsString('<a href="https://example.org"', $text);
        $this->assertStringContainsString('class="blog-entry__permalink" href="', $text);

        $withSummary = $this->article($html, $this->tags['generatedWithSummary']);
        $this->assertStringContainsString('Avertissement de contenu', $withSummary);
        $this->assertStringNotContainsString('Le texte caché', $withSummary);
        $this->assertStringNotContainsString('Avec avertissement', $withSummary);
    }

    public function testTheEntryFooterCreditsTheRemoteAuthorAndLinksTheOriginalPost()
    {
        if ($this->adminName === '') {
            $this->markTestSkipped('no admin account in the test wiki');
        }
        $this->wiki->services->get(AuthController::class)->connectFirstAdmin();
        $html = $this->wiki->services->get(EntryController::class)->view($this->tags['remote']);
        $this->wiki->services->get(AuthController::class)->logout();

        $this->assertStringContainsString('<a class="entry-author" href="' . self::ACTOR . '" title="@Khrys@mamot.fr"', $html);
        $this->assertStringContainsString('href="' . self::NOTE . '"', $html);
        $this->assertStringContainsString(htmlspecialchars(_t('BAZ_ORIGINAL_POST'), ENT_QUOTES), $html);
        $this->assertStringNotContainsString('>Source</a>', $html);
    }

    public function testAnExplicitOrderStillWins()
    {
        $html = $this->render(' champ="bf_titre" ordre="asc"');

        $this->assertTrue(strpos($html, 'Billet ancien') < strpos($html, 'Pouet récent'));
    }

    public function testHeaderNoDropsTheLeadAndShowAuthorZeroHidesTheAuthor()
    {
        $html = $this->render(' header="no" show_author="0"');

        $this->assertStringNotContainsString('blog-entry--lead', $html);
        $this->assertStringNotContainsString('blog-entry__author', $html);
    }
}
