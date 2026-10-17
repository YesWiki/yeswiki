<?php

namespace YesWiki\Test\Render;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YesWiki\Render\Twig\WikiActionSyntax;

/** The wiki's `{{action param="value"}}` becomes a Twig call, and nothing written in Twig changes. */
class WikiActionSyntaxTest extends TestCase
{
    public function testParametersBecomeAHash(): void
    {
        $this->assertSame(
            "<p>{{ renderAction('button', {'text': 'Ok', 'link': 'Page', 'data-x': 'a b'})|raw }}</p>",
            WikiActionSyntax::rewrite('<p>{{button text="Ok" link="Page" data-x="a b"}}</p>')
        );
    }

    public function testQuotesAndBackslashesStayInsideTheLiteral(): void
    {
        $this->assertSame(
            "{{ renderAction('button', {'text': 'l\\'été \\\\ ici'})|raw }}",
            WikiActionSyntax::rewrite('{{button text="l\'été \\ ici"}}')
        );
    }

    public function testSeveralCallsOnOneLine(): void
    {
        $this->assertSame(
            "{{ renderAction('label', {'class': 'a'})|raw }}x{{ closeAction('label')|raw }}",
            WikiActionSyntax::rewrite('{{label class="a"}}x{{end elem="label"}}')
        );
    }

    public function testACallSpanningLinesKeepsTheLinesThatFollowInPlace(): void
    {
        $source = "{{button\n  text=\"Ok\"\n  link=\"Page\"}}\n{{ oops(";
        $rewritten = WikiActionSyntax::rewrite($source);

        $this->assertSame(substr_count($source, "\n"), substr_count($rewritten, "\n"));
        $this->assertSame(4, WikiActionSyntax::calls("a\n\n\n{{button text=\"Ok\"}}")[0]['line']);
    }

    public function testASlashMakesACallWithoutParameters(): void
    {
        $this->assertSame("{{ renderAction('yeswikiversion')|raw }}", WikiActionSyntax::rewrite('{{yeswikiversion /}}'));
    }

    public function testABareNameIsTheVariableWhenThereIsOneAndTheActionOtherwise(): void
    {
        $this->assertSame(
            "{% if yeswikiversion is defined %}{{ yeswikiversion }}{% else %}{{ optionalAction('yeswikiversion')|raw }}{% endif %}",
            WikiActionSyntax::rewrite('{{yeswikiversion}}')
        );
    }

    public function testEndWithoutElemStaysTheAction(): void
    {
        $this->assertSame("{{ renderAction('end', {'class': 'x'})|raw }}", WikiActionSyntax::rewrite('{{end class="x"}}'));
    }

    /** @return array<string, array{string}> */
    public static function twigLeftAlone(): array
    {
        return [
            'spaced variable' => ['{{ label }}'],
            'trimmed variable' => ['{{-label-}}'],
            'reserved word' => ['{{true}}{{null}}{{_self}}'],
            'subtraction' => ['{{a-b}}'],
            'attribute' => ['{{entry.title}}'],
            'string in a function call' => ['{{ format(\'{{include page="Menu"}}\') }}'],
            'string in a tag' => ['{% set a = "{{button text=\\"Ok\\"}}" %}'],
            'hash closing on braces' => ['{{ {\'a\': {\'b\': 1}}|json_encode }}{{x}}', '{{x}}'],
            'interpolation' => ['{{ "#{ \'}}\' }{{button text=\\"Ok\\"}}" }}'],
            'comment' => ['{# {{button text="Ok"}} #}'],
            'verbatim block' => ['{% verbatim %}{{button text="Ok"}}{% endverbatim %}'],
            'raw block' => ['{%- raw -%}{{label}}{%- endraw -%}'],
            'number' => ['{{1}}'],
        ];
    }

    #[DataProvider('twigLeftAlone')]
    public function testTwigConstructsAreLeftAlone(string $source, string $onlyBare = ''): void
    {
        $calls = WikiActionSyntax::calls($source);

        $this->assertSame($onlyBare === '' ? [] : [$onlyBare], array_map(fn (array $call): string => substr($source, $call['offset'], $call['length']), $calls));
    }
}
