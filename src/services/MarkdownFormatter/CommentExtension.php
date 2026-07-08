<?php

namespace YesWiki\Core\Formatter;

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

/**
 * Adds Twig-like comments: {# this is invisible once parsed #}. A line devoted only to a
 * comment (optionally spanning several blocks/paragraphs/headings/blank lines, until the
 * closing #} is found) disappears entirely; a comment amid other text on one paragraph is
 * simply removed inline. Either way nothing is rendered, including any {{action}} written
 * inside it - the whole span is swallowed before actions ever get a chance to parse it.
 */
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

        // does it also close on this same line, or does it keep going across blocks/lines?
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
        // a comment never renders anything
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
        // nothing is appended to the container: the comment is simply removed
        $inlineContext->getCursor()->advanceBy($inlineContext->getFullMatchLength());

        return true;
    }
}
