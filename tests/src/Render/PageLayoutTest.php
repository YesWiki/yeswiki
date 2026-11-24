<?php

namespace YesWiki\Test\Render;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use YesWiki\Content\Api\PageApiController;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Kernel\Exception\ExitException;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Render\Service\LayoutService;
use YesWiki\Render\Service\PageLayout;
use YesWiki\Render\Service\Performer;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Ticket end-users 45: a page's own menu, header, footer, theme, CSS and OpenGraph, saved in its Metadata with the page. */
#[CoversClass(PageLayout::class)]
class PageLayoutTest extends YesWikiTestCase
{
    private const PAGE = 'PageLayoutTestPage';
    private const OPEN_PAGE = 'PageLayoutTestOpenPage';
    private const FOOTER = 'PageLayoutTestFooter';
    private const OPEN_ACLS = ['read' => '*', 'write' => '*', 'comment' => ''];

    private ?Request $previousRequest = null;
    private ?string $previousTag = null;

    protected function setUp(): void
    {
        parent::setUp();
        $services = self::getWiki()->services;
        $this->previousRequest = $services->get(CurrentRequest::class)->get();
        $this->previousTag = $services->get(PageContext::class)->getTag();
        self::actAs(self::adminName());
        $pages = $services->get(PageManager::class);
        $pages->save(self::PAGE, [PageBody::CONTENT => 'une page'], '', true);
        $pages->save(self::OPEN_PAGE, [PageBody::CONTENT => 'une page ouverte'], '', true, null, null, self::OPEN_ACLS);
        $pages->save(self::FOOTER, [PageBody::CONTENT => 'un pied de page à part'], '', true);
    }

    protected function tearDown(): void
    {
        $services = self::getWiki()->services;
        foreach ([self::PAGE, self::OPEN_PAGE, self::FOOTER] as $tag) {
            $services->get(PageManager::class)->deleteOrphaned($tag);
        }
        $_POST = [];
        if ($this->previousRequest !== null) {
            $services->get(CurrentRequest::class)->replace($this->previousRequest);
        }
        $services->get(PageContext::class)->setTag((string)$this->previousTag);
        $services->get(PageContext::class)->setMetadata([]);
        self::actAs(null);
        parent::tearDown();
    }

    private function layout(): PageLayout
    {
        return self::getWiki()->services->get(PageLayout::class);
    }

    private function onPage(string $tag, string $method = 'show'): void
    {
        $services = self::getWiki()->services;
        $page = $services->get(PageManager::class)->getOne($tag);
        $services->get(PageContext::class)->setTag($tag);
        $services->get(PageContext::class)->setPage($page);
        $services->get(PageContext::class)->setMethod($method);
        $services->get(PageContext::class)->setMetadata($services->get(PageManager::class)->getMetadata($tag) ?? []);
    }

    /** @param array<string, mixed> $post */
    private function postEdit(string $tag, array $post): void
    {
        $services = self::getWiki()->services;
        $this->onPage($tag, 'edit');
        $page = $services->get(PageManager::class)->getOne($tag);
        $_POST = ['submit' => 'Sauver', 'previous' => $page['id']] + $post + self::validBotGuardFields(self::getWiki());
        $services->get(CurrentRequest::class)->replace(Request::createFromGlobals());
        try {
            $services->get(Performer::class)->run('edit', 'handler', []);
        } catch (ExitException) {
        }
        $_POST = [];
    }

    public function testTheEditScreenShowsThePanelInsideThePageForm(): void
    {
        $services = self::getWiki()->services;
        $services->get(PageManager::class)->setMetadata(self::PAGE, ['PageFooter' => 'none', 'custom_css' => 'body{}']);
        $this->onPage(self::PAGE, 'edit');
        $_POST = [];
        $services->get(CurrentRequest::class)->replace(Request::createFromGlobals());

        $output = $services->get(Performer::class)->run('edit', 'handler', []);

        $form = substr($output, (int)strpos($output, 'id="ACEditor"'));
        $form = substr($form, 0, (int)strpos($form, '</form>'));
        $this->assertStringContainsString('data-yw-page-layout', $form);
        $this->assertStringContainsString('name="layout_shown"', $form);
        $this->assertMatchesRegularExpression('/<option value="none" selected>/', $form);
        $this->assertStringContainsString('name="custom_css"', $form);
        $this->assertStringNotContainsString('graphical_options', $output);

        self::actAs(null);
        $this->onPage(self::OPEN_PAGE, 'edit');
        $output = $services->get(Performer::class)->run('edit', 'handler', []);
        $this->assertStringContainsString('data-yw-page-layout', $output);
        $this->assertStringNotContainsString('name="custom_css"', $output);
    }

