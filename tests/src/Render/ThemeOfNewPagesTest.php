<?php

namespace YesWiki\Test\Render;

use Symfony\Component\HttpFoundation\Request;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Render\Service\LinkRenderer;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A new page follows the site theme unless the page it was linked from has a theme of its own. */
class ThemeOfNewPagesTest extends YesWikiTestCase
{
    private const PARENT = 'ThemeOfNewPagesTestParent';
    private const CHILD = 'ThemeOfNewPagesTestChild';
    private const OWN_THEME = ['theme' => 'margot', 'style' => 'margot-fun.css', 'squelette' => 'full-page.tpl.html'];

    private PageManager $pageManager;
    private ?Request $previousRequest = null;
    private ?string $previousTag = null;

    protected function setUp(): void
    {
        $services = $this->getWiki()->services;
        $this->pageManager = $services->get(PageManager::class);
        $this->previousRequest = $services->get(CurrentRequest::class)->get();
        $this->previousTag = $services->get(PageContext::class)->getTag();
        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        $services = $this->getWiki()->services;
        if ($this->previousRequest !== null) {
            $services->get(CurrentRequest::class)->replace($this->previousRequest);
        }
        $services->get(PageContext::class)->setTag((string)$this->previousTag);
        parent::tearDown();
    }

    private function cleanUp(): void
    {
        foreach ([self::PARENT, self::CHILD] as $tag) {
            $this->pageManager->deleteOrphaned($tag);
        }
    }

    /** @param array<string, mixed>|null $metadata */
    private function onParent(?array $metadata = null): void
    {
        $this->pageManager->save(self::PARENT, [PageBody::CONTENT => 'parent'], '', true);
        if ($metadata !== null) {
            $this->pageManager->setMetadata(self::PARENT, $metadata);
        }
        $this->getWiki()->services->get(PageContext::class)->setTag(self::PARENT);
    }

    /** @return array<mixed> */
    private function linkParams(): array
    {
        return $this->getWiki()->services->get(LinkRenderer::class)->paramsForNewPageLink();
    }

    /**
     * @param array<mixed> $linkParams
     *
     * @return array<string, mixed>
     */
    private function createChildFromItsLink(array $linkParams): array
    {
        $this->getWiki()->services->get(CurrentRequest::class)->replace(
            Request::create('/?wiki=' . self::CHILD . '/edit', 'POST', ['body' => 'child'] + $linkParams)
        );
        $this->pageManager->save(self::CHILD, [PageBody::CONTENT => 'child'], '', true);

        return $this->pageManager->getMetadata(self::CHILD) ?? [];
    }

    public function testALinkFromAPageWithoutItsOwnThemeCarriesNoTheme(): void
    {
        $this->onParent();

        $params = $this->linkParams();

        $this->assertSame(1, $params['newpage']);
        foreach (['theme', 'style', 'squelette', 'bgimg'] as $key) {
            $this->assertArrayNotHasKey($key, $params);
        }
    }

    public function testALinkFromAPageWithItsOwnThemeCarriesThatTheme(): void
    {
        $this->onParent(self::OWN_THEME);

        $params = $this->linkParams();

        $this->assertSame('margot', $params['theme']);
        $this->assertSame('margot-fun.css', $params['style']);
        $this->assertSame('full-page.tpl.html', $params['squelette']);
    }

    public function testAPageCreatedFromALinkOnAPageWithoutItsOwnThemeFollowsTheSiteTheme(): void
    {
        $this->onParent();

        $metadata = $this->createChildFromItsLink($this->linkParams());

        $this->assertEmpty($metadata['theme'] ?? null);
    }

    public function testAPageCreatedFromALinkKeepsTheFooterOfThePageItCameFrom(): void
    {
        $this->onParent(['PageFooter' => 'ThemeOfNewPagesTestFooter']);

        $metadata = $this->createChildFromItsLink($this->linkParams());

        $this->assertSame('ThemeOfNewPagesTestFooter', $metadata['PageFooter'] ?? null);
        $this->assertEmpty($metadata['theme'] ?? null);
    }

    public function testAPageCreatedFromALinkOnAPageWithItsOwnThemeInheritsIt(): void
    {
        $this->onParent(self::OWN_THEME);

        $metadata = $this->createChildFromItsLink($this->linkParams());

        $this->assertSame('margot-fun.css', $metadata['style'] ?? null);
        $this->assertSame('full-page.tpl.html', $metadata['squelette'] ?? null);
    }
}
