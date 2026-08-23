<?php

namespace YesWiki\Test\Content;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YesWiki\Content\Service\EntryListCategoryRewriter;

/** `{{entrylistcategory}}` becomes a grouped entry list (ticket 49). */
class EntryListCategoryRewriterTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function callProvider(): array
    {
        return [
            'the two-argument call, which is what a stored page says' => [
                '{{entrylistcategory idtypeannonce="4" id="bf_type" list="ListeType"}}',
                '{{entrylist id="4" groups="bf_type" template="liste_accordeon"}}',
            ],
            'the pre-rename spelling of the same call' => [
                '{{bazarlistecategorie idtypeannonce="4" id="bf_type" list="ListeType"}}',
                '{{entrylist id="4" groups="bf_type" template="liste_accordeon"}}',
            ],
            'a form id on its own is a form, because a field name is not a number' => [
                '{{entrylistcategory id="4" list="ListeType"}}',
                '{{entrylist id="4" template="liste_accordeon"}}',
            ],
            'a field name on its own is a field' => [
                '{{entrylistcategory id="bf_type" list="ListeType"}}',
                '{{entrylist groups="bf_type" template="liste_accordeon"}}',
            ],
            'order is a parameter an entry list has' => [
                '{{entrylistcategory idtypeannonce="4" id="bf_type" order="desc"}}',
                '{{entrylist id="4" groups="bf_type" template="liste_accordeon" order="desc"}}',
            ],
            'the accordion replaces whatever template drew each group' => [
                '{{entrylistcategory idtypeannonce="4" id="bf_type" template="card"}}',
                '{{entrylist id="4" groups="bf_type" template="liste_accordeon"}}',
            ],
        ];
    }

    #[DataProvider('callProvider')]
    public function testACallIsRewrittenOntoAGroupedList(string $stored, string $expected): void
    {
        $this->assertSame($expected, (new EntryListCategoryRewriter())->rewriteText($stored));
    }

    public function testTextAroundTheCallIsUntouched(): void
    {
        $rewriter = new EntryListCategoryRewriter();

        $this->assertSame(
            "Voici la liste :\n{{entrylist id=\"4\" groups=\"bf_type\" template=\"liste_accordeon\"}}\nEt voilà.",
            $rewriter->rewriteText("Voici la liste :\n{{entrylistcategory idtypeannonce=\"4\" id=\"bf_type\"}}\nEt voilà.")
        );
    }

    public function testAPageWithNoSuchCallIsNotRewrittenAtAll(): void
    {
        $this->assertNull((new EntryListCategoryRewriter())->rewriteBody(['content' => '{{entrylist id="4"}}']));
    }

    public function testEveryStringInABodyIsRewritten(): void
    {
        $body = (new EntryListCategoryRewriter())->rewriteBody([
            'content' => '{{entrylistcategory idtypeannonce="4" id="bf_type"}}',
            'bf_description' => 'no call here',
        ]);

        $this->assertIsArray($body);
        $this->assertSame('{{entrylist id="4" groups="bf_type" template="liste_accordeon"}}', $body['content']);
        $this->assertSame('no call here', $body['bf_description']);
    }

    /** What the author loses, so the migration can name it rather than leave them to find it. */
    public function testWhatCannotBeCarriedIsReported(): void
    {
        $rewriter = new EntryListCategoryRewriter();
        $rewriter->rewriteText('{{entrylistcategory idtypeannonce="4" id="bf_type" list="ListeType" template="card"}}');

        $this->assertSame(['list', 'template'], $rewriter->droppedParameters());
    }

    /** A call naming no field at all becomes an ungrouped list, which is worth being told. */
    public function testACallWithNothingToGroupOnSaysSo(): void
    {
        $rewriter = new EntryListCategoryRewriter();
        $rewriter->rewriteText('{{entrylistcategory id="4"}}');

        $this->assertContains('groups', $rewriter->droppedParameters());
    }
}
