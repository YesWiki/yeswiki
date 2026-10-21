<?php

use YesWiki\Content\Service\FormManager;
use YesWiki\Core\YesWikiMigration;

/** Forms still on a shipped Agenda or Blog ActivityPub template get the starter's current one; any other template stops reading the retired date keys. */
class ActivityPubTemplatesSpeakMastodonAndMobilizon extends YesWikiMigration
{
    private const SHIPPED = [
        'agenda' => [
            'sem_template' => [
                '71aa6277f8b73dc9881c4846fbf8ec20e7627651',
                '4c599384880b3909e8ebc0d3d96093361645f0c4',
                '1dafc44771fe251f13d9558440d39c538aa815f1',
            ],
            'sem_reverse_template' => [
                '0fd0bc6ffb23042c865e3db83893c2071c0dfe0d',
                '01b4de8b02721ac889884efeca64a4acebe002ff',
                '2ed602337cbae4be83104c6482c25320f94a0302',
            ],
        ],
        'blog' => [
            'sem_template' => [
                'a183bec1a8c2c7dddf343b04c3e61ead147b5af9',
                'a64238db911e1429acf220f6d251067add6040f7',
                '1ec8b88e2607827a6a99ef2957f85ae9911d7748',
            ],
            'sem_reverse_template' => [
                '1eb70ddcf814f73bae1a630914ff172474fce2da',
                '57d89034907168735ded143bbc20a8f75d5f07d3',
                'ab12f9724c5a6123cfb6640a27108bc6fceadbd1',
                '264b131cbeee460c1c622caae777f822ae39af54',
            ],
        ],
    ];

    private const DATES = [
        'date_creation_fiche | replace({" ": "T"})' => 'date_creation_fiche | date("c")',
        'date_maj_fiche | replace({" ": "T"})' => 'date_maj_fiche | default(date_creation_fiche) | date("c")',
        'created_at | replace({" ": "T"})' => 'date_creation_fiche | date("c")',
        'updated_at | replace({" ": "T"})' => 'date_maj_fiche | default(date_creation_fiche) | date("c")',
    ];

    private const WARNING_FIELD = 'bf_avertissement';

    public function run()
    {
        $formManager = $this->getService(FormManager::class);
        $starters = ['agenda' => self::starterForm('agenda'), 'blog' => self::starterForm('blog')];
        $updated = [];

        foreach ($formManager->getAllIds() as $id) {
            $form = $formManager->getUntranslated($id);
            if (!is_array($form)) {
                continue;
            }
            $changed = self::upgrade($form, $starters);
            if ($changed !== $form) {
                $formManager->update($changed);
                $updated[] = (string)($form['label'] ?? $id);
            }
        }

        if ($updated !== []) {
            $this->say('ActivityPub templates brought up to date for ' . implode(', ', $updated) . '.');
        }
    }

    /**
     * The form with its shipped templates replaced and its other templates reading created_at and updated_at.
     *
     * @param array<string, mixed>                     $form
     * @param array<string, array<string, mixed>|null> $starters
     *
     * @return array<string, mixed>
     */
    public static function upgrade(array $form, array $starters): array
    {
        foreach (['sem_template', 'sem_reverse_template'] as $key) {
            $template = is_string($form[$key] ?? null) ? $form[$key] : '';
            if (trim($template) === '') {
                continue;
            }
            $shipped = self::shippedAs($key, $template);
            $starter = $shipped === null ? null : ($starters[$shipped] ?? null);
            if ($starter !== null && is_string($starter[$key] ?? null)) {
                $form[$key] = self::forMapField($starter[$key], $form);
                if ($shipped === 'blog' && $key === 'sem_template') {
                    $form['template'] = self::withWarningField(is_array($form['template'] ?? null) ? $form['template'] : [], $starter);
                }
                continue;
            }
            $form[$key] = (string)preg_replace(
                ['/\bdate_creation_fiche\b/', '/\bdate_maj_fiche\b/'],
                ['created_at', 'updated_at'],
                strtr($template, self::DATES)
            );
        }

        return $form;
    }

    /** Which starter shipped this template, or null when it was written or edited by hand. */
    private static function shippedAs(string $key, string $template): ?string
    {
        $hash = sha1(strtr(trim(str_replace("\r\n", "\n", $template)), self::DATES));
        foreach (self::SHIPPED as $starter => $hashes) {
            if (in_array($hash, $hashes[$key], true)) {
                return $starter;
            }
        }

        return null;
    }

    /**
     * The starter's template reading and writing the form's own map field.
     *
     * @param array<string, mixed> $form
     */
    private static function forMapField(string $template, array $form): string
    {
        foreach (is_array($form['template'] ?? null) ? $form['template'] : [] as $field) {
            if (is_array($field) && ($field['type'] ?? null) === 'map' && is_string($field['name'] ?? null) && $field['name'] !== '') {
                return str_replace('bf_geolocation', $field['name'], $template);
            }
        }

        return $template;
    }

    /**
     * The form's fields with the starter's content warning field after the summary, or last when there is no summary.
     *
     * @param array<mixed>         $fields
     * @param array<string, mixed> $starter
     *
     * @return list<mixed>
     */
    private static function withWarningField(array $fields, array $starter): array
    {
        $fields = array_values($fields);
        $names = array_map(fn ($field) => is_array($field) ? ($field['name'] ?? null) : null, $fields);
        if (in_array(self::WARNING_FIELD, $names, true)) {
            return $fields;
        }
        $warning = null;
        foreach (is_array($starter['template'] ?? null) ? $starter['template'] : [] as $field) {
            if (is_array($field) && ($field['name'] ?? null) === self::WARNING_FIELD) {
                $warning = $field;
            }
        }
        if ($warning === null) {
            return $fields;
        }
        $position = array_search('bf_chapeau', $names, true);
        array_splice($fields, $position === false ? count($fields) : (int)$position + 1, 0, [$warning]);

        return $fields;
    }

    /** @return array<string, mixed>|null */
    private static function starterForm(string $slug): ?array
    {
        $starter = json_decode((string)file_get_contents(__DIR__ . "/../Admin/starters/{$slug}.json"), true);

        return is_array($starter['form'] ?? null) ? $starter['form'] : null;
    }
}
