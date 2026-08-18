<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\TestCase;

/** A ceiling on the PHPStan baseline: each kind of suppression may fall, never rise. */
class PhpstanBaselineRatchetTest extends TestCase
{
    private const BASELINE = __DIR__ . '/../../../phpstan/baseline.neon';

    /**
     * Suppressions per error identifier.
     *
     * @var array<string, int>
     */
    private const CEILING = [
        'missingType.return' => 884,
        'missingType.parameter' => 704,
        'missingType.iterableValue' => 569,
        'missingType.property' => 462,
        'missingType.generics' => 7,

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
        'foreach.nonIterable' => 4,
        'method.unused' => 5,
        'nullCoalesce.variable' => 5,
        'empty.offset' => 4,
        'isset.variable' => 4,
        'return.phpDocType' => 4,
        'throws.notThrowable' => 4,
        'binaryOp.invalid' => 3,
        'booleanNot.alwaysFalse' => 3,
        'encapsedStringPart.nonString' => 1,
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

    /**
     * @return array<string, int>
     */
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

    /** The other direction, and the reason the ceiling is exact. */
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
     * The headline number, so that a change to it is visible in a diff even when it moves between kinds.
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
