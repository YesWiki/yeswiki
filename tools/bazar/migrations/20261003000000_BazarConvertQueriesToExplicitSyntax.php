<?php

use YesWiki\Bazar\Field\EnumField;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Bazar\Service\SearchManager;
use YesWiki\Core\YesWikiMigration;

/** Rewrites stored bazar queries from the legacy `|`/`,` form into the explicit AND/OR grammar; idempotent. */
class BazarConvertQueriesToExplicitSyntax extends YesWikiMigration
{
    private const QUERY_FIELD_INDEX = 15;

    public function run()
    {
        $searchManager = $this->wiki->services->get(SearchManager::class);
        $this->convertFormQueries($searchManager);
        $this->convertPageBodyQueries($searchManager);
    }

    private function convertFormQueries(SearchManager $searchManager): void
    {
        $formManager = $this->wiki->services->get(FormManager::class);
        foreach ($formManager->getAll() as $form) {
            $template = $form['template'];
            $changed = false;
            foreach ($template as $index => $field) {
                $prepared = $form['prepared'][$index] ?? null;
                if (!($prepared instanceof EnumField) || !isset($field[self::QUERY_FIELD_INDEX])) {
                    continue;
                }
                $converted = $searchManager->convertLegacyQuery((string)$field[self::QUERY_FIELD_INDEX]);
                if ($converted !== (string)$field[self::QUERY_FIELD_INDEX]) {
                    $template[$index][self::QUERY_FIELD_INDEX] = $converted;
                    $changed = true;
                }
            }
            if ($changed) {
                $this->dbService->query(
                    'UPDATE ' . $this->dbService->prefixTable('nature')
                    . ' SET bn_template = \'' . $this->dbService->escape($formManager->encodeTemplate($template)) . '\''
                    . ' WHERE bn_id_nature = \'' . $this->dbService->escape($form['bn_id_nature']) . '\''
                );
            }
        }
    }

    private function convertPageBodyQueries(SearchManager $searchManager): void
    {
        $pages = $this->dbService->loadAll(
            'SELECT id, body FROM ' . $this->dbService->prefixTable('pages')
            . " WHERE latest = 'Y' AND body LIKE '%quer%'"
        );
        foreach ($pages as $page) {
            $converted = $this->convertBodyQueries((string)$page['body'], $searchManager);
            if ($converted !== (string)$page['body']) {
                $this->dbService->query(
                    'UPDATE ' . $this->dbService->prefixTable('pages')
                    . ' SET body = \'' . $this->dbService->escape(chop($converted)) . '\''
                    . ' WHERE id = \'' . $this->dbService->escape($page['id']) . '\''
                );
            }
        }
    }

    public function convertBodyQueries(string $body, SearchManager $searchManager): string
    {
        return (string)preg_replace_callback(
            '/\b(queries|query)=(")([^"]*)(")|\b(queries|query)=(\')([^\']*)(\')/',
            function (array $matches) use ($searchManager) {
                if (($matches[1] ?? '') !== '') {
                    return $matches[1] . '="' . $searchManager->convertLegacyQuery($matches[3]) . '"';
                }

                return $matches[5] . '=\'' . $searchManager->convertLegacyQuery($matches[7]) . '\'';
            },
            $body
        );
    }
}
