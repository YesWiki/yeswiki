<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\TestCase;

/** A ceiling on `$GLOBALS`, which worker mode makes a correctness problem (ADR-0024). */
class GlobalsRatchetTest extends TestCase
{
    private const SRC = __DIR__ . '/../../../src';

    /**
     * What each file has yet to convert, counted the day the rule was seeded (2026-08-21).
     *
     * @var array<string, int>
     */
    private const REMAINING = [
        'Admin/Action/EditConfigAction.php' => 2,
        'Admin/Controller/DocumentationController.php' => 1,
        'Content/Controller/FormController.php' => 1,
        'Kernel/Service/LanguageService.php' => 3,
        'Render/Service/CoreAssets.php' => 1,
        'YesWikiRuntime.php' => 1,
    ];

    /**
     * @return array<string, int> file relative to src/ => times the code reads or writes `$GLOBALS`
     */
    private function actualCounts(): array
    {
        $root = (string)realpath(self::SRC);
        $counts = [];
        $directory = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($directory as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $found = $this->globalsIn((string)file_get_contents($file->getPathname()));
            if ($found > 0) {
                $counts[substr($file->getPathname(), strlen($root) + 1)] = $found;
            }
        }

        return $counts;
    }

    /** Uses of `$GLOBALS` in $source, counting code and not prose. */
    private function globalsIn(string $source): int
    {
        $found = 0;
        foreach (token_get_all($source) as $token) {
            if (is_array($token) && $token[0] === T_VARIABLE && $token[1] === '$GLOBALS') {
                $found++;
            }
        }

        return $found;
    }

    public function testNoFileGainsGlobals(): void
    {
        $over = [];
        foreach ($this->actualCounts() as $file => $found) {
            $budget = self::REMAINING[$file] ?? 0;
            if ($found > $budget) {
                $over[] = "{$file}: {$found} uses of \$GLOBALS, {$budget} allowed";
            }
        }

        $this->assertSame([], $over, "Request state does not live in a global (ADR-0024): a worker keeps the process alive between requests, so what one visitor leaves there the next one reads.\n"
            . 'Put it in a service that is built per request, the way PageContext and CurrentRequest are.');
    }

    /** The other direction, and the reason the numbers are exact. */
    public function testTheRemainingListIsNotStale(): void
    {
        $actual = $this->actualCounts();
        $stale = [];
        foreach (self::REMAINING as $file => $budget) {
            $found = $actual[$file] ?? 0;
            if ($found < $budget) {
                $stale[] = "{$file}: {$found} left, {$budget} still budgeted -- lower it";
            }
        }

        $this->assertSame([], $stale, 'These budgets are above what the files hold. A budget nobody spends is a rule nobody enforces.');
    }

    /** The headline number, so a change to it shows up in a diff even when it moves between files. */
    public function testTheTotalIsWhatWeThinkItIs(): void
    {
        $this->assertSame(
            array_sum(self::REMAINING),
            array_sum($this->actualCounts()),
            'the per-file budgets no longer add up to what src/ holds'
        );
    }
}