    public function testAFormWithoutThePanelChangesNothing(): void
    {
        $this->assertSame([], $this->layout()->fromForm(['body' => 'x'], self::PAGE));
    }

    public function testThePanelSaysWhatTheWikiDoesWithNulls(): void
    {
        $wanted = $this->layout()->fromForm([
            'layout_shown' => '1',
            'layout_navbar_position' => 'none',
            'PageHeader_mode' => '',
            'PageHeader_page' => 'Ignored',
            'PageFooter_mode' => 'page',
            'PageFooter_page' => self::FOOTER,
            'og_title' => '  Un titre  ',
        ], self::PAGE);

        $this->assertSame('none', $wanted['layout_navbar_position']);
        $this->assertNull($wanted['PageHeader']);
        $this->assertSame(self::FOOTER, $wanted['PageFooter']);
        $this->assertSame('Un titre', $wanted['og_title']);
        foreach (PageLayout::THEME_KEYS as $key) {
            $this->assertArrayHasKey($key, $wanted);
            $this->assertNull($wanted[$key], 'an unticked own theme removes ' . $key);
        }
    }

    public function testAnOwnThemeIsWrittenWithTheSuffixesTheThemeManagerReads(): void
    {
        $wanted = $this->layout()->fromForm([
            'layout_shown' => '1',
            'layout_own_theme' => '1',
            'theme_select' => 'margot',
            'squelette_select' => '1col',
            'style_select' => 'margot',
            'preset_select' => '',
        ], self::PAGE);

        $this->assertSame('margot', $wanted['theme']);
        $this->assertSame('1col.tpl.html', $wanted['squelette']);
        $this->assertSame('margot.css', $wanted['style']);
        $this->assertNull($wanted['favorite_preset']);
    }

