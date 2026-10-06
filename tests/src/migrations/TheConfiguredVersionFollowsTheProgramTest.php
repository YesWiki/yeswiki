<?php

namespace YesWiki\Test\Core\Migrations;

use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The `yeswiki_version` a migrated wiki keeps is replaced by the Program's Release line. */
class TheConfiguredVersionFollowsTheProgramTest extends YesWikiTestCase
{
    public static function setUpBeforeClass(): void
    {
        self::getWiki();
        require_once 'src/migrations/20261006100000_TheConfiguredVersionFollowsTheProgram.php';
    }

    public static function versions(): array
    {
        return [
            'a Doryphore wiki' => ['doryphore', 'ectoplasme', 'ectoplasme'],
            'no version at all' => ['', 'ectoplasme', 'ectoplasme'],
            'already the line' => ['ectoplasme', 'ectoplasme', null],
            'the line in capitals' => ['Ectoplasme', 'ectoplasme', null],
            'a Program naming no line' => ['doryphore', '', null],
        ];
    }

    #[DataProvider('versions')]
    public function testTheLineToWrite(string $configured, string $programLine, ?string $expected): void
    {
        $this->assertSame($expected, \TheConfiguredVersionFollowsTheProgram::lineToWrite($configured, $programLine));
    }
}
