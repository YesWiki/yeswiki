<?php

namespace YesWiki\Test\Kernel\Database;

use PHPUnit\Framework\TestCase;

/**
 * `DbService::escape()` builds SQL by string interpolation, and new code does not get to.
 *
 * This began as a ratchet with a per-file ceiling and a running total, which is what a burn-down
 * needs. The burn-down is over: 99 call sites became 60, and all but one of the 60 are in
 * migrations that have already run on every wiki and will never be edited again. Counting them
 * every run was maintaining a number that could only change by hand.
 *
 * What is left is a rule with two named exemptions, and it is a stronger statement than the
 * ceiling was: not "no more than six here", but "not at all, except where quoting is the job".
 */
class EscapeRatchetTest extends TestCase
{
    private const SRC = __DIR__ . '/../../../../src';

    /**
     * The two places that may still interpolate, and why.
     *
     * `SqlDumper` is not a leftover and never will be: it writes `INSERT` statements into a text
     * file, so there is no query to bind a value to. Quoting a literal into SQL text *is* what it
     * does.
     *
     * Migrations are frozen history. Each of these has already run on every wiki that will ever
     * run it, and editing one to bind its values changes nothing anybody executes. A migration
     * written from today binds like everything else, which is why this is a list of the ones that
     * exist rather than a wildcard on the directory.
     *
     * @var list<string>
     */
    private const ALLOWED = [
        'Kernel/Database/SqlDumper.php',
        'migrations/00000000000002_PageTypeAndParentColumns.php',
        'migrations/20240425000000_CalcFieldToString.php',
        'migrations/20240425000000_CheckSQLTablesThenFixThem.php',
        'migrations/20240425153022_CleanBase64.php',
        'migrations/20240425172243_CleanOldCartoGoogle.php',
        'migrations/20251012095130_RemoveLoginTplTemplate.php',
        'migrations/20260203091701_BazarChangeModelForGeolocation.php',
        'migrations/20260727000000_ConvertFormTemplatesToJson.php',
        'migrations/20260727100000_RenameFormBodyKeys.php',
        'migrations/20260727110000_ExtractFormPropertiesFromTemplate.php',
        'migrations/20260727120000_RenameEntryBodyKeys.php',
        'migrations/20260730160000_FileAttributesIntoBody.php',
        'migrations/20260801000000_RenameContentOffReservedTags.php',
        'migrations/20260802140000_RenameContentOffSearchTag.php',
        'migrations/20260803120000_RenameContentOffDashboardTags.php',
        'migrations/20260806110000_LookWikiIsRetired.php',
    ];

    public function testNothingOutsideTheTwoExemptionsBuildsSqlByInterpolation(): void
    {
        $offenders = array_values(array_diff($this->filesThatInterpolate(), self::ALLOWED));

        $this->assertSame([], $offenders, "These build SQL with escape(), and nothing new may.\n"
            . "Pass the value instead: query(\$sql, [\$value]) or prepare(\$sql). SearchIndexer is a converted file.\n"
            . 'A new migration binds like anything else -- the exempt ones are exempt because they have already run.');
    }

    public function testAnExemptionThatIsNoLongerUsedIsRemoved(): void
    {
        $stale = array_values(array_diff(self::ALLOWED, $this->filesThatInterpolate()));

        $this->assertSame([], $stale, 'These no longer call escape(), so they are not exemptions any more. '
            . 'Delete them from ALLOWED and the rule gets stricter for free.');
    }

    /**
     * @return list<string> paths relative to src/, sorted
     */
    private function filesThatInterpolate(): array
    {
        $found = [];
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::SRC, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            if (!str_contains((string)file_get_contents($file->getPathname()), '->escape(')) {
                continue;
            }
            $found[] = str_replace('\\', '/', substr($file->getPathname(), \strlen(self::SRC) + 1));
        }

        sort($found);

        return $found;
    }
}
