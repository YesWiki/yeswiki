<?php

namespace YesWiki\Test\Kernel\Database;

use PHPUnit\Framework\TestCase;

/**
 * A ratchet on `DbService::escape()`: the count may fall, never rise.
 *
 * `escape()` is the pre-bindings way of getting a value into a query -- PDO::quote() with its
 * outer quotes chopped off, spliced into SQL text by the caller. It is not broken, and every
 * call site here was checked to be correctly quoted. What it cannot do is enforce anything:
 * the safety of a call depends on the caller remembering the quotes, no test can see an
 * omission, and PHPStan cannot either. `query($sql, $params)` moves that from a habit to a
 * property of the API.
 *
 * So this file is a burn-down list, in the spirit of ArchitectureTest's KNOWN_VIOLATIONS: it
 * records what was there when bindings landed, fails if any of it grows, and fails if a file
 * that had none acquires some. Numbers may be lowered -- and must be, when you convert a call
 * site, because TOTAL is asserted exactly. That is deliberate: a ceiling nobody has to update
 * stops describing the code within a month.
 *
 * Converting a call site means replacing the interpolation with a placeholder and passing the
 * value: see SearchIndexer for the whole file done, and BoundValuesTest for what it buys.
 *
 * Two things bindings cannot do, both now handled elsewhere rather than open questions:
 *  - **identifiers** (a column or table name) cannot be bound at all. Field names are user
 *    data and reach SearchManager's generated SQL in five positions, so they are constrained
 *    once at their chokepoint instead -- `SearchManager::asSafeIdentifier()`, pinned by
 *    FieldNameIsNotSqlTest.
 *  - **LIKE metacharacters**: `%` and `_` are pattern syntax, so neither escape() nor a bound
 *    parameter touches them, correctly. Defusing is deliberate and opt-in --
 *    `SqlParameters::likeContains()` plus `LIKE_CLAUSE_SUFFIX`, which is mandatory because
 *    SQLite has no default escape character.
 *
 * What keeps a count on this list is therefore one of: it is a genuine value escape not yet
 * converted; it is inside a SQL *fragment* builder whose callers concatenate the result, so the
 * value never reaches the call that executes the statement (AclService's read-ACL predicate,
 * SearchManager's WHERE assembly); or it is in a one-shot migration against known data.
 */
class EscapeRatchetTest extends TestCase
{
    private const SRC = __DIR__ . '/../../../../src';

    /**
     * Every file that still builds SQL with escape(), and how many calls it may have.
     *
     * @var array<string, int>
     */
    private const CEILING = [
        // the last one in live code, and it is correct: escaping row VALUES into the TEXT of a
        // SQL dump file. A dump is written, not executed, so there is no statement to bind to.
        'Kernel/Database/SqlDumper.php' => 1,
        'migrations/00000000000002_PageTypeAndParentColumns.php' => 6,
        'migrations/20240425000000_CalcFieldToString.php' => 2,
        'migrations/20240425000000_CheckSQLTablesThenFixThem.php' => 1,
        'migrations/20240425153022_CleanBase64.php' => 3,
        'migrations/20240425172243_CleanOldCartoGoogle.php' => 6,
        'migrations/20251012095130_RemoveLoginTplTemplate.php' => 1,
        'migrations/20260203091701_BazarChangeModelForGeolocation.php' => 4,
        'migrations/20260727000000_ConvertFormTemplatesToJson.php' => 3,
        'migrations/20260727100000_RenameFormBodyKeys.php' => 3,
        'migrations/20260727110000_ExtractFormPropertiesFromTemplate.php' => 3,
        'migrations/20260727120000_RenameEntryBodyKeys.php' => 3,
        'migrations/20260730160000_FileAttributesIntoBody.php' => 4,
        'migrations/20260801000000_RenameContentOffReservedTags.php' => 7,
        'migrations/20260802140000_RenameContentOffSearchTag.php' => 6,
        'migrations/20260803120000_RenameContentOffDashboardTags.php' => 6,
        'migrations/20260806110000_LookWikiIsRetired.php' => 1,
    ];

    /** Lower this when you convert a call site. It is asserted exactly, on purpose. */
    private const TOTAL = 60;

    /** @return array<string, int> relative path => number of ->escape( calls */
    private function currentCounts(): array
    {
        $counts = [];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::SRC, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $n = substr_count((string)file_get_contents($file->getPathname()), '->escape(');
            if ($n > 0) {
                $rel = str_replace('\\', '/', substr($file->getPathname(), strlen(self::SRC) + 1));
                $counts[$rel] = $n;
            }
        }
        ksort($counts);

        return $counts;
    }

    public function testNoFileGainsAnEscapeCall(): void
    {
        $grown = [];
        foreach ($this->currentCounts() as $file => $count) {
            $allowed = self::CEILING[$file] ?? 0;
            if ($count > $allowed) {
                $grown[] = "{$file}: {$count} > {$allowed}";
            }
        }

        $this->assertSame([], $grown, "These files build more SQL by string interpolation than they used to.\n"
            . 'Pass the value instead: query($sql, [$value]). See SearchIndexer for a converted file.');
    }

    public function testAFileWithNoneDoesNotAcquireAny(): void
    {
        $new = array_diff(array_keys($this->currentCounts()), array_keys(self::CEILING));

        $this->assertSame([], array_values($new), "New code must not use escape() to build SQL.\n"
            . 'Use query($sql, $params) / prepare($sql) -- both take values separately.');
    }

    /**
     * The total, asserted exactly so that lowering it is part of converting a call site.
     *
     * A "less than or equal" assertion here would let the recorded number drift upward of the
     * truth as the code improved, and a burn-down list that overstates what is left is one
     * nobody trusts enough to finish.
     */
    public function testTheTotalMatchesWhatIsRecorded(): void
    {
        $total = array_sum($this->currentCounts());

        $this->assertSame(
            self::TOTAL,
            $total,
            $total < self::TOTAL
                ? "Good -- {$total} escape() calls left, down from " . self::TOTAL . '. Lower TOTAL (and the '
                    . "file's entry in CEILING) to match, so the list keeps telling the truth."
                : "escape() usage grew to {$total}."
        );
    }

    /** A file that has been fully converted should leave the list, not sit on it at zero. */
    public function testTheListHasNoStaleEntries(): void
    {
        $stale = array_diff(array_keys(self::CEILING), array_keys($this->currentCounts()));

        $this->assertSame([], array_values($stale), 'These files no longer call escape() at all -- '
            . 'delete them from CEILING.');
    }
}
