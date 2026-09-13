<?php

namespace YesWiki\Test\Actions;

use Symfony\Component\HttpFoundation\Request;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\LanguageService;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Render\Service\Performer;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** `{{languages}}`: one call offering every language, replacing one `{{translation}}` call per flag. */
class LanguagesActionTest extends YesWikiTestCase
{
    /**
     * @param array<string, mixed> $query
     */
    private function render(string $tag = 'PagePrincipale', array $query = []): string
    {
        $wiki = $this->getWiki();
        $wiki->services->get(CurrentRequest::class)->replace(new Request($query));
        $wiki->services->get(PageContext::class)->setTag($tag);

        return (string)$wiki->services->get(Performer::class)->run('languages', 'action', []);
    }

    /**
     * @return list<string>
     */
    private function offered(): array
    {
        return $this->getWiki()->services->get(LanguageService::class)->availableLanguages();
    }

    public function testEveryOfferedLanguageIsALink(): void
    {
        $offered = $this->offered();
        if (count($offered) < 2) {
            $this->markTestSkipped('this wiki offers a single language, so the switch draws nothing');
        }

        $html = $this->render();

        foreach ($offered as $language) {
            $this->assertStringContainsString('hreflang="' . $language . '"', $html);
            $this->assertStringContainsString('lang=' . $language, html_entity_decode($html));
        }
    }

    public function testTheLanguageBeingServedIsMarkedAsCurrent(): void
    {
        if (count($this->offered()) < 2) {
            $this->markTestSkipped('this wiki offers a single language');
        }

        $current = $this->getWiki()->services->get(LanguageService::class)->preferredLanguage();
        $html = $this->render();

        $this->assertMatchesRegularExpression(
            '/hreflang="' . preg_quote($current, '/') . '"[^>]*aria-current="true"/',
            $html
        );
    }

    /** The switch keeps the reader where they are, which is the whole point of it being one call. */
    public function testTheLinksStayOnTheCurrentPage(): void
    {
        if (count($this->offered()) < 2) {
            $this->markTestSkipped('this wiki offers a single language');
        }

        $html = html_entity_decode($this->render('PageMenuHaut'));

        $this->assertStringContainsString('PageMenuHaut', $html);
    }

    public function testASingleLanguageWikiDrawsNothing(): void
    {
        if (count($this->offered()) > 1) {
            $this->markTestSkipped('this wiki offers several languages, so the switch is drawn');
        }

        $this->assertSame('', trim($this->render()));
    }
}
