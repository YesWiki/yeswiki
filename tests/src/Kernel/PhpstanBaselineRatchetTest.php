<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\TestCase;

/**
 * A ceiling on the PHPStan baseline: each kind of suppression may fall, never rise.
 *
 * The baseline already catches *stale* entries by itself -- `ignore.unmatched` fires when a
 * baselined error stops occurring, which is how ticket 39's module split found seven at once.
 * What it has never had is a ceiling. A new file could be written, baselined and merged, and
 * the number only went up: 3,398 when this was written.
 *
 * ## Why per identifier, and not one total
 *
 * 82% of the baseline is `missingType.*` -- annotation debt with no behaviour in it. The rest
 * are assertions that the code is *wrong*, and those are where defects hide: ticket 35 found a
 * feature dead on arrival behind an `if.alwaysFalse`, and ticket 40 found
 * `ThemeSelectorRenderer::prepareBackgrounds()` fatally instantiating a class in the wrong
 * namespace behind nine `class.notFound`s.
 *
 * A single total would let 2,772 annotations fall by one and pay for a new `class.notFound`.
 * They are not the same currency, so they are not counted in the same purse.
 *
 * ## Lowering a number
 *
 * Required, not optional: the counts are asserted exactly, so converting a call site means
 * editing this file. That is the same deliberate friction `EscapeRatchetTest` uses -- a ceiling
 * nobody has to touch stops describing the code within a month.
 *
 * A new identifier that is not on this list at all fails too: adding a kind of suppression the
 * codebase has never had is exactly the event worth noticing.
 */
class PhpstanBaselineRatchetTest extends TestCase
{
    private const BASELINE = __DIR__ . '/../../../phpstan/baseline.neon';

    /**
     * Suppressions per error identifier. Lower these; never raise them.
     *
     * @var array<string, int>
     */
    private const CEILING = [
        // ---- annotation debt: mechanical, no behaviour in it ----
        'missingType.return' => 886,
        'missingType.parameter' => 704,
        'missingType.iterableValue' => 573,
        'missingType.property' => 462,
        'missingType.generics' => 7,

        // ---- claims that the code is wrong: burn these first (ticket 40) ----
        'argument.type' => 127,
        'offsetAccess.notFound' => 33,
        'function.alreadyNarrowedType' => 22,
        'method.alreadyNarrowedType' => 11,
        'method.nonObject' => 11,
        'empty.variable' => 9,
        'offsetAccess.nonOffsetAccessible' => 8,
        'property.notFound' => 8,
        'nullCoalesce.expr' => 6,
        'nullCoalesce.offset' => 6,
        'foreach.nonIterable' => 5,
        'method.unused' => 5,
        'nullCoalesce.variable' => 5,
        'empty.offset' => 4,
        'isset.variable' => 4,
        'return.phpDocType' => 4,
        'throws.notThrowable' => 4,
        'binaryOp.invalid' => 3,
        'booleanNot.alwaysFalse' => 3,
        'encapsedStringPart.nonString' => 3,
        'greater.alwaysTrue' => 3,
        'if.alwaysFalse' => 3,
        'return.unusedType' => 3,
        'booleanAnd.leftAlwaysTrue' => 2,
        'equal.alwaysFalse' => 2,
        'function.impossibleType' => 2,
        'instanceof.alwaysFalse' => 2,
        'instanceof.alwaysTrue' => 2,
        'isset.offset' => 2,
        'notEqual.alwaysTrue' => 2,
        'parameterByRef.type' => 2,
        'property.protected' => 2,
        'arguments.count' => 1,
        'assign.propertyType' => 1,
        'booleanAnd.rightAlwaysTrue' => 1,
        'booleanNot.alwaysTrue' => 1,
        'booleanOr.alwaysTrue' => 1,
        'callable.nonCallable' => 1,
        'cast.string' => 1,
        'catch.neverThrown' => 1,
        'elseif.alwaysTrue' => 1,
        'empty.expr' => 1,
        'foreach.emptyArray' => 1,
        'function.inner' => 1,
        'function.resultUnused' => 1,
        'identical.alwaysTrue' => 1,
        'if.alwaysTrue' => 1,
        'method.protected' => 1,
        'method.resultUnused' => 1,
        'notIdentical.alwaysFalse' => 1,
        'nullCoalesce.property' => 1,
        'property.onlyWritten' => 1,
        'property.private' => 1,
        'property.unused' => 1,
        'return.empty' => 1,
        'ternary.alwaysTrue' => 1,
        'varTag.nativeType' => 1,
        'varTag.variableNotFound' => 1,
    ];

    /** @return array<string, int> */
    private function actualCounts(): array
    {
        $counts = [];
        $baseline = (string)file_get_contents(self::BASELINE);
        if (preg_match_all('/identifier: ([\w.]+)/', $baseline, $matches) !== false) {
            foreach ($matches[1] as $identifier) {
                $counts[$identifier] = ($counts[$identifier] ?? 0) + 1;
            }
        }

        return $counts;
    }

    public function testNoKindOfSuppressionGrows(): void
    {
        $grown = [];
        foreach ($this->actualCounts() as $identifier => $count) {
            $ceiling = self::CEILING[$identifier] ?? 0;
            if ($count > $ceiling) {
                $grown[] = "{$identifier}: {$count} > {$ceiling}";
            }
        }

        $this->assertSame([], $grown, "the baseline grew. Fix the code rather than baselining it:\n"
            . implode("\n", $grown));
    }

    /**
     * The other direction, and the reason the ceiling is exact.
     *
     * A count that has fallen and not been recorded leaves headroom nobody decided to grant --
     * which is how a ceiling stops meaning anything. Lower the number in `CEILING` in the same
     * commit that removes the suppression.
     */
    public function testTheCeilingIsNotStale(): void
    {
        $actual = $this->actualCounts();
        $stale = [];
        foreach (self::CEILING as $identifier => $ceiling) {
            $count = $actual[$identifier] ?? 0;
            if ($count < $ceiling) {
                $stale[] = "{$identifier}: {$count} < {$ceiling}, lower it";
            }
        }

        $this->assertSame([], $stale, "these ceilings are above what the baseline holds:\n"
            . implode("\n", $stale));
    }

    /**
     * The headline number, so that a change to it is visible in a diff even when it moves
     * between kinds.
     */
    public function testTheTotalIsWhatWeThinkItIs(): void
    {
        $this->assertSame(
            array_sum(self::CEILING),
            array_sum($this->actualCounts()),
            'the per-identifier ceilings no longer add up to the baseline'
        );
    }
}
