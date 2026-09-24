<?php

namespace YesWiki\Content\Service;

/** Rewrites Doryphore's wakka markup as the CommonMark Ectoplasme renders, keeping what each page showed. */
class LegacyMarkupConverter
{
    private const SLOT = "\x1A";
    private const BLANK = "\x01";
    private const SLOT_RE = '\x1A(\d+)\x1A';
    private const BLANK_RE = '\x01';

    private const BLOCK_TAGS = 'address|article|aside|blockquote|center|details|dialog|dir|div|dl|dt|dd|fieldset|figcaption|figure|footer|form|h[1-6]|header|hr|li|main|nav|ol|p|section|table|tbody|td|tfoot|th|thead|tr|ul|left|right';

    private const HEADING_LEVELS = [6 => 1, 5 => 2, 4 => 3, 3 => 4, 2 => 5];

    /** @var list<string> */
    private array $slots = [];

    /** The Markdown equivalent of one page's legacy markup. */
    public function convert(string $markup): string
    {
        $this->slots = [];
        $text = str_replace(["\r\n", "\r", self::SLOT, self::BLANK], ["\n", "\n", '', ''], $markup);
        $text = $this->protect($text);
        $text = $this->convertHeadings($text);
        $text = $this->convertInline($text);
        $text = $this->convertLines($text);
        $text = $this->settleBlankLines($text);

        return trim($this->restore($text), "\n");
    }

    /** Sets aside what must not be touched, converting the legacy delimiters on the way. */
    private function protect(string $text): string
    {
        return (string)preg_replace_callback(
            '/%%(?:\((\w+)\))?\n?(.*?)%%'
            . '|(?:^|(?<=\n))```[^\n]*\n.*?\n```(?=\n|$)'
            . '|""(.*?)""'
            . '|\{\{.*?\}\}'
            . '|\{#.*?#\}'
            . '|\[\[([^\]\n]*)\]\]'
            . '|!?\[[^\]\n]*\]\([^)\n]*\)(?:\{[^}\n]*\})?'
            . '|`[^`\n]+`'
            . '|\b[a-z][a-z0-9+.-]*:\/\/[^\s<>"\'\]\)\\\\]+'
            . '|<(script|style|pre|textarea)\b[^>]*>.*?<\/\5>'
            . '|<!--.*?-->'
            . '|<[a-z!\/][^>\n]*>/isu',
            function (array $match): string {
                $whole = $match[0];
                if (str_starts_with($whole, '%%')) {
                    return self::BLANK . $this->slot('```' . ($match[1] ?? '') . "\n" . rtrim($match[2] ?? '', "\n") . "\n```") . self::BLANK;
                }
                if (str_starts_with($whole, '""')) {
                    return $this->rawHtml($match[3] ?? '');
                }
                if (str_starts_with($whole, '[[')) {
                    return $this->slot($this->wikiLink($match[4] ?? ''));
                }
                if (str_starts_with($whole, '{#') && preg_match('/\{\{|\}\}|""|</', $whole) === 1) {
                    return self::BLANK . $this->slot($whole) . self::BLANK;
                }

                return $this->slot($whole);
            },
            $text
        );
    }

    /** The inside of `""…""`: kept as is, on its own paragraph when it opens or closes a block. */
    private function rawHtml(string $html): string
    {
        $isBlock = preg_match('/^\s*(?:<\/?(?:' . self::BLOCK_TAGS . ')\b[^>]*>\s*)+$/i', $html) === 1;

        return $isBlock
            ? self::BLANK . $this->slot(trim($html)) . self::BLANK
            : $this->slot($html);
    }

    /** `[[Target label]]` as a Markdown link; the remote include `[[|url]]` is left alone. */
    private function wikiLink(string $inside): string
    {
        if (str_starts_with($inside, '|')) {
            return '[[' . $inside . ']]';
        }
        $parts = preg_split('/\s+/', trim($inside), 2) ?: [''];
        $target = $parts[0];
        $label = trim($parts[1] ?? '') !== '' ? trim($parts[1]) : $target;

        return '[' . $label . '](' . $target . ')';
    }

    /** `======Titre======` as `# Titre` on a line of its own; a heading wakka let run over several lines keeps only its first. */
    private function convertHeadings(string $text): string
    {
        $heading = function (array $m): string {
            $lines = explode("\n", trim($m[2]));
            $first = self::BLANK . str_repeat('#', self::HEADING_LEVELS[strlen($m[1])]) . ' ' . trim(array_shift($lines)) . self::BLANK;

            return $lines === [] ? $first : $first . implode("\n", $lines) . self::BLANK;
        };
        $text = (string)preg_replace_callback('/(={2,6})([^=\n](?:[^\n]*?[^=\n])?)={2,6}/u', $heading, $text);

        return (string)preg_replace_callback('/(?<!=)(={2,6})([^=\n][^=]*?[^=\s])\1(?!=)/u', $heading, $text);
    }

