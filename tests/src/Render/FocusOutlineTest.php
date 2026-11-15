<?php

namespace YesWiki\Test\Render;

use PHPUnit\Framework\TestCase;

/** No stylesheet takes the focus outline away without something visible in its place (end-users 08). */
class FocusOutlineTest extends TestCase
{
    /** Selectors that remove the outline where another rule draws the focus: a form that never takes it, and inputs whose container shows it with :focus-within. */
    private const REPLACED_ELSEWHERE = [
        '#search .yeswiki-search-form',
        '.yw-searchbox__input:focus',
        '.yw-accordion__item:focus-within',
        '.yw-tag-input__search:focus',
    ];

    public function testEveryOutlineRemovedIsReplaced(): void
    {
        $offenders = [];
        foreach (glob(dirname(__DIR__, 3) . '/styles/*.css') ?: [] as $file) {
            $css = (string)preg_replace('~/\*.*?\*/~s', '', (string)file_get_contents($file));
            preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $rules, PREG_SET_ORDER);
            foreach ($rules as [, $selectors, $body]) {
                if (preg_match('/outline\s*:\s*(none|0)\s*(;|$)/', $body) !== 1) {
                    continue;
                }
                foreach (array_map('trim', explode(',', $selectors)) as $selector) {
                    if (!str_contains($selector, ':not(:focus-visible)') && !in_array($selector, self::REPLACED_ELSEWHERE, true)) {
                        $offenders[] = basename($file) . ': ' . $selector;
                    }
                }
            }
        }

        $this->assertSame([], $offenders, 'These rules hide the focus from keyboard users. Keep the outline, or write the selector with :not(:focus-visible) and draw a ring on :focus-visible.');
    }
}
