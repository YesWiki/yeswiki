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

/** HedgeDoc-style alerts: a `:::info` fence, closed by `:::`. */
final class AlertExtension implements ExtensionInterface
{
    /** The types this wiki has styles for. */
    public const TYPES = ['success', 'info', 'warning', 'danger'];

    public function register(EnvironmentBuilderInterface $environment): void
    {
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
        if (!$cursor->isIndented() && preg_match('/^:::[ \t]*$/', $cursor->getLine()) === 1) {
            $cursor->advanceToEnd();

            return BlockContinue::finished();
        }

        return BlockContinue::at($cursor);
    }
}
