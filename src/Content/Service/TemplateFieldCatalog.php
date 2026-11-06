<?php

namespace YesWiki\Content\Service;

use YesWiki\Content\Field\BazarField;
use YesWiki\Content\Field\LinkedEntryField;
use YesWiki\Content\Field\TextareaField;

/** The form fields a template can print, worked out from its path and its source so that it declares nothing. */
class TemplateFieldCatalog
{
    private const ENTRY_TEMPLATE = '~^core/fiche-(\d+)\.twig$~';
    private const LIST_TEMPLATE = '~^core/entries/index-dynamic-templates/[^/]+\.twig$~';

    public function __construct(private readonly FormManager $formManager)
    {
    }

    /**
     * What the field picker offers for the override at $path, or null when that template has no form behind it.
     *
     * @return array{kind: string, selected: string, forms: list<array{id: string, label: string, fields: list<array<string, mixed>>}>}|null
     */
    public function forTemplate(string $path, string $source): ?array
    {
        if (preg_match(self::ENTRY_TEMPLATE, $path, $match) === 1) {
            $form = $this->formManager->getOne($match[1]);

            return $form === null ? null : [
                'kind' => 'entry',
                'selected' => (string)$form['id'],
                'forms' => [$this->describe($form, false)],
            ];
        }
        if (preg_match(self::LIST_TEMPLATE, $path) !== 1) {
            return null;
        }

        $forms = array_values(array_map(fn (array $form): array => $this->describe($form, true), $this->formManager->getAll()));

        return $forms === [] ? null : [
            'kind' => 'list',
            'selected' => $this->closestForm($forms, $this->fieldsReadBy($source)),
            'forms' => $forms,
        ];
    }

    /**
     * The field names $source already reads.
     *
     * @return list<string>
     */
    public function fieldsReadBy(string $source): array
    {
        preg_match_all('/\b(?:entry|html|formFields)\.([a-zA-Z_]\w*)/', $source, $reads);
        $declared = [];
        if (preg_match_all('/necessary_fields\s*=[^%]*%\}/', $source, $sets) > 0) {
            preg_match_all('/[\'"]([a-zA-Z_]\w*)[\'"]/', implode(' ', $sets[0]), $names);
            $declared = $names[1];
        }

        return array_values(array_unique([...$reads[1], ...$declared]));
    }

    /**
     * @param array<string, mixed> $form
     *
     * @return array{id: string, label: string, fields: list<array<string, mixed>>}
     */
    private function describe(array $form, bool $storedOnly): array
    {
        $fields = [];
        foreach ($form['prepared'] ?? [] as $field) {
            if (!$field instanceof BazarField || empty($field->getName())) {
                continue;
            }
            $stored = !empty($field->getPropertyName());
            $key = $field instanceof LinkedEntryField ? $field->getType() . $field->getName() : (string)$field->getPropertyName();
            if ($key === '' || ($storedOnly && !$stored)) {
                continue;
            }
            $fields[] = [
                'name' => (string)$field->getName(),
                'label' => trim(strip_tags((string)$field->getLabel())),
                'key' => $key,
                'stored' => $stored,
                'html' => $field instanceof TextareaField,
            ];
        }

        return ['id' => (string)$form['id'], 'label' => (string)($form['label'] ?? $form['id']), 'fields' => $fields];
    }

    /**
     * The form holding the most of $names, the first one when none holds any.
     *
     * @param list<array{id: string, label: string, fields: list<array<string, mixed>>}> $forms
     * @param list<string>                                                               $names
     */
    private function closestForm(array $forms, array $names): string
    {
        $best = $forms[0]['id'];
        $bestScore = 0;
        foreach ($forms as $form) {
            $score = count(array_intersect($names, array_column($form['fields'], 'name')));
            if ($score > $bestScore) {
                $best = $form['id'];
                $bestScore = $score;
            }
        }

        return $best;
    }
}
