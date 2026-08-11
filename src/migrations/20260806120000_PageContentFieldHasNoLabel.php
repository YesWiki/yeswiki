<?php

use YesWiki\Admin\Service\AdministrativeLogService;
use YesWiki\Content\Entity\ContentTypeSchema;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\DbService;

/**
 * The Pages form's `content` field loses the label the seed gave it.
 *
 * A page rendered through its form since ticket 10, and a field renders its label above its
 * value -- so every page in the wiki grew a "Contenu" caption over its own prose.
 *
 * `ContentTypeSchema` has always said the label is empty, and says why: *the prose IS the
 * page*. The SQL seed simply did not follow it and wrote `"label":"Contenu"`. So this is the
 * seed drifting from the schema it is supposed to instantiate -- the same drift ticket 25
 * found once already -- and every wiki installed from that seed carries the wrong label in
 * its stored form.
 *
 * Only the seeded value is removed. A webmaster who deliberately captioned the field keeps
 * their caption: a label this migration does not recognise is left exactly as it is.
 *
 * Idempotent: once the label is empty there is nothing to match.
 */
class PageContentFieldHasNoLabel extends YesWikiMigration
{
    /** What the seed wrote. Anything else is somebody's choice. */
    private const SEEDED_LABEL = 'Contenu';

    public function run()
    {
        $db = $this->getService(DbService::class);
        $pages = $db->prefixTable('pages');
        $typeCol = $db->quoteIdentifier('type');

        $rows = $db->loadAll(
            "SELECT id, tag, body FROM {$pages} WHERE {$typeCol} = 'form'"
            . " AND body LIKE '%\"content_type\":\"" . ContentTypeSchema::TYPE_PAGE . "\"%'"
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
