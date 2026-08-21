<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\TestCase;

/**
 * A ceiling on `$GLOBALS`, which worker mode makes a correctness problem (ADR-0024).
 *
 * Under php-fpm the process dies with the request, so a per-request fact kept in a global is
 * merely ugly. Under a worker it is a bug: the counter that numbers mail forms climbs for the
 * life of the process, the flag that says "this is an import" is never unset, the panel stack is
 * handed on dirty. ADR-0024 rejected resetting them between requests, because a reset routine is
 * a list somebody has to remember to extend and the day it falls behind the symptom is
 * cross-visitor bleed rather than an error. So they are removed, and this stops them coming back.
 *
 * Per file rather than one total, for `PhpstanBaselineRatchetTest`'s reason: a file shedding ten
 * must not make room for one somewhere else.
 */
class GlobalsRatchetTest extends TestCase
{
    private const SRC = __DIR__ . '/../../../src';

    /**
     * What each file has yet to convert, counted the day the rule was seeded (2026-08-21).
     *
     * A number may only fall and a new file may not appear. **Every entry left is boot state**
     * (the translation catalogues and the language lists, which ADR-0024 allows because they are
     * identical for every request in a process) or the one deprecated write in `YesWikiRuntime`
     * that core no longer reads. No request state remains, which is what the ADR asked for.
     *
     * @var array<string, int>
     */
    private const REMAINING = [
        'Admin/Action/EditConfigAction.php' => 4,
        'Admin/Controller/DocumentationController.php' => 1,
        'Admin/Controller/InstallationController.php' => 3,
        'Content/Controller/FormController.php' => 1,
        'Kernel/Service/LanguageService.php' => 16,
        'Render/Service/CoreAssets.php' => 1,
        'Render/Service/TemplateEngine.php' => 2,
        'Render/Service/ThemeSelectorRenderer.php' => 2,
        'YesWikiRuntime.php' => 1,
        'lang/languages_list.php' => 1,
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

    /**
     * Uses of `$GLOBALS` in $source, counting code and not prose.
     *
     * Tokenised rather than grepped, so that a docblock saying which global a service replaced
     * does not count against it. Recording that history is worth more than the simpler rule, and
     * a number that can never reach zero while the history is written down is a number nobody
     * can finish.
     */
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
