<?php

namespace YesWiki\Test\Admin;

use YesWiki\Admin\Service\Documentation;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** `/doc` renders the docs' Markdown on the server and searches it there, with the wiki's own syntax. */
class DocumentationTest extends YesWikiTestCase
{
    private function documentation(): Documentation
    {
        return self::getWiki()->services->get(Documentation::class);
    }

    public function testTheHomeIsTheReadme(): void
    {
        $page = $this->documentation()->page('');

        $this->assertNotNull($page);
        $this->assertSame('README', $page['name']);
        $this->assertSame('Documentation YesWiki', $page['title']);
        $this->assertStringContainsString('yw-alert--info', $page['html'], '::: info is the wiki\'s alert');
        $this->assertSame('https://github.com/YesWiki/yeswiki/edit/ectoplasme-dev/docs/fr/README.md', $page['edit']);
    }

    public function testALinkToAnotherFileOpensItsPageAtItsHeading(): void
    {
        $html = $this->documentation()->page('admin')['html'] ?? '';

        $this->assertMatchesRegularExpression('/href="[^"]*doc\/async#sauvegardes-et-restaurations"/', $html);
        $this->assertStringNotContainsString('.md"', $html, 'no link is left pointing at a Markdown file');
        $this->assertStringContainsString('id="sauvegardes-et-restaurations"', $this->documentation()->page('async')['html'] ?? '');
    }

    public function testAnImageIsServedFromItsDocsFolder(): void
    {
        $this->assertMatchesRegularExpression('/<img loading="lazy" src="[^"]*docs\/fr\/images\/[^"]+\.png/', $this->documentation()->page('admin')['html'] ?? '');
    }

    public function testTheTableOfContentsHoldsLevelTwoAndThreeHeadings(): void
    {
        $headings = $this->documentation()->page('dev')['headings'] ?? [];

        $this->assertNotEmpty($headings);
        $this->assertSame([2, 3], array_values(array_unique(array_map(fn (array $h): int => $h['level'], $headings))));
    }

    public function testAPathOutsideTheBooksIsNoPage(): void
    {
        foreach (['nope', '_menu', '../README', 'fr/README', 'extensions/nope', 'extensions/a/b/c'] as $path) {
            $this->assertNull($this->documentation()->page($path), $path);
        }
    }

    public function testTheMenuPointsIntoTheDocAndMarksThePageShown(): void
    {
        $documentation = $this->documentation();
        $menu = $documentation->menu($documentation->url('', 'bazar'));

        $this->assertStringContainsString('class="active" href="' . htmlspecialchars($documentation->url('', 'bazar')) . '"', $menu);
        $this->assertStringNotContainsString('https://forum.yeswiki.net/', $menu, 'the other spaces are on the home page, not in the menu');
        $this->assertStringContainsString('https://forum.yeswiki.net/', $documentation->page('')['html'] ?? '');
    }

    public function testSearchFindsSectionsWithEveryWordIgnoringCaseAndAccents(): void
    {
        $results = $this->documentation()->search('DROITS acces');

        $this->assertNotEmpty($results);
        $this->assertStringContainsString('#', $results[0]['url']);
        $this->assertMatchesRegularExpression('/<mark>(droits|accès)<\/mark>/i', implode(' ', array_column($results, 'excerpt')));
    }

    public function testSearchEscapesWhatItQuotes(): void
    {
        foreach ($this->documentation()->search('script') as $result) {
            $this->assertStringNotContainsString('<script', $result['excerpt']);
        }
    }

    public function testAnEmptyOrUnknownQueryFindsNothing(): void
    {
        $this->assertSame([], $this->documentation()->search('  '));
        $this->assertSame([], $this->documentation()->search('zzqqxxwordnowhere'));
    }
}
