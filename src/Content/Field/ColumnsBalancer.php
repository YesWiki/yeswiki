<?php

namespace YesWiki\Content\Field;

/** Renders a run of fields so that columns blocks stay well-formed, whatever divs the custom HTML fields open or close. */
final class ColumnsBalancer
{
    public const BREAK = '</div></div><div class="yw-form-col"><div class="yw-form-col__body">';
    public const CLOSE = '</div></div></div>';

    private int $depth = 0;

    /** @var list<array{base: int, pendingEnd: bool}> */
    private array $frames = [];

    /** The opening markup of a columns block with that layout. */
    public static function open(string $layout): string
    {
        return '<div class="yw-form-columns yw-form-columns--' . $layout . '"><div class="yw-form-col"><div class="yw-form-col__body">';
    }

    /** Renders $field with $renderer, dropping or deferring column markers that would cross a div left open by another field. */
    public function render(BazarField $field, callable $renderer): string
    {
        if ($field instanceof ColumnsField) {
            $html = (string)$renderer($field);
            if ($html !== '') {
                $this->frames[] = ['base' => $this->depth, 'pendingEnd' => false];
            }

            return $html;
        }
        if ($field instanceof ColumnChangeField) {
            return $this->atFrameBase() ? self::BREAK : '';
        }
        if ($field instanceof ColumnsEndField) {
            if (empty($this->frames)) {
                return '';
            }
            if ($this->atFrameBase()) {
                array_pop($this->frames);

                return self::CLOSE;
            }
            $this->frames[array_key_last($this->frames)]['pendingEnd'] = true;

            return '';
        }

        $html = (string)$renderer($field);

        return $field instanceof LabelField ? $this->followDivs($html) : $html;
    }

    /** Closes whatever columns blocks are still open. */
    public function finish(): string
    {
        $html = '';
        while (!empty($this->frames)) {
            $frame = array_pop($this->frames);
            $html .= str_repeat('</div>', max(0, $this->depth - $frame['base'])) . self::CLOSE;
            $this->depth = $frame['base'];
        }

        return $html;
    }

    private function atFrameBase(): bool
    {
        return !empty($this->frames)
            && !end($this->frames)['pendingEnd']
            && end($this->frames)['base'] === $this->depth;
    }

    private function followDivs(string $html): string
    {
        if (!preg_match_all('#<div\b|</div\b#i', $html, $matches, PREG_OFFSET_CAPTURE)) {
            return $html;
        }
        $out = '';
        $cursor = 0;
        foreach ($matches[0] as [$tag, $offset]) {
            if ($tag[1] !== '/') {
                $this->depth++;
                continue;
            }
            if (!empty($this->frames) && end($this->frames)['base'] === $this->depth) {
                $out .= substr($html, $cursor, $offset - $cursor) . self::CLOSE;
                $cursor = $offset;
                array_pop($this->frames);
            }
            $this->depth = max(0, $this->depth - 1);
        }
        $out .= substr($html, $cursor);
        while (!empty($this->frames) && end($this->frames)['pendingEnd'] && end($this->frames)['base'] === $this->depth) {
            array_pop($this->frames);
            $out .= self::CLOSE;
        }

        return $out;
    }
}
