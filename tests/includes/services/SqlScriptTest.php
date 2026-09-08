<?php

namespace YesWiki\Test\Core\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YesWiki\Core\Service\SqlScript;

require_once 'includes/services/SqlScript.php';

class SqlScriptTest extends TestCase
{
    /**
     * @return string[]
     */
    private static function statements(string $sqlContent): array
    {
        return iterator_to_array(SqlScript::statements($sqlContent), false);
    }

    #[DataProvider('scriptProvider')]
    public function testStatements(string $sqlContent, array $expected)
    {
        $this->assertSame($expected, self::statements($sqlContent));
    }

    public static function scriptProvider()
    {
        return [
            'two statements' => [
                "CREATE TABLE `a` (`b` int);\nINSERT INTO `a` VALUES (1);\n",
                ['CREATE TABLE `a` (`b` int)', 'INSERT INTO `a` VALUES (1)'],
            ],
            'a semicolon inside a page body' => [
                "INSERT INTO `a` VALUES ('one; two', 3);\n",
                ["INSERT INTO `a` VALUES ('one; two', 3)"],
            ],
            'an escaped quote before a semicolon' => [
                "INSERT INTO `a` VALUES ('it\\'s here; really');\nINSERT INTO `a` VALUES (2);\n",
                ["INSERT INTO `a` VALUES ('it\\'s here; really')", 'INSERT INTO `a` VALUES (2)'],
            ],
            'a doubled quote' => [
                "INSERT INTO `a` VALUES ('two '' quotes; one statement');\n",
                ["INSERT INTO `a` VALUES ('two '' quotes; one statement')"],
            ],
            'a backslash at the end of a value' => [
                "INSERT INTO `a` VALUES ('ends with a backslash\\\\');\nINSERT INTO `a` VALUES (2);\n",
                ["INSERT INTO `a` VALUES ('ends with a backslash\\\\')", 'INSERT INTO `a` VALUES (2)'],
            ],
            'a semicolon in an identifier' => [
                "CREATE TABLE `odd;name` (`b` int);\n",
                ['CREATE TABLE `odd;name` (`b` int)'],
            ],
            'a semicolon in a line comment' => [
                "-- one; two\nINSERT INTO `a` VALUES (1);\n",
                ["-- one; two\nINSERT INTO `a` VALUES (1)"],
            ],
            'a semicolon in a hash comment' => [
                "# one; two\nINSERT INTO `a` VALUES (1);\n",
                ["# one; two\nINSERT INTO `a` VALUES (1)"],
            ],
            'a semicolon in a block comment' => [
                "/* one; two */\nINSERT INTO `a` VALUES (1);\n",
                ["/* one; two */\nINSERT INTO `a` VALUES (1)"],
            ],
            'an executable comment stays a statement' => [
                "/*!40101 SET NAMES utf8mb4 */;\nINSERT INTO `a` VALUES (1);\n",
                ['/*!40101 SET NAMES utf8mb4 */', 'INSERT INTO `a` VALUES (1)'],
            ],
            'a double quoted value' => [
                "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO;\";\nINSERT INTO `a` VALUES (1);\n",
                ['SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO;"', 'INSERT INTO `a` VALUES (1)'],
            ],
            'a last statement without its semicolon' => [
                "INSERT INTO `a` VALUES (1);\nINSERT INTO `a` VALUES (2)",
                ['INSERT INTO `a` VALUES (1)', 'INSERT INTO `a` VALUES (2)'],
            ],
            'empty statements are dropped' => [
                "INSERT INTO `a` VALUES (1);\n;\n;\n",
                ['INSERT INTO `a` VALUES (1)'],
            ],
            'nothing at all' => ['', []],
            'only comments' => ["-- nothing here\n", ['-- nothing here']],
        ];
    }