    public function testValuesOutsideTheirRulesAreRefused(): void
    {
        foreach ([
            ['unknown' => 'x'],
            ['layout_navbar_position' => 'bottom'],
            ['theme' => '../../etc'],
            ['og_image' => 'http://example.org/insecure.png'],
            ['og_image' => 'NoSuchFileEntry'],
            ['custom_css' => 'body { color: red } </style><script>alert(1)</script>'],
        ] as $metadata) {
            try {
                $this->layout()->checked($metadata, self::PAGE);
                $this->fail('refused: ' . json_encode($metadata));
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(
            ['og_image' => 'https://example.org/image.png'],
            $this->layout()->checked(['og_image' => 'https://example.org/image.png'], self::PAGE)
        );
    }

    public function testOnlyAnAdminOrTheOwnerMayChangeTheCss(): void
    {
        $this->assertTrue($this->layout()->mayEditCss(self::PAGE));

        self::actAs(null);
        $this->assertFalse($this->layout()->mayEditCss(self::OPEN_PAGE));
        $this->assertArrayNotHasKey(
            PageLayout::CUSTOM_CSS,
            $this->layout()->fromForm(['layout_shown' => '1', 'custom_css' => 'body{}'], self::OPEN_PAGE),
            'the form drops a CSS it did not offer'
        );
        $this->expectException(AccessDeniedException::class);
        $this->layout()->checked(['custom_css' => 'body{}'], self::OPEN_PAGE);
    }

    public function testChangesKeepOnlyWhatDiffers(): void
    {
        $this->assertSame(
            ['PageFooter' => null, 'og_title' => 'B'],
            PageLayout::changes(
                ['PageFooter' => 'X', 'og_title' => 'A', 'lang' => 'fr'],
                ['PageFooter' => null, 'og_title' => 'B', 'lang' => 'fr', 'theme' => null]
            )
        );
    }

    public function testTheBodyAndTheLayoutAreSavedAsOneRevision(): void
    {
        $pages = self::getWiki()->services->get(PageManager::class);
        $before = $pages->countRevisions(self::PAGE);

        $this->postEdit(self::PAGE, [
            'body' => 'une page modifiée',
            'layout_shown' => '1',
            'layout_navbar_position' => 'left',
            'PageFooter_mode' => 'none',
            'custom_css' => 'body { outline: 1px solid red; }',
        ]);

        $this->assertSame($before + 1, $pages->countRevisions(self::PAGE));
        $metadata = $pages->getMetadata(self::PAGE) ?? [];
        $this->assertSame('left', $metadata['layout_navbar_position'] ?? null);
        $this->assertSame('none', $metadata['PageFooter'] ?? null);
        $this->assertSame('body { outline: 1px solid red; }', $metadata['custom_css'] ?? null);
        $this->assertArrayHasKey('acls', $metadata, 'the rest of the Metadata is carried forward');
    }

    public function testALayoutChangeAloneIsSavedAndClearingAFieldRemovesItsKey(): void
    {
        $pages = self::getWiki()->services->get(PageManager::class);
        $pages->setMetadata(self::PAGE, ['og_title' => 'Ancien titre', 'PageFooter' => self::FOOTER]);
        $before = $pages->countRevisions(self::PAGE);

        $this->postEdit(self::PAGE, [
            'body' => 'une page',
            'layout_shown' => '1',
            'PageFooter_mode' => '',
            'og_title' => '',
        ]);

        $this->assertSame($before + 1, $pages->countRevisions(self::PAGE));
        $metadata = $pages->getMetadata(self::PAGE) ?? [];
        $this->assertArrayNotHasKey('og_title', $metadata);
        $this->assertArrayNotHasKey('PageFooter', $metadata);
    }

    public function testARefusedCssSavesNothing(): void
    {
        $pages = self::getWiki()->services->get(PageManager::class);
        $before = $pages->countRevisions(self::PAGE);

        $this->postEdit(self::PAGE, [
            'body' => 'ce texte ne doit pas être enregistré',
            'layout_shown' => '1',
            'custom_css' => '</style><script>alert(1)</script>',
        ]);

        $this->assertSame($before, $pages->countRevisions(self::PAGE));
        $this->assertSame('une page', PageBody::content($pages->getOne(self::PAGE)['body'] ?? []));
    }

    public function testThePageDecidesItsMenuHeaderAndFooter(): void
    {
        $services = self::getWiki()->services;
        $layout = $services->get(LayoutService::class);
        $services->get(PageManager::class)->setMetadata(self::PAGE, [
            'layout_navbar_position' => 'none',
            'PageHeader' => 'none',
            'PageFooter' => self::FOOTER,
        ]);
        $this->onPage(self::PAGE);

        $this->assertSame('none', $layout->navbarPosition());
        $this->assertContains($layout->wikiNavbarPosition(), LayoutService::NAVBAR_POSITIONS);
        $this->assertSame('', $layout->pageFor('PageHeader'));
        $this->assertSame(self::FOOTER, $layout->pageFor('PageFooter'));
        $this->assertSame('', $services->get(Performer::class)->run('include', 'action', ['page' => 'PageHeader']));
    }

    public function testThePageCssIsDeclaredAndJoinsTheFingerprint(): void
    {
        $services = self::getWiki()->services;
        $layout = $services->get(LayoutService::class);
        $this->onPage(self::PAGE);
        $this->assertSame('', $layout->pageCustomCss());

        $services->get(PageManager::class)->setMetadata(self::PAGE, ['custom_css' => '.yw-page-layout-test { color: red; }']);
        $this->onPage(self::PAGE);

        $this->assertSame('.yw-page-layout-test { color: red; }', $layout->pageCustomCss());
        $this->assertContains(
            hash('xxh3', '.yw-page-layout-test { color: red; }'),
            $services->get(\YesWiki\Render\Service\ThemeManager::class)->layoutIdentity()
        );
    }

    public function testOpenGraphUsesThePageValuesEscaped(): void
    {
        $services = self::getWiki()->services;
        $services->get(PageManager::class)->setMetadata(self::PAGE, [
            'og_title' => '"><script>alert(1)</script>',
            'og_description' => 'Une description choisie',
            'og_image' => 'https://example.org/partage.png',
        ]);
        $this->onPage(self::PAGE);

        $head = $services->get(Performer::class)->run('metarobots', 'action', []);

        $this->assertStringNotContainsString('<script>', $head);
        $this->assertStringContainsString('content="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"', $head);
        $this->assertStringContainsString('<meta property="og:description" content="Une description choisie" />', $head);
        $this->assertStringContainsString('<meta property="og:image" content="https://example.org/partage.png" />', $head);
    }

    public function testTheApiRefusesTheCssFromSomeoneElseAndUnknownKeys(): void
    {
        $services = self::getWiki()->services;
        $controller = $services->get(PageApiController::class);
        $pages = $services->get(PageManager::class);

        self::actAs(null);
        $response = $controller->savePageMetadatas(
            Request::create('/api/pages/' . self::OPEN_PAGE . '/metadatas', 'POST', ['metadatas' => ['custom_css' => 'body{}']]),
            self::OPEN_PAGE
        );
        $this->assertSame(403, $response->getStatusCode());
        $this->assertArrayNotHasKey('custom_css', $pages->getMetadata(self::OPEN_PAGE) ?? []);

        self::actAs(self::adminName());
        $response = $controller->savePageMetadatas(
            Request::create('/api/pages/' . self::PAGE . '/metadatas', 'POST', ['metadatas' => ['acls' => ['read' => '*']]]),
            self::PAGE
        );
        $this->assertSame(400, $response->getStatusCode());
    }
}
