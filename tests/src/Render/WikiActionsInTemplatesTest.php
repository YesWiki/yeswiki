<?php

namespace YesWiki\Test\Render;

use YesWiki\Kernel\Service\PageContext;
use YesWiki\Render\Service\ActionRunner;
use YesWiki\Render\Service\GraphicalElementState;
use YesWiki\Render\Service\Performer;
use YesWiki\Render\Service\TemplateEngine;
use YesWiki\Render\Twig\WikiActionSyntax;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A template calls an action the way a page does, and closes what it opens whatever the page around it holds. */
class WikiActionsInTemplatesTest extends YesWikiTestCase
{
    private const SQUELETTE = '{% block body %}<main>{{section class="tpl"}}{{ page_content|raw }}{{end elem="section"}}</main>{% endblock %}';

    private function engine(): TemplateEngine
    {
        return $this->getWiki()->services->get(TemplateEngine::class);
    }

    /** The squelette rendered around a page whose body is $body. */
    private function wrapPage(string $tag, string $body): string
    {
        $services = $this->getWiki()->services;
        $services->get(GraphicalElementState::class)->startNewRequest();
        $pageContext = $services->get(PageContext::class);
        $previous = $pageContext->getPage();
        $pageContext->setPage(['tag' => $tag, 'body' => $body]);
        try {
            return $this->engine()->createSquelette(self::SQUELETTE)->renderBlock('body', ['page_content' => '<p>page</p>']);
        } finally {
            $pageContext->setPage($previous);
        }
    }

    public function testASqueletteRendersAButton(): void
    {
        $html = $this->engine()->createSquelette('{% block body %}{{button text="Ok" link="PagePrincipale"}}{% endblock %}')->renderBlock('body');

        $this->assertMatchesRegularExpression('~<a[^>]*class="[^"]*yw-btn[^"]*"[^>]*>.*Ok~s', $html);
    }

    public function testAListPlacedInATemplateIsInTheActionsLog(): void
    {
        $this->engine()->renderFromString('{{bazarliste id="1" showmapinlistview="1"}}');

        $logged = array_filter(
            $this->getWiki()->services->get(ActionRunner::class)->actionsLog(),
            fn (array $entry): bool => $entry['action'] === 'bazarliste' && ($entry['vars']['id'] ?? null) === '1'
        );
        $this->assertNotSame([], $logged);
    }

    public function testABareNameIsTheVariableFirstThenTheActionThenNothing(): void
    {
        $engine = $this->engine();

        $this->assertSame('mine', $engine->renderFromString('{{yeswikiversion}}', ['yeswikiversion' => 'mine']));
        $this->assertNotSame('', trim($engine->renderFromString('{{yeswikiversion}}')));
        $this->assertSame('', $engine->renderFromString('{{noSuchActionOrVariable}}'));
    }

    public function testATemplateClosesItsSectionInsideAnUnbalancedPage(): void
    {
        $html = $this->wrapPage('WikiActionsUnbalanced', "{{section}}\nouverte");

        $this->assertSame(1, substr_count($html, '</section>'));
    }

    public function testABalancedPageGetsTheSameMarkupAsAnEmptyOne(): void
    {
        $this->assertSame(
            $this->wrapPage('WikiActionsEmpty', 'rien'),
            $this->wrapPage('WikiActionsBalanced', "{{section}}\nx\n{{end elem=\"section\"}}")
        );
    }

    public function testEndWithoutElemShowsThePageError(): void
    {
        $this->assertStringContainsString(_t('TEMPLATE_ELEM_PARAMETER_REQUIRED'), $this->engine()->renderFromString('{{end /}}'));
    }

    public function testShippedTemplatesHoldNoWikiCallAndNoBareActionName(): void
    {
        $performer = $this->getWiki()->services->get(Performer::class);
        $found = [];
        foreach (['templates', 'themes', 'src'] as $root) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                $path = $file->getPathname();
                if (!str_ends_with($path, '.twig') || str_ends_with($path, '.sql.twig')) {
                    continue;
                }
                foreach (WikiActionSyntax::calls((string)file_get_contents($path)) as $call) {
                    if (!$call['bare'] || $performer->exists($call['name'], 'action')) {
                        $found[] = "$path:{$call['line']} {$call['name']}";
                    }
                }
            }
        }

        $this->assertSame([], $found);
    }
}
