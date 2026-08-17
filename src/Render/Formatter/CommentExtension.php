<?php

namespace YesWiki\Render\Formatter;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Node\Block\AbstractBlock;
use League\CommonMark\Node\RawMarkupContainerInterface;
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

/** Adds Twig-like comments: {# this is invisible once parsed #}. */
final class CommentExtension implements ExtensionInterface
{
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addBlockStartParser(new CommentBlockStartParser());
        $environment->addInlineParser(new CommentInlineParser());
        $environment->addRenderer(CommentBlock::class, new RawHtmlNodeRenderer());
    }
}

final class CommentBlockStartParser implements BlockStartParserInterface
{
    public function tryStart(Cursor $cursor, MarkdownParserStateInterface $parserState): ?BlockStart
    {
        if ($cursor->isIndented() || $cursor->match('/^\{#/') === null) {
            return BlockStart::none();
        }

        $stillOpen = \strpos($cursor->getRemainder(), '#}') === false;
        $cursor->advanceToEnd();

        return BlockStart::of(new CommentBlockContinueParser($stillOpen))->at($cursor);
    }
}

final class CommentBlock extends AbstractBlock implements RawMarkupContainerInterface
{
    public function getLiteral(): string
    {
        return '';
    }

    public function setLiteral(string $literal): void
    {
    }
}

final class CommentBlockContinueParser extends AbstractBlockContinueParser
{
    private CommentBlock $block;
    private bool $stillOpen;

    public function __construct(bool $stillOpen)
    {
        $this->block = new CommentBlock();
        $this->stillOpen = $stillOpen;
    }

    public function getBlock(): CommentBlock
    {
        return $this->block;
    }

    public function tryContinue(Cursor $cursor, BlockContinueParserInterface $activeBlockParser): ?BlockContinue
    {
        if (!$this->stillOpen) {
            return BlockContinue::none();
        }

        if (\strpos($cursor->getLine(), '#}') !== false) {
            return BlockContinue::finished();
        }

        $cursor->advanceToEnd();

        return BlockContinue::at($cursor);
    }
}

final class CommentInlineParser implements InlineParserInterface
{
    public function getMatchDefinition(): InlineParserMatch
    {
        return InlineParserMatch::regex('(?s)\{#.*?#\}');
    }

    public function parse(InlineParserContext $inlineContext): bool
    {
        $inlineContext->getCursor()->advanceBy($inlineContext->getFullMatchLength());

        return true;
    }
}
