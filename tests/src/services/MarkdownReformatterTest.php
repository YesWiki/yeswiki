<?php

namespace YesWiki\Test\Content\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YesWiki\Content\Service\MarkdownReformatter;

require_once 'tests/YesWikiTestCase.php';

/** Pages already in Markdown get the shape the editor reads, and nothing else moves. */
class MarkdownReformatterTest extends TestCase
{
    #[DataProvider('reformats')]
    public function testMarkdownIsReformatted(string $before, string $after): void
    {
        $this->assertSame($after, (new MarkdownReformatter())->reformat($before));
    }

    #[DataProvider('reformats')]
    public function testReformattingTwiceChangesNothing(string $before, string $after): void
    {
        $this->assertSame($after, (new MarkdownReformatter())->reformat($after));
    }

    /** @return array<string, array{string, string}> */
    public static function reformats(): array
    {
        return [
            'text after an opening tag' => [
                "{{section class=\"cover\"}}**C'est :**\n- un\n{{end elem=\"section\"}}",
                "{{section class=\"cover\"}}\n**C'est :**\n- un\n{{end elem=\"section\"}}",
            ],
            'text before a closing tag' => ['corps{{end elem="panel"}}', "corps\n{{end elem=\"panel\"}}"],
            'a whole section on one line' => [
                '{{section}}x{{end elem="section"}}',
                "{{section}}\nx\n{{end elem=\"section\"}}",
            ],
            'tags side by side' => ['{{end elem="col"}}{{col size="8"}}', "{{end elem=\"col\"}}\n{{col size=\"8\"}}"],
            'a leaf action after an opening tag stays with the text' => [
                '{{section}}{{button text="a" link="A"}} suite',
                "{{section}}\n{{button text=\"a\" link=\"A\"}} suite",
            ],
            'a label stays inline' => ['{{label}}x{{end elem="label"}}', '{{label}}x{{end elem="label"}}'],
            'a tag mid-sentence stays' => ['avant {{section}} après', 'avant {{section}} après'],
            'a list item keeps its tags' => [
                '- {{section}}{{button text="a" link="A"}}{{end elem="section"}}',
                '- {{section}}{{button text="a" link="A"}}{{end elem="section"}}',
            ],
            'an indented line keeps its tags' => ["- un\n  {{section}}x", "- un\n  {{section}}x"],
            'a quote keeps its tags' => ['> {{section}}x', '> {{section}}x'],
            'a fenced block is left alone' => [
                "```\n{{section}}x{{end elem=\"section\"}}\n```",
                "```\n{{section}}x{{end elem=\"section\"}}\n```",
            ],
            'a code span is left alone' => ['`{{section}}` x', '`{{section}}` x'],
            'a comment is left alone' => [
                "{# {{section}}x\n{{end elem=\"section\"}}y #}",
                "{# {{section}}x\n{{end elem=\"section\"}}y #}",
            ],
            'a tag after a comment still moves' => ['{# note #}{{grid}}', "{# note #}\n{{grid}}"],
            'blank lines in a row become one' => ["un\n\n\n\ndeux", "un\n\ndeux"],
            'a line of spaces is a blank line' => ["un\n  \n\t\ndeux", "un\n\ndeux"],
            'blank lines at either end go' => ["\n\nun\n\n", 'un'],
            'trailing spaces go' => ["un \ndeux\t\ntrois", "un\ndeux\ntrois"],
            'two trailing spaces are a line break and stay' => ["un  \ndeux", "un  \ndeux"],
            'more trailing spaces are the same break' => ["un    \ndeux", "un  \ndeux"],
            'a break before a blank line breaks nothing' => ["un  \n\ndeux", "un\n\ndeux"],
            'a break at the end breaks nothing' => ['un  ', 'un'],
            'spaces before a backslash break stay' => ["un  \\\ndeux", "un  \\\ndeux"],
            'a heading gets a blank line on each side' => ["avant\n## Titre\naprès", "avant\n\n## Titre\n\naprès"],
            'a heading under an action too' => ["{{section}}\n### Titre\n{{end elem=\"section\"}}", "{{section}}\n\n### Titre\n\n{{end elem=\"section\"}}"],
            'a hash without a space is no heading' => ["#motclé\nsuite", "#motclé\nsuite"],
            'an indented heading is left where it is' => ["- un\n  ## Titre\n- deux", "- un\n  ## Titre\n- deux"],
            'a heading inside a comment is left alone' => ["{# note\n## pas un titre\n#}", "{# note\n## pas un titre\n#}"],
            'a fence gets a blank line on each side' => ["avant\n```php\necho 1;\n```\naprès", "avant\n\n```php\necho 1;\n```\n\naprès"],
            'a fence keeps its blank lines and spaces' => ["```\na  \n\n\n\nb \n```", "```\na  \n\n\n\nb \n```"],
            'a pre block keeps its blank lines, spaces and tags' => [
                "avant\n<pre class=\"x\">\na  \n\n\n\n## b \n{{section}}x\n</pre>\naprès",
                "avant\n<pre class=\"x\">\na  \n\n\n\n## b \n{{section}}x\n</pre>\naprès",
            ],
            'blank lines before and after a pre block become one' => ["un\n\n\n<pre>x  </pre>\n\n\ndeux", "un\n\n<pre>x  </pre>\n\ndeux"],
            'a pre block left open runs to the end' => ["<pre>\na\n\n\nb ", "<pre>\na\n\n\nb "],
            'a pre in a code span is no pre block' => ["`<pre>`\n\n\n\nx", "`<pre>`\n\nx"],
            'a pre in a comment is no pre block' => ["{# <pre> #}\n\n\nx", "{# <pre> #}\n\nx"],
            'a tag starting with pre is no pre block' => ["<preview>\n\n\nx", "<preview>\n\nx"],
            'a fence left open runs to the end' => ["```\na\n\n\nb ", "```\na\n\n\nb "],
        ];
    }
}
