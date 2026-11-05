<?php

namespace YesWiki\Render\Formatter;

use League\CommonMark\Extension\Footnote\Parser\FootnoteParser;
use League\CommonMark\Parser\Block\BlockStart;
use League\CommonMark\Parser\Block\BlockStartParserInterface;
use League\CommonMark\Parser\Cursor;
use League\CommonMark\Parser\MarkdownParserStateInterface;
use League\CommonMark\Reference\Reference;

/** Starts a footnote definition on `[^1]:` or `[^1] :`, even right after a paragraph line. */
final class FootnoteDefinitionStartParser implements BlockStartParserInterface
{
    public function tryStart(Cursor $cursor, MarkdownParserStateInterface $parserState): ?BlockStart
    {
        if ($cursor->isIndented()) {
            return BlockStart::none();
        }

        if (preg_match('/^\[\^([^\s^\]]+)\] ?:(?:\s|$)/', $cursor->getRemainder(), $matches) !== 1) {
            return BlockStart::none();
        }

        $cursor->advanceBy(strlen($matches[0]));

        return BlockStart::of(new FootnoteParser(new Reference($matches[1], $matches[1], $matches[1])))->at($cursor);
    }
}
