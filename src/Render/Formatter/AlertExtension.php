<?php

namespace YesWiki\Render\Formatter;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Node\Block\AbstractBlock;
use League\CommonMark\Node\Node;
use League\CommonMark\Parser\Block\AbstractBlockContinueParser;
use League\CommonMark\Parser\Block\BlockContinue;
use League\CommonMark\Parser\Block\BlockContinueParserInterface;
use League\CommonMark\Parser\Block\BlockStart;
use League\CommonMark\Parser\Block\BlockStartParserInterface;
use League\CommonMark\Parser\Cursor;
use League\CommonMark\Parser\MarkdownParserStateInterface;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;

/**
 * HedgeDoc-style alerts: a `:::info` fence, closed by `:::`.
 *
 *     :::warning
 *     Do not do the thing.
 *     :::
 *
 * The four types are the four the wiki already styles (`yw-alert--success|info|warning|danger`,
 * styles/yw-core.css), which are also exactly HedgeDoc's four. **Anything else is left alone**
 * -- `:::note` stays the literal text it is, rather than becoming an alert wearing a class no
 * theme defines, which would render as an unstyled block and look like a bug in the page.
 *
 * A container, not a leaf: what is inside is ordinary markdown, so lists, links and even
 * `{{actions}}` work in there the way they do anywhere else. That is the point of having this
 * at all -- `{{panel}}` already draws a box, but it is an action, and you cannot type one
 * around three paragraphs you have already written.
 */
final class AlertExtension implements ExtensionInterface
{
    /** The types this wiki has styles for. */
    public const TYPES = ['success', 'info', 'warning', 'danger'];

    public function register(EnvironmentBuilderInterface $environment): void
    {
        // Below BlockQuote (70) and the fenced-code parsers: `:::` is not a character any of
        // them claim, so priority only decides order against other custom blocks. 60 keeps it
        // clear of the core block starts either way.
        $environment->addBlockStartParser(new AlertStartParser(), 60);
        $environment->addRenderer(Alert::class, new AlertRenderer());
    }
}

final class Alert extends AbstractBlock
{
    public function __construct(private readonly string $type)
    {
        parent::__construct();
    }

    public function getType(): string
    {
        return $this->type;
    }
}

final class AlertRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
    {
        if (!$node instanceof Alert) {
            throw new \InvalidArgumentException('Incompatible node type: ' . \get_class($node));
        }

        $attrs = $node->data->getData('attributes');
        $attrs->append('class', 'yw-alert yw-alert--' . $node->getType());
        // announced, not merely coloured: `danger` and `warning` are the two whose meaning is
        // lost entirely if the colour is
        if (in_array($node->getType(), ['warning', 'danger'], true)) {
            $attrs->set('role', 'alert');
        }

        return new HtmlElement('div', $attrs->export(), $childRenderer->renderNodes($node->children()));
    }
}

final class AlertStartParser implements BlockStartParserInterface
{
    public function tryStart(Cursor $cursor, MarkdownParserStateInterface $parserState): ?BlockStart
    {
        // an indented line is code, and `:::` inside a paragraph is text: only a fence on a
        // line of its own opens one
        if ($cursor->isIndented() || $parserState->getLastMatchedBlockParser()->getBlock() instanceof Alert === false
            && $parserState->getParagraphContent() !== null) {
            return BlockStart::none();
        }

        if (preg_match('/^:::[ \t]*([A-Za-z][A-Za-z0-9_-]*)[ \t]*$/', $cursor->getLine(), $match) !== 1) {
            return BlockStart::none();
        }

        $type = strtolower($match[1]);
        if (!in_array($type, AlertExtension::TYPES, true)) {
            return BlockStart::none();
        }

        $cursor->advanceToEnd();

        return BlockStart::of(new AlertParser($type))->at($cursor);
    }
}

final class AlertParser extends AbstractBlockContinueParser
{
    private Alert $block;

    public function __construct(string $type)
    {
        $this->block = new Alert($type);
    }

    public function getBlock(): AbstractBlock
    {
        return $this->block;
    }

    public function isContainer(): bool
    {
        return true;
    }

    public function canContain(AbstractBlock $childBlock): bool
    {
        return true;
    }

    public function tryContinue(Cursor $cursor, BlockContinueParserInterface $activeBlockParser): BlockContinue
    {
        // the closing fence, consumed so it never renders as content
        if (!$cursor->isIndented() && preg_match('/^:::[ \t]*$/', $cursor->getLine()) === 1) {
            $cursor->advanceToEnd();

            return BlockContinue::finished();
        }

        // Unclosed at end of input is not an error: the document ends, and so does the alert.
        // Refusing to close would drop everything after the opening fence.
        return BlockContinue::at($cursor);
    }
}