    /** The inline markers whose meaning differs, and wakka's two uses of dashes. */
    private function convertInline(string $text): string
    {
        $pairs = [
            '\/\/' => ['*', '*'],
            '\*\*' => ['**', '**'],
            '__' => ['<u>', '</u>'],
            '@@' => ['~~', '~~'],
            '££' => ['<ins>', '</ins>'],
        ];
        foreach ($pairs as $marker => [$open, $close]) {
            $text = (string)preg_replace_callback(
                '/' . $marker . '(?=\S)((?:(?!\n\s*\n).)+?)(?<=\S)' . $marker . '/su',
                fn (array $m): string => $this->emphasiseEachLine($m[1], $open, $close),
                $text
            );
        }
        $rules = [
            '/##(?=\S)([^#\n]+?)##/u' => '`$1`',
            '/(?<!-)-{4,}(?!-)/' => self::BLANK . '----' . self::BLANK,
            '/(?<!-)---(?!-)/' => '<br>',
        ];
        foreach ($rules as $pattern => $replacement) {
            $text = (string)preg_replace($pattern, $replacement, $text);
        }

        return $text;
    }

    /** Emphasis wakka carried across lines, applied to each line since Markdown's stops at a line that starts a list item. */
    private function emphasiseEachLine(string $inside, string $open, string $close): string
    {
        $lines = explode("\n", $inside);
        foreach ($lines as $i => $line) {
            if (trim($line) === '' || preg_match('/^(\s*(?:[-*]|\d+[.)]|[a-zA-Z]\))\s+)?(.*?)(\s*)$/su', $line, $m) !== 1) {
                continue;
            }
            $lines[$i] = $m[1] . $open . $m[2] . $close . $m[3];
        }

        return implode("\n", $lines);
    }

    /** Lists, indentation and line breaks, which wakka read line by line. */
    private function convertLines(string $text): string
    {
        $out = [];
        $isItem = [];
        foreach (explode("\n", $text) as $line) {
            if (preg_match('/^(\t+| +)(-|\*|\d+\)|[a-zA-Z]\)|[ivxIVX]+\))\s+(.*)$/u', $line, $m) === 1) {
                $marker = ($m[2] === '-' || $m[2] === '*') ? '-' : '1.';
                $out[] = str_repeat('  ', max(0, strlen($m[1]) - 1)) . $marker . ' ' . $m[3];
                $isItem[] = true;
                continue;
            }
            $out[] = ltrim($line, " \t");
            $isItem[] = preg_match('/^(-|\d+\.)\s/', ltrim($line)) === 1;
        }

        $result = [];
        foreach ($out as $i => $line) {
            $next = $out[$i + 1] ?? null;
            $joined = $next !== null && trim($next) !== '' && !str_ends_with(rtrim($line), self::BLANK) && !str_starts_with(ltrim($next), self::BLANK);
            $opensHtmlBlock = $joined && $this->opensHtmlBlock($line);
            if ($joined && !$opensHtmlBlock && !$isItem[$i] && !$isItem[$i + 1] && $this->isProse($line) && $this->isProse($next) && !str_ends_with(rtrim($line), '\\')) {
                $line .= '\\';
            }
            $result[] = $line;
            if ($opensHtmlBlock || ($next !== null && $isItem[$i] && !$isItem[$i + 1] && $this->isProse($next))) {
                $result[] = '';
            }
        }

        return implode("\n", $result);
    }

    /** Whether a line is running text, the only kind wakka's hard line breaks have to be kept for. */
    private function isProse(string $line): bool
    {
        $bare = trim(str_replace(self::BLANK, '', $line));
        if ($bare === '') {
            return false;
        }
        if (preg_match('/^(#{1,6}\s|>|\||<br>$)/u', $bare) === 1) {
            return false;
        }

        return !(preg_match('/^(' . self::SLOT_RE . '\s*)+$/', $bare) === 1 && $this->onlyActions($bare));
    }

    /** Whether CommonMark would open an HTML block on this line, which then swallows every following line up to a blank one. */
    private function opensHtmlBlock(string $line): bool
    {
        $bare = ltrim(str_replace(self::BLANK, '', $line));
        if (preg_match('/^' . self::SLOT_RE . '/', $bare, $m) !== 1) {
            return false;
        }

        return preg_match('/^<(?:!--|\/?(?:script|style|pre|textarea|' . self::BLOCK_TAGS . ')\b)/i', $this->slots[(int)$m[1]]) === 1;
    }

    /** Whether a line holds nothing but set-aside actions, comments or block HTML. */
    private function onlyActions(string $bare): bool
    {
        preg_match_all('/' . self::SLOT_RE . '/', $bare, $found);
        foreach ($found[1] as $index) {
            $slot = $this->slots[(int)$index];
            if (!preg_match('/^(\{\{|\{#|<!--|<\/?(?:script|style|pre|textarea|iframe)\b|<\/?(?:' . self::BLOCK_TAGS . ')\b|```)/i', $slot)) {
                return false;
            }
        }

        return true;
    }

    /** Turns the blank-line requests into exactly one blank line. */
    private function settleBlankLines(string $text): string
    {
        return trim((string)preg_replace('/\s*' . self::BLANK_RE . '(?:\s*' . self::BLANK_RE . ')*\s*/u', "\n\n", $text), "\n");
    }

    private function slot(string $kept): string
    {
        $this->slots[] = $kept;

        return self::SLOT . (count($this->slots) - 1) . self::SLOT;
    }

    private function restore(string $text): string
    {
        return (string)preg_replace_callback(
            '/' . self::SLOT_RE . '/',
            fn (array $m): string => $this->slots[(int)$m[1]],
            $text
        );
    }
}
