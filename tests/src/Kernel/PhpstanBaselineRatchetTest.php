<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\TestCase;

/** The PHPStan baseline is empty, and this is what keeps it that way. */
class PhpstanBaselineRatchetTest extends TestCase
{
    private const BASELINE = __DIR__ . '/../../../phpstan/baseline.neon';

    public function testTheBaselineIsEmpty(): void
    {
        $baseline = (string)file_get_contents(self::BASELINE);
        preg_match_all('/identifier: ([\w.]+)/', $baseline, $matches);

        $this->assertSame(
            [],
            $matches[1],
            "The baseline is empty and stays empty. Fix the code rather than regenerating this file.\n"
            . "An error the analyser is genuinely wrong about -- a suggested package that is not installed,\n"
            . "a guard whose answer depends on how an extension was built -- belongs in phpstan.neon's\n"
            . 'ignoreErrors with the reason written beside it, as the six entries there have.'
        );
    }
}
