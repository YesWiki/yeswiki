<?php

namespace YesWiki\Core\Formatter;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Node\Inline\AbstractStringContainer;
use League\CommonMark\Node\Node;
use League\CommonMark\Node\RawMarkupContainerInterface;
use League\CommonMark\Node\StringContainerInterface;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;

/**
 * Adds support for [NN%] (0-100), rendered as a <progress> bar, e.g.
 * [50%] => <progress class="yw-progressbar" value="50" max="100">50%</progress>
 */
final class ProgressExtension implements ExtensionInterface
{
    public function register(EnvironmentBuilderInterface $environment): void
    {
        // higher than OpenBracketParser's 20, so [50%] is claimed whole before "[" is
        // treated as the start of a link/image
        $environment->addInlineParser(new ProgressInlineParser(), 25);
        $environment->addRenderer(ProgressInline::class, new ProgressInlineRenderer());
    }
}

final class ProgressInline extends AbstractStringContainer implements RawMarkupContainerInterface
{
}

final class ProgressInlineRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
    {
        if (!$node instanceof StringContainerInterface) {
            throw new \InvalidArgumentException('Incompatible node type: ' . \get_class($node));
        }

        return $node->getLiteral();
    }
}

final class ProgressInlineParser implements InlineParserInterface
{
    public function getMatchDefinition(): InlineParserMatch
    {
        return InlineParserMatch::regex('\[(100|[0-9]{1,2})%\]');
    }

    public function parse(InlineParserContext $inlineContext): bool
    {
        [$percent] = $inlineContext->getSubMatches();

        $inlineContext->getCursor()->advanceBy($inlineContext->getFullMatchLength());
        $inlineContext->getContainer()->appendChild(new ProgressInline(
            "<progress class=\"yw-progressbar\" value=\"{$percent}\" max=\"100\">{$percent}%</progress>"
        ));

        return true;
    }
}
