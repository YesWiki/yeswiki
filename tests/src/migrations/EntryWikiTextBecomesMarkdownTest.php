<?php

namespace YesWiki\Test\Core\Migrations;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The wakka to Markdown sweep of entries' wiki text fields, run on its own rows only. */
class EntryWikiTextBecomesMarkdownTest extends YesWikiTestCase
{
    private const TAG = 'TestEntryWikiTextBecomesMarkdown';

    public static function setUpBeforeClass(): void
    {
        self::getWiki();
        require_once 'src/migrations/20261005180000_EntryWikiTextBecomesMarkdown.php';
    }

    public function testOnlyTheWikiTextFieldsOfOlderRevisionsAreConverted(): void
    {
        $db = $this->getWiki()->services->get(DbService::class);
        $pages = $db->prefixTable('pages');
        $legacy = [
            'id_typeannonce' => '9',
            'bf_titre' => '""Titre""',
            'bf_description' => 'Le PDG d\'""OpenAI"" parle""<iframe src="https://v.example/1"></iframe>""',
            'bf_html' => '""<b>gras</b>""',
        ];
        $this->insertRevision($db, $pages, '2020-01-01 00:00:00', $legacy, 'N');
        $this->insertRevision($db, $pages, '2099-01-01 00:00:00', ['id_typeannonce' => '9', 'bf_description' => 'déjà ""là""'], 'Y');
        $fields = ['9' => ['bf_description']];

        try {
            [$tags, $revisions] = (new \EntryWikiTextBecomesMarkdown())->rewrite($db, '2030-01-01 00:00:00', $fields, self::TAG);

            $this->assertSame([self::TAG], $tags);
            $this->assertSame(1, $revisions);
            $bodies = $this->bodies($db, $pages);
            $this->assertSame("Le PDG d'OpenAI parle\n\n<iframe src=\"https://v.example/1\"></iframe>", $bodies[0]['bf_description']);
            $this->assertSame('""Titre""', $bodies[0]['bf_titre'], 'a field that is not wiki text is left alone');
            $this->assertSame('""<b>gras</b>""', $bodies[0]['bf_html']);
            $this->assertSame('déjà ""là""', $bodies[1]['bf_description'], 'a revision written after the upgrade is left alone');

            [, $again] = (new \EntryWikiTextBecomesMarkdown())->rewrite($db, '2030-01-01 00:00:00', $fields, self::TAG);
            $this->assertSame(0, $again, 'a second run finds nothing left to convert');
        } finally {
            $db->query("DELETE FROM {$pages} WHERE tag = ?", [self::TAG]);
        }
    }

    /** @param array<string, string> $body */
    private function insertRevision(DbService $db, string $pages, string $time, array $body, string $latest): void
    {
        $db->query(
            "INSERT INTO {$pages} (tag, {$db->quoteIdentifier('time')}, body, owner, {$db->quoteIdentifier('user')}, latest, type, parent)"
            . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [self::TAG, $time, PageBody::encode($body), '', '', $latest, 'entry', '']
        );
    }

    /** @return list<array<string, mixed>> */
    private function bodies(DbService $db, string $pages): array
    {
        $rows = $db->loadAll("SELECT body FROM {$pages} WHERE tag = ? ORDER BY id", [self::TAG]);

        return array_map(fn (array $row): array => PageBody::decode((string)$row['body']), $rows);
    }
}
