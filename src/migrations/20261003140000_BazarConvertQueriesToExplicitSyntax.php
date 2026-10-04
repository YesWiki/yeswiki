<?php

use YesWiki\Content\Entity\PageType;
use YesWiki\Content\Field\EnumField;
use YesWiki\Content\Service\FieldFactory;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Search\Service\SearchIndexer;
use YesWiki\Search\Service\SearchManager;

/** Rewrites stored Bazar queries from the legacy | and , syntax into the explicit AND/OR syntax, in form enum-field settings and in entrylist action calls. Legacy queries still run, so this only normalises what the visual builder reopens. */
class BazarConvertQueriesToExplicitSyntax extends YesWikiMigration
{
    /** Enum fields keep their `query` setting at this template position. */
    private const QUERY_FIELD_INDEX = 15;

    /** The list actions whose query/queries parameters hold a Bazar query. */
    private const LIST_ACTIONS = 'entrylist|bazarlist|bazarliste|bazarcarto|bazartable|bazartableau|bazarmap|bazarcarte';

    private SearchManager $searchManager;
    private FieldFactory $fieldFactory;

    public function run()
    {
        $this->searchManager = $this->getService(SearchManager::class);
        $this->fieldFactory = $this->getService(FieldFactory::class);

        $enqueued = $this->convertFormQueries();
        $enqueued = array_merge($enqueued, $this->convertBodyQueries());

        if ($enqueued !== []) {
            $this->getService(SearchIndexer::class)->enqueue(array_values(array_unique($enqueued)));
        }
    }

    /** @return list<string> tags of the forms whose enum-field queries were rewritten */
    private function convertFormQueries(): array
    {
        $db = $this->getService(DbService::class);
        $pages = $db->prefixTable('pages');
        $rows = $db->loadAll(
            "SELECT id, tag, body FROM {$pages} WHERE latest = 'Y' AND "
            . $db->quoteIdentifier('type') . ' = ?',
            [PageType::FORM]
        );

        $changedTags = [];
        foreach ($rows as $row) {
            $body = json_decode((string)$row['body'], true);
            if (!is_array($body) || !isset($body['template']) || !is_array($body['template'])) {
                continue;
            }

            $changed = false;
            foreach ($body['template'] as $index => $fieldArray) {
                if (!is_array($fieldArray)) {
                    continue;
                }
                $value = (string)($fieldArray[self::QUERY_FIELD_INDEX] ?? '');
                if ($value === '') {
                    continue;
                }
                $field = $this->fieldFactory->create($fieldArray);
                if (!$field instanceof EnumField) {
                    continue;
                }
                $converted = $this->searchManager->convertLegacyQuery($value);
                if ($converted !== $value) {
                    $body['template'][$index][self::QUERY_FIELD_INDEX] = $converted;
                    $changed = true;
                }
            }

            if ($changed) {
                $db->query(
                    "UPDATE {$pages} SET body = ? WHERE id = ?",
                    [json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), (string)$row['id']]
                );
                $changedTags[] = (string)$row['tag'];
            }
        }

        return $changedTags;
    }

    /** @return list<string> tags of the content pages whose list-action calls were rewritten */
    private function convertBodyQueries(): array
    {
        $db = $this->getService(DbService::class);
        $pages = $db->prefixTable('pages');
        $bodyAsText = $db->jsonAsText('body');
        $rows = $db->loadAll(
            "SELECT id, tag, body FROM {$pages} WHERE latest = 'Y' AND ({$bodyAsText} LIKE '%query=%' OR {$bodyAsText} LIKE '%queries=%')"
        );

        $changedTags = [];
        foreach ($rows as $row) {
            $body = (string)$row['body'];
            $newBody = preg_replace_callback(
                '/\{\{\s*(?:' . self::LIST_ACTIONS . ')\b[^}]*\}\}/i',
                fn ($matches) => $this->rewriteActionQueries($matches[0]),
                $body
            );
            if ($newBody !== null && $newBody !== $body) {
                $db->query(
                    "UPDATE {$pages} SET body = ? WHERE id = ?",
                    [$newBody, (string)$row['id']]
                );
                $changedTags[] = (string)$row['tag'];
            }
        }

        return $changedTags;
    }

    private function rewriteActionQueries(string $action): string
    {
        return (string)preg_replace_callback(
            '/(\b(?:query|queries)\s*=\s*)(["\'])(.*?)\2/is',
            function ($m) {
                $converted = $this->searchManager->convertLegacyQuery($m[3]);

                return $m[1] . $m[2] . $converted . $m[2];
            },
            $action
        );
    }
}
