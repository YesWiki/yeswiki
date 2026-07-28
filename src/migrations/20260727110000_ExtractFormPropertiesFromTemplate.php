<?php

use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\TripleStore;
use YesWiki\Core\YesWikiMigration;

/**
 * Ticket 27 (ADR-0010): the five pseudo-fields (titre / acls / metadatas /
 * utilisateur_wikini / bookmarklet) leave the form template and become form
 * properties applied by the entry pipeline. Converts the latest revision of every
 * form page in place; every form ends up with a non-empty entry_title_template
 * ({{bf_titre}} by default).
 */
class ExtractFormPropertiesFromTemplate extends YesWikiMigration
{
    private function truthy($value): bool
    {
        return in_array($value, [1, true, '1', 'true', 'yes'], true);
    }

    private function compactObject(array $pairs): array
    {
        return array_filter($pairs, function ($value) {
            return $value !== null && trim((string)$value) !== '';
        });
    }

    public function run()
    {
        $rows = $this->dbService->loadAll(
            'SELECT id, tag, body FROM ' . $this->dbService->prefixTable('pages')
            . " WHERE latest = 'Y' AND tag IN (SELECT resource FROM " . $this->dbService->prefixTable('triples')
            . " WHERE property = '" . $this->dbService->escape(TripleStore::TYPE_URI)
            . "' AND value = '" . $this->dbService->escape(FormManager::TRIPLES_FORM_TYPE) . "')"
        );

        foreach ($rows as $row) {
            $body = json_decode($row['body'] ?? '', true);
            if (!is_array($body) || !is_array($body['template'] ?? null)) {
                continue;
            }

            $template = [];
            $changed = false;
            foreach ($body['template'] as $fieldObject) {
                switch ($fieldObject['type'] ?? '') {
                    case 'titre':
                        $body['entry_title_template'] = $fieldObject['title_template'] ?? '';
                        $changed = true;
                        break;
                    case 'acls':
                    case 'acl':
                        foreach ([
                            'entry_read_right' => 'entry_read_access',
                            'entry_write_right' => 'entry_write_access',
                            'entry_comment_right' => 'entry_comment_access',
                        ] as $attr => $property) {
                            if (!empty($fieldObject[$attr])) {
                                $body[$property] = $fieldObject[$attr];
                            }
                        }
                        if ($this->truthy($fieldObject['ask_if_activate_comments'] ?? '')) {
                            $body['entry_permit_activate_comments'] = true;
                        }
                        $changed = true;
                        break;
                    case 'metadatas':
                        $body['entry_metadatas'] = $this->compactObject([
                            'theme' => $fieldObject['theme'] ?? null,
                            'skeleton' => $fieldObject['template'] ?? null,
                            'style' => $fieldObject['style'] ?? null,
                            'background_image' => $fieldObject['bg_image'] ?? null,
                            'css_preset' => $fieldObject['css_preset'] ?? null,
                        ]);
                        $changed = true;
                        break;
                    case 'utilisateur_wikini':
                        $body['entry_creates_user'] = $this->compactObject([
                            'name_field' => $fieldObject['name_field'] ?? null,
                            'email_field' => $fieldObject['email_field'] ?? null,
                            'add_to_group' => $fieldObject['auto_add_to_group'] ?? null,
                            'update_email' => $this->truthy($fieldObject['auto_update_mail'] ?? '') ? '1' : null,
                            'mailing_list' => $fieldObject['mailing_list'] ?? null,
                        ]);
                        $changed = true;
                        break;
                    case 'bookmarklet':
                        $body['entry_bookmarklet'] = $this->compactObject([
                            'url_field' => $fieldObject['url_field'] ?? null,
                            'description_field' => $fieldObject['description_field'] ?? null,
                            'text_field' => $fieldObject['text_field'] ?? null,
                        ]);
                        $changed = true;
                        break;
                    default:
                        $template[] = $fieldObject;
                }
            }

            if (empty(trim((string)($body['entry_title_template'] ?? '')))) {
                // the historical implicit convention: the visitor-typed bf_titre field
                $body['entry_title_template'] = '{{bf_titre}}';
                $changed = true;
            }

            if (!$changed) {
                continue;
            }
            $body['template'] = $template;

            $this->dbService->query(
                'UPDATE ' . $this->dbService->prefixTable('pages')
                . " SET body = '" . $this->dbService->escape(json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
                . "' WHERE id = '" . $this->dbService->escape($row['id']) . "'"
            );
        }
    }
}