    public function testAMultilineInsertStaysOneStatement()
    {
        $sql = "INSERT INTO `pages` (`tag`, `body`) VALUES\n('One', 'first'),\n('Two', 'second; still the same statement'),\n('Three', 'third');\n";

        $this->assertCount(1, self::statements($sql));
    }

    public function testTheOversizedStatementIsFound()
    {
        $sql = "INSERT INTO `a` VALUES ('small');\nINSERT INTO `a` VALUES ('" . str_repeat('x', 2000) . "');\n";

        $oversized = SqlScript::oversizedStatement($sql, 1000);

        $this->assertNotNull($oversized);
        $this->assertGreaterThan(1000, strlen($oversized));
    }

    public function testNothingIsOversizedWhenEverythingFits()
    {
        $sql = "INSERT INTO `a` VALUES ('small');\nINSERT INTO `a` VALUES ('also small');\n";

        $this->assertNull(SqlScript::oversizedStatement($sql, 1000));
    }

    public function testAnUnknownLimitRefusesNothing()
    {
        $this->assertNull(SqlScript::oversizedStatement("INSERT INTO `a` VALUES ('x');", 0));
    }

    public function testTheErrorSaysWhatToDoAboutAnOversizedStatement()
    {
        $message = SqlScript::errorMessage('MySQL server has gone away', str_repeat('x', 3 * 1024 * 1024), 1024 * 1024);

        $this->assertStringContainsString('3 MB', $message);
        $this->assertStringContainsString('1 MB', $message);
        $this->assertStringContainsString('max_allowed_packet', $message);
    }

    #[DataProvider('scriptProvider')]
    public function testTheSameStatementsComeOutOfAStream(string $sqlContent, array $expected)
    {
        $handle = fopen('php://memory', 'r+');
        fwrite($handle, $sqlContent);
        rewind($handle);

        $statements = iterator_to_array(SqlScript::statementsFromStream($handle), false);
        fclose($handle);

        $this->assertSame($expected, $statements);
    }

    public function testAStatementSplitAcrossReadsStaysWhole()
    {
        $body = str_repeat('a; b -- c /* d ', 200000);
        $sql = "INSERT INTO `a` VALUES ('$body');\nINSERT INTO `a` VALUES ('after');\n";
        $handle = fopen('php://memory', 'r+');
        fwrite($handle, $sql);
        rewind($handle);

        $statements = iterator_to_array(SqlScript::statementsFromStream($handle), false);
        fclose($handle);

        $this->assertGreaterThan(SqlScript::CHUNK_SIZE, strlen($body), 'the value has to be bigger than one read');
        $this->assertCount(2, $statements);
        $this->assertSame("INSERT INTO `a` VALUES ('$body')", $statements[0]);
        $this->assertSame("INSERT INTO `a` VALUES ('after')", $statements[1]);
    }

    public function testReadingADumpCostsTheMemoryOfOneStatement()
    {
        $handle = fopen('php://memory', 'r+');
        for ($i = 0; $i < 400; $i++) {
            fwrite($handle, "INSERT INTO `a` VALUES ('" . str_repeat('x', 100000) . "');\n");
        }
        $size = ftell($handle);
        rewind($handle);

        $before = memory_get_usage(true);
        $count = 0;
        foreach (SqlScript::statementsFromStream($handle) as $statement) {
            $count++;
        }
        $used = memory_get_usage(true) - $before;
        fclose($handle);

        $this->assertSame(400, $count);
        $this->assertGreaterThan(30 * 1024 * 1024, $size, 'the dump has to be big enough for the comparison to mean something');
        $this->assertLessThan($size / 4, $used, 'reading the dump must not hold it all in memory');
    }

    public function testTheErrorShowsTheStatementNotItsComment()
    {
        $message = SqlScript::errorMessage('some error', "-- \n-- Data of table : `pages`\n-- \nINSERT INTO `pages` VALUES (1)", 0);

        $this->assertStringContainsString('INSERT INTO `pages`', $message);
        $this->assertStringNotContainsString('Data of table', $message);
    }
}
