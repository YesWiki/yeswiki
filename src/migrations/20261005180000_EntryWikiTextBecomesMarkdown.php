<?php

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Entity\PageType;
use YesWiki\Content\Field\TextareaField;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\LegacyMarkupConverter;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Search\Service\SearchIndexer;

require_once __DIR__ . '/20260924120000_LegacyMarkupBecomesMarkdown.php';

/** The long text fields that Bazar entries wrote in wiki syntax before the upgrade become Markdown, as pages did: `""raw html""`, ===titles===, //italics//, [[links]]. */
class EntryWikiTextBecomesMarkdown extends YesWikiMigration
{
    public function run()
    {
        $fields = $this->wikiTextFields();
        if ($fields === []) {
            return;
        }
        $markup = new LegacyMarkupBecomesMarkdown();
        $markup->setServices($this->services);
        $markup->setDbService($this->dbService);
        $upgradedAt = $markup->upgradedAt();

        [$entries, $revisions] = $this->rewrite($this->getService(DbService::class), $upgradedAt, $fields);
        $this->getService(SearchIndexer::class)->enqueue($entries);

        if ($entries !== []) {
            $this->say(
                "the wiki markup of long text fields was rewritten as Markdown in {$revisions} revision(s) of "
                . count($entries) . ' entries'
                . ($upgradedAt === null ? '' : ", all written before the upgrade to Ectoplasme ({$upgradedAt})")
                . '.'
            );
        }
    }

    /**
     * Converts the named fields of the entry revisions older than `$before`, or only those of `$tag` when one is given.
     *
     * @param array<int|string, list<string>> $fields form id => property names of its wiki text fields
     *
     * @return array{0: list<string>, 1: int} the tags touched and the number of revisions rewritten
     */
    public function rewrite(DbService $db, ?string $before, array $fields, ?string $tag = null): array
    {
        $pages = $db->prefixTable('pages');
        $sql = "SELECT id, tag, body FROM {$pages} WHERE type = ?";
        $params = [PageType::ENTRY];
        if ($before !== null) {
            $sql .= ' AND ' . $db->quoteIdentifier('time') . ' < ?';
            $params[] = $before;
        }
        if ($tag !== null) {
            $sql .= ' AND tag = ?';
            $params[] = $tag;
        }

        return $db->transactional(fn (): array => $this->convertRows($db, $db->loadAll($sql, $params), $fields));
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<int|string, list<string>>  $fields
     *
     * @return array{0: list<string>, 1: int}
     */
    private function convertRows(DbService $db, array $rows, array $fields): array
    {
        $pages = $db->prefixTable('pages');
        $converter = new LegacyMarkupConverter();
        $touched = [];
        $revisions = 0;
        foreach ($rows as $row) {
            $body = PageBody::decode((string)$row['body']);
            $formId = (string)($body['form_id'] ?? $body['id_typeannonce'] ?? '');
            $changed = false;
            foreach ($fields[$formId] ?? [] as $property) {
                $value = $body[$property] ?? null;
                if (!is_string($value) || $value === '') {
                    continue;
                }
                $markdown = $converter->convert($value);
                if ($markdown !== $value) {
                    $body[$property] = $markdown;
                    $changed = true;
                }
            }
            if (!$changed) {
                continue;
            }

            $db->query("UPDATE {$pages} SET body = ? WHERE id = ?", [PageBody::encode($body), (string)$row['id']]);
            $touched[(string)$row['tag']] = true;
            $revisions++;
        }

        return [array_keys($touched), $revisions];
    }

    /** @return array<int|string, list<string>> form id => property names of its long text fields in wiki syntax */
    private function wikiTextFields(): array
    {
        $fields = [];
        foreach ($this->getService(FormManager::class)->getAll() as $form) {
            foreach ($form['prepared'] ?? [] as $field) {
                if ($field instanceof TextareaField && $field->getSyntax() === TextareaField::SYNTAX_WIKI) {
                    $fields[(string)$form['id']][] = (string)$field->getPropertyName();
                }
            }
        }

        return $fields;
    }
}
