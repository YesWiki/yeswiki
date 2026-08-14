<?php

namespace YesWiki\Test\Content;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YesWiki\Content\Service\ListpagesRewriter;

/**
 * What `{{listpages}}` becomes, on strings -- no wiki, no database.
 *
 * The mapping is the whole of the retirement's risk: a page is an entry of the Pages form
 * (ADR-0011), so the list survives the rewrite, but three of the old action's parameters
 * have no equivalent and are dropped. Each case here is one row of the table in
 * `ListpagesRewriter`'s docblock, so the two cannot drift apart quietly.
 */
class ListpagesRewriterTest extends TestCase
{
    private const PAGES_FORM = '5';

    /**
     * @return array<string, array{string, string}>
     */
    public static function callProvider(): array
    {
        return [
            'a bare call becomes a list of the Pages form' => [
                '{{listpages}}',
                '{{entrylist id="5"}}',
            ],
            'a presentation is kept' => [
                '{{listpages template="card"}}',
                '{{entrylist id="5" template="card"}}',
            ],
            'sorting by tag is what an entry list already does' => [
                '{{listpages sort="tag"}}',
                '{{entrylist id="5"}}',
            ],
            'sorting by time is the most recently changed first' => [
                '{{listpages sort="time"}}',
                '{{entrylist id="5" field="updated_at" order="desc"}}',
            ],
            'sorting by owner is a field like any other' => [
                '{{listpages sort="owner"}}',
                '{{entrylist id="5" field="owner"}}',
            ],
            'my own pages is a condition on a field' => [
                '{{listpages owner="owner"}}',
                '{{entrylist id="5" query="owner=[user.name]"}}',
            ],
            "somebody else's pages, likewise" => [
                '{{listpages owner="JeanDupont"}}',
                '{{entrylist id="5" query="owner=JeanDupont"}}',
            ],
            'a parameter this knows nothing about is passed through' => [
                '{{listpages class="my-class"}}',
                '{{entrylist id="5" class="my-class"}}',
            ],
            'and the spacing of a call is left as it was written' => [
                '{{ listpages }}',
                '{{ entrylist id="5"}}',
            ],
        ];
    }

    #[DataProvider('callProvider')]
    public function testTheCallIsRewritten(string $before, string $after): void
    {
        $this->assertSame(
            $after,
            (new ListpagesRewriter())->rewriteText($before, self::PAGES_FORM)
        );
    }

    /**
     * The three that cannot be carried over. Dropped, not left in place: a call nothing
     * answers renders an error where a list used to be. Every one is reported.
     */
    public function testWhatCannotBeExpressedIsDroppedAndNamed(): void
    {
        $rewriter = new ListpagesRewriter();

        $rewritten = $rewriter->rewriteText(
            '{{listpages exclude="PagePrincipale,BacASable" user="JeanDupont" sort="user"}}',
            self::PAGES_FORM
        );

        $this->assertSame('{{entrylist id="5"}}', $rewritten);
        $this->assertSame(
            ['sort="user"', 'user="JeanDupont"', 'exclude="PagePrincipale,BacASable"'],
            $rewriter->droppedParameters()
        );
    }

    /** Prose is not a call, and neither is another action's parameter value. */
    public function testOnlyCallsAreRewritten(): void
    {
        $text = "The listpages action is retired.\n"
            . '{{entrylist id="5" template="listpages.twig"}}';

        $this->assertSame(
            $text,
            (new ListpagesRewriter())->rewriteText($text, self::PAGES_FORM)
        );
    }

    /** A body is JSON, and a call can sit in any string in one. */
    public function testABodyIsWalkedRatherThanPatternMatchedAsJson(): void
    {
        $rewriter = new ListpagesRewriter();

        $body = $rewriter->rewriteBody(
            [
                'title' => 'Toutes les pages',
                'content' => "Voici la liste :\n{{listpages sort=\"time\"}}",
                'bf_description' => 'rien à réécrire ici',
            ],
            self::PAGES_FORM
        );

        $this->assertNotNull($body);
        $this->assertSame(
            "Voici la liste :\n{{entrylist id=\"5\" field=\"updated_at\" order=\"desc\"}}",
            $body['content']
        );
        $this->assertSame('rien à réécrire ici', $body['bf_description']);
    }

    /** Nothing to do is said as null, so the caller can skip the write entirely. */
    public function testABodyWithNoCallIsNotRewritten(): void
    {
        $this->assertNull(
            (new ListpagesRewriter())->rewriteBody(['content' => 'du texte'], self::PAGES_FORM)
        );
    }
}
