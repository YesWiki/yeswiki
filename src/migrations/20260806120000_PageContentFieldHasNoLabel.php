<?php

use YesWiki\Admin\Service\AdministrativeLogService;
use YesWiki\Content\Entity\ContentTypeSchema;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\DbService;

/** The Pages form's `content` field loses the label the seed gave it. */
class PageContentFieldHasNoLabel extends YesWikiMigration
{
    /** What the seed wrote. */
    private const SEEDED_LABEL = 'Contenu';

    public function run()
    {
        $db = $this->getService(DbService::class);
        $pages = $db->prefixTable('pages');
        $typeCol = $db->quoteIdentifier('type');
        $bodyAsText = $db->jsonAsText('body');

        $rows = $db->loadAll(
            "SELECT id, tag, body FROM {$pages} WHERE {$typeCol} = 'form'"
            . " AND {$bodyAsText} LIKE '%\"content_type\":\"" . ContentTypeSchema::TYPE_PAGE . "\"%'"
        );

        $fixed = [];
        foreach ($rows as $row) {
            $body = json_decode((string)$row['body'], true);
            if (!is_array($body) || !is_array($body['template'] ?? null)) {
                continue;
            }

            $changed = false;
            foreach ($body['template'] as $index => $field) {
                if (
                    is_array($field)
                    && ($field['name'] ?? null) === 'content'
                    && ($field['label'] ?? null) === self::SEEDED_LABEL
                ) {
                    $body['template'][$index]['label'] = '';
                    $changed = true;
                }
            }

            if (!$changed) {
                continue;
            }

            $encoded = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encoded === false) {
                continue;
            }
            $db->query(
                "UPDATE {$pages} SET body = ? WHERE id = ?",
                [$encoded, (string)$row['id']]
            );
            $fixed[(string)$row['tag']] = true;
        }

        foreach (array_keys($fixed) as $tag) {
            $this->getService(AdministrativeLogService::class)->log(
                'migration',
                "the form '{$tag}' captioned every page's prose with the word "
                . self::SEEDED_LABEL . ', which the install seeded by mistake -- the caption is '
                . 'removed. Give the content field a label again on the form designer if you '
                . 'actually want one.'
            );
        }
    }
}
