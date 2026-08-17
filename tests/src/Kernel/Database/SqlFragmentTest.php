<?php

namespace YesWiki\Test\Kernel\Database;

use PHPUnit\Framework\TestCase;
use YesWiki\Kernel\Database\SqlFragment;

/** The composition rules a fragment builder depends on (ticket 31). */
class SqlFragmentTest extends TestCase
{
    public function testAFragmentKeepsItsSqlAndValuesTogether(): void
    {
        $fragment = SqlFragment::of('tag = ?', ['Home']);

        $this->assertSame('tag = ?', $fragment->sql);
        $this->assertSame(['Home'], $fragment->params);
    }

    /**
     * Caught at construction rather than three compositions downstream, where the statement no longer resembles anything a developer wrote.
     */
    public function testAFragmentWhoseValuesAlreadyDisagreeIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SqlFragment::of('tag = ? AND owner = ?', ['only one']);
    }

    public function testAnEmptyFragmentIsEmpty(): void
    {
        $this->assertTrue(SqlFragment::empty()->isEmpty());
        $this->assertTrue(SqlFragment::of('   ')->isEmpty());
        $this->assertFalse(SqlFragment::of('1 = 1')->isEmpty());
    }

    /** The property: values come out in the order their placeholders appear. */
    public function testCompositionConcatenatesValuesInPlaceholderOrder(): void
    {
        $joined = SqlFragment::all(
            ' AND ',
            SqlFragment::of('tag = ?', ['Home']),
            SqlFragment::of('owner = ? OR user = ?', ['alice', 'bob']),
            SqlFragment::of("latest = 'Y'"),
            SqlFragment::of('parent = ?', ['']),
        );

        $this->assertSame("tag = ? AND owner = ? OR user = ? AND latest = 'Y' AND parent = ?", $joined->sql);
        $this->assertSame(['Home', 'alice', 'bob', ''], $joined->params);
    }

    /**
     * A clause that contributes nothing must not leave a dangling glue token -- the bug every caller hand-wrote a guard for, and the reason `empty()` exists as a value rather than as an `if`.
     */
    public function testEmptyPartsAreDroppedRatherThanLeavingDanglingGlue(): void
    {
        $joined = SqlFragment::all(
            ' AND ',
            SqlFragment::empty(),
            SqlFragment::of('tag = ?', ['Home']),
            SqlFragment::empty(),
            SqlFragment::of('owner = ?', ['alice']),
            SqlFragment::empty(),
        );

        $this->assertSame('tag = ? AND owner = ?', $joined->sql);
        $this->assertSame(['Home', 'alice'], $joined->params);
    }

    public function testJoiningNothingIsEmptyRatherThanTheGlue(): void
    {
        $joined = SqlFragment::all(' AND ', SqlFragment::empty(), SqlFragment::empty());

        $this->assertTrue($joined->isEmpty());
        $this->assertSame([], $joined->params);
        $this->assertSame('', $joined->sql);
    }

    public function testWrappingKeepsTheValues(): void
    {
        $wrapped = SqlFragment::of('tag = ? OR owner = ?', ['Home', 'alice'])->wrappedIn('NOT (', ')');

        $this->assertSame('NOT (tag = ? OR owner = ?)', $wrapped->sql);
        $this->assertSame(['Home', 'alice'], $wrapped->params);
    }

    /** `()` is a syntax error, not an empty condition. */
    public function testWrappingNothingStaysNothing(): void
    {
        $this->assertTrue(SqlFragment::empty()->wrappedIn('(', ')')->isEmpty());
    }

    public function testSqlOrGivesTheFallbackOnlyWhenEmpty(): void
    {
        $this->assertSame('1 = 1', SqlFragment::empty()->sqlOr('1 = 1'));
        $this->assertSame('tag = ?', SqlFragment::of('tag = ?', ['Home'])->sqlOr('1 = 1'));
    }

    /** Nesting composes: a fragment of fragments still lines its values up. */
    public function testNestedCompositionPreservesOrder(): void
    {
        $acl = SqlFragment::all(
            ' OR ',
            SqlFragment::of('acl LIKE ?', ['%alice%']),
            SqlFragment::of('acl LIKE ?', ['%@admins%']),
        )->wrappedIn('(', ')');

        $where = SqlFragment::all(
            ' AND ',
            SqlFragment::of("latest = 'Y'"),
            SqlFragment::of('content_type = ?', ['entry']),
            $acl,
            SqlFragment::of('owner = ?', ['bob']),
        );

        $this->assertSame(
            "latest = 'Y' AND content_type = ? AND (acl LIKE ? OR acl LIKE ?) AND owner = ?",
            $where->sql
        );
        $this->assertSame(['entry', '%alice%', '%@admins%', 'bob'], $where->params);

        $this->assertSame(substr_count($where->sql, '?'), count($where->params));
    }
}
