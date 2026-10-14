<?php

namespace YesWiki\Content\Service;

/** Rewrites page Markdown into the shape the editor reads, without changing what it renders. */
class MarkdownReformatter
{
    private const BLOCK_ACTIONS = 'section|grid|col|panel|accordion|tabs|tab|buttondropdown';
    private const TAG = '\{\{(?:(?!\}\}).)*\}\}';
    private const FENCE = '/^ {0,3}(`{3,}|~{3,})/';
    private const PRE_CLOSE = '/<\/pre\s*>/i';

    /** The same Markdown with block action tags on lines of their own, one blank line around headings and fences and between blocks, and no stray trailing spaces; code and pre blocks are left as they are. */
    public function reformat(string $markdown): string
    {
        return $this->join($this->lines($markdown));
    }

    /** @return list<array{text: string, code: bool, before: bool, after: bool}> each line, whether it is code, and whether it wants a blank line before or after it */
    private function lines(string $markdown): array
    {
        $lines = [];
        $fence = null;
        $inComment = false;
        $inPre = false;
        foreach (explode("\n", $markdown) as $line) {
            $flush = $line !== '' && $line[0] !== ' ';
            if ($inPre) {
                $inPre = preg_match(self::PRE_CLOSE, $line) !== 1;
                $lines[] = ['text' => $line, 'code' => true, 'before' => false, 'after' => false];
                continue;
            }
            if ($fence !== null) {
                $closes = preg_match(self::FENCE, $line, $m) === 1 && $m[1][0] === $fence[0] && strlen($m[1]) >= strlen($fence)
                    && trim(substr(ltrim($line), strlen($m[1]))) === '';
                $fence = $closes ? null : $fence;
                $lines[] = ['text' => $line, 'code' => !$closes, 'before' => false, 'after' => $closes && $flush];
                continue;
            }
            if (!$inComment && preg_match(self::FENCE, $line, $m) === 1) {
                $fence = $m[1];
                $lines[] = ['text' => $line, 'code' => false, 'before' => $flush, 'after' => false];
                continue;
            }
            $wasInComment = $inComment;
            $visible = $this->hideCodeAndComments($line, $inComment);
            if (preg_match('/<pre\b/i', $visible, $m, PREG_OFFSET_CAPTURE) === 1) {
                $inPre = preg_match(self::PRE_CLOSE, substr($visible, $m[0][1])) !== 1;
                $lines[] = ['text' => $line, 'code' => true, 'before' => false, 'after' => false];
                continue;
            }
            foreach ($this->isolateBlockActions($line, $visible) as $part) {
                $heading = preg_match('/^#{1,6}(?:[ \t]|$)/', $part) === 1;
                $lines[] = ['text' => $part, 'code' => false, 'before' => $heading && !$wasInComment, 'after' => $heading && !$inComment];
            }
        }

        return $lines;
    }

    /** @param list<array{text: string, code: bool, before: bool, after: bool}> $lines */
    private function join(array $lines): string
    {
        $spaced = [];
        $blank = false;
        foreach ($lines as $line) {
            if ($line['code']) {
                if ($blank && !$spaced[count($spaced) - 1][1]) {
                    $spaced[] = ['', false];
                }
                $spaced[] = [$line['text'], true];
                $blank = false;
                continue;
            }
            if (trim($line['text']) === '') {
                $blank = $spaced !== [];
                continue;
            }
            if ($spaced !== [] && ($blank || $line['before'])) {
                $spaced[] = ['', false];
            }
            $spaced[] = [$line['text'], false];
            $blank = $line['after'];
        }

        $text = [];
        foreach ($spaced as $i => [$line, $code]) {
            if ($code || preg_match('/[ \t]+$/', $line, $m) !== 1) {
                $text[] = $line;
                continue;
            }
            $kept = substr($line, 0, -strlen($m[0]));
            $breaks = preg_match('/ {2,}$/', $m[0]) === 1 && trim($spaced[$i + 1][0] ?? '') !== '';
            $text[] = $breaks ? $kept . '  ' : $kept;
        }

        return implode("\n", $text);
    }

    /** The line with its code spans and comments blanked out, so that their tags are never moved. */
    private function hideCodeAndComments(string $line, bool &$inComment): string
    {
        $rest = (string)preg_replace_callback('/(`+).*?\1/', fn (array $m): string => str_repeat(' ', strlen($m[0])), $line);
        $out = '';
        while ($rest !== '') {
            $at = strpos($rest, $inComment ? '#}' : '{#');
            if ($at === false) {
                $out .= $inComment ? str_repeat(' ', strlen($rest)) : $rest;
                break;
            }
            $out .= $inComment ? str_repeat(' ', $at + 2) : substr($rest, 0, $at) . '  ';
            $rest = substr($rest, $at + 2);
            $inComment = !$inComment;
        }

        return $out;
    }

    /** @return list<string> the line, split around each block action tag when one opens or closes it */
    private function isolateBlockActions(string $line, string $visible): array
    {
        if (preg_match('/^\s/', $line) === 1 || preg_match('/^(?:[-*+>]|\d+[.)])(?:\s|$)/', $line) === 1) {
            return [$line];
        }
        preg_match_all('/' . self::TAG . '/', $visible, $tags, PREG_OFFSET_CAPTURE);
        $cuts = [];
        foreach ($tags[0] as [$tag, $offset]) {
            if ($this->isBlockAction($tag)) {
                $cuts[] = [$offset, $offset + strlen($tag)];
            }
        }
        $opens = $cuts !== [] && $cuts[0][0] === 0;
        $closes = $cuts !== [] && end($cuts)[1] === strlen(rtrim($visible));
        if (!$opens && !$closes) {
            return [$line];
        }
        $parts = [];
        $from = 0;
        foreach ($cuts as [$start, $end]) {
            $parts[] = substr($line, $from, $start - $from);
            $parts[] = substr($line, $start, $end - $start);
            $from = $end;
        }
        $parts[] = substr($line, $from);

        return array_values(array_filter(array_map('trim', $parts), fn (string $part): bool => $part !== ''));
    }

    private function isBlockAction(string $tag): bool
    {
        return preg_match('/^\{\{\s*(?:end\s+elem\s*=\s*["\']?)?(?:' . self::BLOCK_ACTIONS . ')(?![\w-])/i', $tag) === 1;
    }
}
