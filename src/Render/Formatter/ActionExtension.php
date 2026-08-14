<?php

namespace YesWiki\Render\Formatter;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Node\Block\AbstractBlock;
use League\CommonMark\Node\Inline\AbstractStringContainer;
use League\CommonMark\Node\Node;
use League\CommonMark\Node\RawMarkupContainerInterface;
use League\CommonMark\Node\StringContainerInterface;
use League\CommonMark\Parser\Block\AbstractBlockContinueParser;
use League\CommonMark\Parser\Block\BlockContinue;
use League\CommonMark\Parser\Block\BlockContinueParserInterface;
use League\CommonMark\Parser\Block\BlockStart;
use League\CommonMark\Parser\Block\BlockStartParserInterface;
use League\CommonMark\Parser\Cursor;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;
use League\CommonMark\Parser\MarkdownParserStateInterface;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\RegexHelper;

/**
 * Adds support for the YesWiki action syntax, e.g. {{actionname param="one" param2="two"}}.
 * A line containing only an action becomes its own block (no wrapping <p>); an action found
 * amid other text is rendered inline. Both delegate to Wiki::Action(), which already parses
 * the action name and its param="value" attributes.
 *
 * Also makes standard Markdown links/images route through Wiki::LinkTo(), so internal wiki
 * links keep working (missing-page redirect to edit, link tracking, modalbox iframes, ...).
 */
class ActionExtension implements ExtensionInterface
{
    /** @var callable(string): string */
    private $renderAction;

    /** @var callable(string, string, ?string, array): string */
    private $renderLink;

    public function __construct(callable $renderAction, callable $renderLink)
    {
        $this->renderAction = $renderAction;
        $this->renderLink = $renderLink;
    }

    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addBlockStartParser(new ActionBlockStartParser($this->renderAction));
        $environment->addInlineParser(new ActionInlineParser($this->renderAction));
        $environment->addRenderer(ActionBlock::class, new RawHtmlNodeRenderer());
        $environment->addRenderer(ActionInline::class, new RawHtmlNodeRenderer());
        $environment->addRenderer(Link::class, new WikiLinkRenderer($this->renderLink), 10);
    }
}

final class ActionBlock extends AbstractBlock implements RawMarkupContainerInterface
{
    private string $html;

    public function __construct(string $html)
    {
        parent::__construct();
        $this->html = $html;
    }

    public function getLiteral(): string
    {
        return $this->html;
    }

    public function setLiteral(string $literal): void
    {
        $this->html = $literal;
    }
}

final class ActionInline extends AbstractStringContainer implements RawMarkupContainerInterface
{
}

final class RawHtmlNodeRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
    {
        if (!$node instanceof StringContainerInterface) {
            throw new \InvalidArgumentException('Incompatible node type: ' . \get_class($node));
        }

        return $node->getLiteral();
    }
}

final class WikiLinkRenderer implements NodeRendererInterface
{
    /** @var callable(string, string, ?string, array): string */
    private $renderLink;

    public function __construct(callable $renderLink)
    {
        $this->renderLink = $renderLink;
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
    {
        // `Link::assertInstanceOf($node)` said this at runtime and nothing at analysis time,
        // so getUrl()/getTitle() below sat baselined as method.notFound (ticket 40). Same
        // check, same exception, and it narrows.
        if (!$node instanceof Link) {
            throw new \InvalidArgumentException('Incompatible node type: expected ' . Link::class . ', got ' . $node::class);
        }

        $url = RegexHelper::isLinkPotentiallyUnsafe($node->getUrl()) ? '' : $node->getUrl();
        // populated by the Attributes extension from a trailing {.class #id key="value"}
        $attributes = $node->data->get('attributes');

        return ($this->renderLink)($url, $childRenderer->renderNodes($node->children()), $node->getTitle(), $attributes);
    }
}

/**
 * Recognizes a line containing nothing but {{action ...}} as its own block, so it doesn't
 * get wrapped in a <p> the way it would if left to inline parsing.
 */
final class ActionBlockStartParser implements BlockStartParserInterface
{
    /** @var callable(string): string */
    private $renderAction;

    public function __construct(callable $renderAction)
    {
        $this->renderAction = $renderAction;
    }

    public function tryStart(Cursor $cursor, MarkdownParserStateInterface $parserState): ?BlockStart
    {
        if ($cursor->isIndented()) {
            return BlockStart::none();
        }

        // ONE tag and nothing else on the line -- hence the tempered dot, which stops the
        // capture at the first `}}` rather than running to the last one on the line.
        //
        // `(.*)` was greedy, so a line holding a *pair* of tags matched as a single one:
        // `{{label}}texte{{end elem="label"}}` came out as the action `label}}texte{{end
        // elem="label"`, which the runner read as `label` and ran, silently eating both the
        // text between the tags and the closing tag. Six of those in a row -- the way
        // anyone writes a row of labels -- left six unclosed spans nesting the rest of the
        // document inside them. A pair written mid-sentence was always fine, because the
        // inline parser (below) handles that one, and it is where such a line belongs.
        if (!\preg_match('/^\{\{((?:(?!\}\}).)*)\}\}[ \t]*$/s', $cursor->getRemainder(), $matches) || \trim($matches[1]) === '') {
            return BlockStart::none();
        }

        $cursor->advanceToEnd();

        return BlockStart::of(new ActionBlockContinueParser(new ActionBlock(($this->renderAction)($matches[1]))))->at($cursor);
    }
}

final class ActionBlockContinueParser extends AbstractBlockContinueParser
{
    private ActionBlock $block;

    public function __construct(ActionBlock $block)
    {
        $this->block = $block;
    }

    public function getBlock(): ActionBlock
    {
        return $this->block;
    }

    public function tryContinue(Cursor $cursor, BlockContinueParserInterface $activeBlockParser): ?BlockContinue
    {
        // an action tag never spans more than one block-level line
        return BlockContinue::none();
    }
}

/**
 * Handles {{action ...}} found amid other inline content (not alone on its own line).
 */
final class ActionInlineParser implements InlineParserInterface
{
    /** @var callable(string): string */
    private $renderAction;

    public function __construct(callable $renderAction)
    {
        $this->renderAction = $renderAction;
    }

    public function getMatchDefinition(): InlineParserMatch
    {
        return InlineParserMatch::regex('(?s)\{\{(.*?)\}\}');
    }

    public function parse(InlineParserContext $inlineContext): bool
    {
        [$action] = $inlineContext->getSubMatches();
        if (\trim($action) === '') {
            // let {{}} fall through and render as literal text
            return false;
        }

        $inlineContext->getCursor()->advanceBy($inlineContext->getFullMatchLength());
        $inlineContext->getContainer()->appendChild(new ActionInline(($this->renderAction)($action)));

        return true;
    }
}
