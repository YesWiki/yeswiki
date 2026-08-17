<?php

namespace YesWiki\Test\Content;

use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Content\Service\ActionCallRewriter;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Ticket 33's rewriter. */
#[CoversMethod(ActionCallRewriter::class, 'rewriteText')]
#[CoversMethod(ActionCallRewriter::class, 'rewriteBody')]
class ActionCallRewriterTest extends YesWikiTestCase
{
    private function rewriter(): ActionCallRewriter
    {
        return $this->getWiki()->services->get(ActionCallRewriter::class);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function rewriteProvider(): array
    {
        return [
            'a bare action' => ['{{bazarliste}}', '{{entrylist}}'],
            'action with parameters' => [
                '{{bazarliste id="3" champ="bf_titre" ordre="desc"}}',
                '{{entrylist id="3" field="bf_titre" order="desc"}}',
            ],
            'spacing is preserved' => ['{{ bazarliste }}', '{{ entrylist }}'],
            'the slash form' => ['{{bazarliste/id="3"}}', '{{entrylist/id="3"}}'],
            'across newlines' => [
                "{{bazarliste\n  champ=\"bf_titre\"\n}}",
                "{{entrylist\n  field=\"bf_titre\"\n}}",
            ],

            'a template value naming the old action survives' => [
                '{{moteurrecherche template="moteurrecherche_button.twig"}}',
                '{{searchform template="moteurrecherche_button.twig"}}',
            ],
            'a value that happens to be an old action name survives' => [
                '{{entrylist template="bazarliste.twig" field="bazarliste"}}',
                '{{entrylist template="bazarliste.twig" field="bazarliste"}}',
            ],

            'prose mentioning an action is untouched' => [
                'To list entries, use the bazarliste action with champ="x".',
                'To list entries, use the bazarliste action with champ="x".',
            ],
            'a url is untouched' => [
                'See https://example.org/doc?action=bazarliste for details.',
                'See https://example.org/doc?action=bazarliste for details.',
            ],

            'bazar keeps its name but renames its parameters' => [
                '{{bazar voirmenu="1" vue="consulter"}}',
                '{{bazar showmenu="1" view="consulter"}}',
            ],
            'a parameter value is not a view name to translate' => [
                '{{bazar vue="consulter"}}',
                '{{bazar view="consulter"}}',
            ],

            'a parameter is scoped to its own action' => [
                '{{nav champ="bf_titre"}}',
                '{{nav champ="bf_titre"}}',
            ],
            'the same parameter name on two actions both move' => [
                '{{valeur champ="bf_x" texte="lien" defaut="-"}}',
                '{{value field="bf_x" text="lien" default="-"}}',
            ],

            'doubleclic as an action' => ['{{doubleclic}}', '{{doubleclick}}'],
            'doubleclic as a parameter of include' => [
                '{{include page="X" doubleclic="0" actif="1"}}',
                '{{include page="X" doubleclick="0" active="1"}}',
            ],

            'an end tag' => ['{{end elem="panel"}}', '{{end elem="panel"}}'],
            'an empty tag' => ['{{ }}', '{{ }}'],
            'a parameter never typed by a user is left alone' => [
                '{{bazarliste nbbazarliste="2"}}',
                '{{entrylist nbbazarliste="2"}}',
            ],

            'several calls in one body' => [
                "{{titrepage}}\n{{ariane}}\n{{bazarliste id=\"1\" nbcol=\"3\"}}",
                "{{pagetitle}}\n{{breadcrumb}}\n{{entrylist id=\"1\" columns=\"3\"}}",
            ],
        ];
    }

    #[DataProvider('rewriteProvider')]
    public function testRewriteText(string $before, string $expected): void
    {
        $this->assertSame($expected, $this->rewriter()->rewriteText($before));
    }

    /** Every rename in both maps, applied and checked. */
    public function testEveryRenameInTheMapsIsApplied(): void
    {
        $rewriter = $this->rewriter();

        $names = json_decode((string)file_get_contents(YESWIKI_SOURCE_DIR . '/docs/action-name-renames.json'), true);
        foreach ($names['renames'] as $rename) {
            $this->assertSame(
                '{{' . $rename['new'] . '}}',
                $rewriter->rewriteText('{{' . $rename['old'] . '}}'),
                "action {$rename['old']} should become {$rename['new']}"
            );
        }

        $params = json_decode((string)file_get_contents(YESWIKI_SOURCE_DIR . '/docs/action-parameter-renames.json'), true);
        foreach ($params['renames'] as $rename) {
            if (!isset($rename['action']) || empty($rename['userTyped'])) {
                continue;
            }
            $action = $rename['action'];

            $expectedAction = $rewriter->actionRenames()[strtolower($action)] ?? $action;
            $this->assertSame(
                '{{' . $expectedAction . ' ' . $rename['new'] . '="v"}}',
                $rewriter->rewriteText('{{' . $action . ' ' . $rename['old'] . '="v"}}'),
                "{$action}'s parameter {$rename['old']} should become {$rename['new']}"
            );
        }
    }

    /** The ordering hazard, pinned. */
    public function testParametersOfARenamedActionAreStillRewritten(): void
    {
        $rewriter = $this->rewriter();

        foreach ([
            '{{bazarcarto zoommolette="1"}}' => '{{entrymap scrollwheelzoom="1"}}',
            '{{bazarexport idtypeannonce="2"}}' => '{{entryexport id="2"}}',
            '{{bazarimport idtypeannonce="2"}}' => '{{entryimport id="2"}}',
            '{{calendrier minical="1"}}' => '{{calendar minicalendar="1"}}',
            '{{nuagetag nbclasses="4" tri="alpha"}}' => '{{tagcloud classcount="4" sort="alpha"}}',
            '{{gererdroits}}' => '{{adminacls}}',
            '{{gererthemes}}' => '{{adminthemes}}',
        ] as $before => $expected) {
            $this->assertSame($expected, $rewriter->rewriteText($before), "failed on {$before}");
        }
    }

    public function testRewritingIsIdempotent(): void
    {
        $rewriter = $this->rewriter();
        $source = '{{bazarliste id="1" champ="bf_titre" nbcol="3"}} {{bazar voirmenu="1"}} {{valeur champ="x"}}';

        $once = $rewriter->rewriteText($source);
        $this->assertSame($once, $rewriter->rewriteText($once), 'a second pass must change nothing');
        $this->assertSame($once, $rewriter->rewriteText($rewriter->rewriteText($once)));
    }

    public function testRewriteBodyWalksNestedStringsAndReportsNoChange(): void
    {
        $rewriter = $this->rewriter();

        $this->assertNull(
            $rewriter->rewriteBody(['content' => 'nothing to do here', 'n' => 3]),
            'an unchanged body must report null so the caller can skip the write'
        );

        $changed = $rewriter->rewriteBody([
            'content' => '{{bazarliste id="1"}}',
            'fields' => [['form_text' => '{{titrepage}}'], ['form_text' => 'plain']],
            'count' => 7,
        ]);

        $this->assertNotNull($changed);
        $this->assertSame('{{entrylist id="1"}}', $changed['content']);
        $this->assertSame('{{pagetitle}}', $changed['fields'][0]['form_text']);
        $this->assertSame('plain', $changed['fields'][1]['form_text']);
        $this->assertSame(7, $changed['count'], 'non-string values must survive the walk unchanged');
    }

    /** The needles only narrow the SQL sweep, so they must cover everything the rewriter can act on. */
    public function testCandidateNeedlesCoverEveryRename(): void
    {
        $rewriter = $this->rewriter();
        $needles = $rewriter->candidateNeedles();

        foreach (array_keys($rewriter->actionRenames()) as $old) {
            $this->assertContains($old, $needles, "action {$old} would never be found by the sweep");
        }

        $params = json_decode((string)file_get_contents(YESWIKI_SOURCE_DIR . '/docs/action-parameter-renames.json'), true);
        foreach ($params['renames'] as $rename) {
            if (!isset($rename['action']) || empty($rename['userTyped'])) {
                continue;
            }
            $this->assertContains(
                strtolower($rename['old']),
                $needles,
                "parameter {$rename['old']} would never be found by the sweep"
            );
        }
    }
}
