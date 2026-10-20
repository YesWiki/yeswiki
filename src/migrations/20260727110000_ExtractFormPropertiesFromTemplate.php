<?php

use YesWiki\Content\Entity\PageType;
use YesWiki\Core\YesWikiMigration;

/**
 * Ticket 27 (ADR-0010): the five pseudo-fields (titre / acls / metadatas / utilisateur_wikini / bookmarklet) leave the form template and become form properties applied by the entry pipeline.
 */
class ExtractFormPropertiesFromTemplate extends YesWikiMigration
{
    /**
     * @param mixed $value the form-definition cell, whose type the old template never fixed
     */
    private function truthy($value): bool
    {
        return in_array($value, [1, true, '1', 'true', 'yes'], true);
    }

    /**
     * @param array<string, mixed> $pairs
     *
     * @return array<string, mixed> $pairs without its null and blank values
     */
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
            . " WHERE latest = 'Y' AND " . $this->dbService->quoteIdentifier('type')
            . " = '" . $this->dbService->escape(PageType::FORM) . "'"
        );

        foreach ($rows as $row) {
            $body = json_decode($row['body'] ?? '', true);
            if (!is_array($body)) {
                continue;
            }
            $body = $this->extract($body);
            if ($body === null) {
                continue;
            }

            $this->dbService->query(
                'UPDATE ' . $this->dbService->prefixTable('pages')
                . " SET body = '" . $this->dbService->escape(json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
                . "' WHERE id = '" . $this->dbService->escape($row['id']) . "'"
            );
        }
    }

    /** Doryphore's positions for each pseudo-field's attributes, which no field class remains to name once ConvertFormTemplatesToJson has run. */
    private const DORYPHORE_POSITIONS = [
        'titre' => [1 => 'title_template'],
        'acls' => [1 => 'entry_read_right', 2 => 'entry_write_right', 3 => 'entry_comment_right', 7 => 'ask_if_activate_comments'],
        'acl' => [1 => 'entry_read_right', 2 => 'entry_write_right', 3 => 'entry_comment_right', 7 => 'ask_if_activate_comments'],
        'metadatas' => [1 => 'theme', 2 => 'template', 3 => 'style', 4 => 'bg_image', 5 => 'css_preset'],
        'utilisateur_wikini' => [1 => 'name_field', 2 => 'email_field', 5 => 'mailing_list', 6 => 'auto_add_to_group', 9 => 'auto_update_mail'],
        'bookmarklet' => [3 => 'url_field', 4 => 'description_field', 5 => 'text_field'],
    ];

    /**
     * @param array<array-key, mixed> $fieldObject
     *
     * @return array<array-key, mixed> $fieldObject with its positional attributes under their Doryphore names
     */
    private function named(array $fieldObject): array
    {
        foreach (self::DORYPHORE_POSITIONS[$fieldObject['type'] ?? ''] ?? [] as $index => $key) {
            if (!isset($fieldObject[$key]) && isset($fieldObject[$index])) {
                $fieldObject[$key] = $fieldObject[$index];
            }
        }

        return $fieldObject;
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>|null the body with its pseudo-fields turned into properties, or null when there is nothing to change
     */
    public function extract(array $body): ?array
    {
        if (!is_array($body['template'] ?? null)) {
            return null;
        }

        $template = [];
        $changed = false;
        foreach ($body['template'] as $fieldObject) {
            $fieldObject = is_array($fieldObject) ? $this->named($fieldObject) : $fieldObject;
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
            $body['entry_title_template'] = '{{bf_titre}}';
            $changed = true;
        }

        if (!$changed) {
            return null;
        }
        $body['template'] = $template;

        return $body;
    }
}
