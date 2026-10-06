<?php

namespace YesWiki\Test\Admin;

use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Admin\Service\InstallationService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A prefix is refused only when this wiki's own tables are there, never because of another wiki sharing the database. */
class InstallTablePrefixTest extends YesWikiTestCase
{
    public static function setUpBeforeClass(): void
    {
        self::getWiki();
    }

    /** @return array<string, array{string, list<string>, bool}> */
    public static function databases(): array
    {
        $wiki = fn (string $prefix): array => [$prefix . 'pages', $prefix . 'triples', $prefix . 'search_index'];

        return [
            'empty database' => ['wiki_', [], true],
            'a longer prefix sharing the start' => ['wiki_', $wiki('wikiother_'), true],
            'an underscore is not a wildcard' => ['wiki_', $wiki('wikis'), true],
            'another wiki whose prefix extends this one' => ['wiki', $wiki('wikiother_'), true],
            'this wiki already installed' => ['wiki_', $wiki('wiki_'), false],
            'a stray table under this prefix' => ['wiki_', ['wiki_leftover'], false],
        ];
    }

    /** @param list<string> $tables */
    #[DataProvider('databases')]
    public function testThePrefixCheck(string $prefix, array $tables, bool $accepted): void
    {
        $pdo = new \PDO('sqlite::memory:');
        foreach ($tables as $table) {
            $pdo->exec("CREATE TABLE {$table} (id INTEGER)");
        }
        $installer = new InstallationService(['db_driver' => 'sqlite', 'table_prefix' => $prefix], 'unused.php');
        (new \ReflectionProperty(InstallationService::class, 'dbLink'))->setValue($installer, $pdo);

        $refused = false;
        try {
            (new \ReflectionMethod(InstallationService::class, 'checkTablePrefix'))->invoke($installer);
        } catch (\Exception $e) {
            $refused = true;
        }

        $this->assertSame(!$accepted, $refused);
    }
}
