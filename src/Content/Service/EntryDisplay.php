<?php

namespace YesWiki\Content\Service;

use Psr\Container\ContainerInterface;
use YesWiki\Content\Controller\EntryController;
use YesWiki\Content\Field\DateField;
use YesWiki\Content\Field\EnumField;
use YesWiki\Content\Field\MapField;
use YesWiki\Render\Service\TemplateEngine;

/** The three questions a list template asks about an entry it is drawing. */
class EntryDisplay
{
    public function __construct(private readonly ContainerInterface $services)
    {
    }

    /**
     * The `data-*` attributes an entry's row carries, so a filter can act on it client-side.
     *
     * @param array<string, mixed>|mixed $entry
     * @param array<mixed>|string        $formtab forms already loaded, to save a lookup per row
     */
    public function dataAttributes($entry, $formtab = ''): string
    {
        $htmldata = '';
        if (is_array($entry) && isset($entry['form_id'])) {
            $form = isset($formtab[$entry['form_id']]) ? $formtab[$entry['form_id']] : $this->services->get(FormManager::class)->getOne($entry['form_id']);
            foreach ($entry as $key => $value) {
                if (!empty($value)) {
                    if (
                        in_array(
                            $key,
                            [
                                'form_id',
                                'owner',
                                'created_at',
                                'date_debut_validite_fiche',
                                'date_fin_validite_fiche',
                                'tag',
                                'status',
                                'updated_at',
                            ]
                        )
                    ) {
                        $htmldata .=
                            'data-' . htmlspecialchars($key) . '="' .
                            htmlspecialchars($value) . '" ';
                    } else {
                        if (isset($form['prepared'])) {
                            foreach ($form['prepared'] as $field) {
                                $propertyName = $field->getPropertyName();
                                if ($propertyName === $key) {
                                    if (
                                        $field instanceof MapField
                                        || $field instanceof EnumField
                                        || $field instanceof DateField
                                        || $field->getName() == 'scope'
                                    ) {
                                        $htmldata .=
                                            'data-' . htmlspecialchars($key) . '="' .
                                            htmlspecialchars(is_array($value) ? '[' . implode(',', $value) . ']' : $value) . '" ';
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        return $htmldata;
    }

    /**
     * A display parameter (`color=`, `icon=`) resolved for one entry.
     *
     * @param array<mixed>|string|null $parameter
     * @param array<string, mixed>     $entry
     */
    public function customValueFor($parameter, ?string $field, array $entry, mixed $default): mixed
    {
        if (empty($field)) {
            return $default;
        }

        if (is_array($parameter)) {
            foreach (self::valuesOf($entry, $field) as $value) {
                if (isset($parameter[$value])) {
                    return $parameter[$value];
                }
            }

            return $default;
        }

        return $default;
    }

    /**
     * The colour a value carries, read off the list that defines it.
     *
     * @param array<string, mixed> $entry
     */
    public function colorForEntry(?string $field, array $entry, mixed $default = ''): mixed
    {
        $node = $this->listNodeFor($field, $entry);
        $color = is_string($node['color'] ?? null) ? trim($node['color']) : '';

        return $color !== '' ? $color : $default;
    }

    /**
     * The colour a list or a map draws an entry in: the mapped one, then the list's, then a palette token; '' for the stylesheet's.
     *
     * @param array<string, mixed> $entry
     */
    public function markerColorFor(mixed $parameter, ?string $field, array $entry): string
    {
        $values = empty($field) ? [] : array_values(array_filter(self::valuesOf($entry, $field), fn ($value) => $value !== ''));
        $first = $values[0] ?? null;
        if ($first === null) {
            return is_string($parameter) ? trim($parameter) : '';
        }
        $mapped = is_array($parameter) ? ($parameter[$first] ?? null) : null;
        if (is_string($mapped) && trim($mapped) !== '') {
            return trim($mapped);
        }
        $stated = $this->colorForEntry($field, [$field => $first, 'form_id' => $entry['form_id'] ?? null]);
        if (is_string($stated) && $stated !== '') {
            return $stated;
        }

        return self::categoryColorFor($first);
    }

    /** The palette token a value id always lands on: FNV-1a of its UTF-8 bytes, modulo eight. */
    public static function categoryColorFor(string $id): string
    {
        $hash = 0x811C9DC5;
        foreach (unpack('C*', $id) ?: [] as $byte) {
            $hash = (($hash ^ $byte) * 0x01000193) & 0xFFFFFFFF;
        }

        return 'var(--yw-category-' . (($hash % 8) + 1) . ')';
    }

    /**
     * The icon a value carries, as HTML, read off the same list.
     *
     * @param array<string, mixed> $entry
     */
    public function iconForEntry(?string $field, array $entry, mixed $default = ''): mixed
    {
        $node = $this->listNodeFor($field, $entry);
        $icon = is_array($node['icon'] ?? null) ? $node['icon'] : null;
        if ($icon === null) {
            return $default;
        }

        $rendered = $this->services->get(TemplateEngine::class)->renderNodeIcon(
            (string)($icon['source'] ?? ''),
            (string)($icon['value'] ?? '')
        );

        return $rendered ?? $default;
    }

    /**
     * The list node one of an entry's values names, or null when the field is not a list at all.
     *
     * @param array<string, mixed> $entry
     *
     * @return array<string, mixed>|null
     */
    private function listNodeFor(?string $field, array $entry): ?array
    {
        if (empty($field)) {
            return null;
        }

        $form = $this->services->get(FormManager::class)->getOne($entry['form_id'] ?? null);
        $prepared = $form['prepared'] ?? [];
        foreach ($prepared as $candidate) {
            if (!$candidate instanceof EnumField || $candidate->getPropertyName() !== $field) {
                continue;
            }
            $list = $candidate->getLinkedObjectName();
            if ($list === '') {
                return null;
            }
            $nodes = $this->services->get(ListManager::class)->getOne($list)['nodes'] ?? [];

            return self::nodeCarrying($nodes, self::valuesOf($entry, $field));
        }

        return null;
    }

    /**
     * @param array<array-key, mixed> $nodes
     * @param list<string>            $values
     *
     * @return array<string, mixed>|null
     */
    private static function nodeCarrying(array $nodes, array $values): ?array
    {
        foreach ($nodes as $node) {
            if (!is_array($node)) {
                continue;
            }
            if (in_array((string)($node['id'] ?? ''), $values, true)) {
                return $node;
            }
            $found = self::nodeCarrying(is_array($node['children'] ?? null) ? $node['children'] : [], $values);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * What an entry holds in one field, as the list of values it is: a checkbox holds several.
     *
     * @param array<string, mixed> $entry
     *
     * @return list<string>
     */
    private static function valuesOf(array $entry, string $field): array
    {
        $held = $entry[$field] ?? null;
        if (is_array($held)) {
            return array_values(array_map('strval', $held));
        }
        $held = (string)$held;

        return $held === '' ? [] : array_map('trim', explode(',', $held));
    }

    /**
     * One entry rendered whole, or the error that stopped it.
     *
     * @param mixed $inApp   truthy when the management bar should be drawn
     * @param mixed $entryId the entry, by tag or already loaded
     */
    public function renderEntry($inApp, $entryId, mixed $form = ''): string
    {
        try {
            $output = $this->services->get(EntryController::class)->view($entryId, '', $inApp, null, $form);
        } catch (\Throwable $t) {
            return $this->services->get(TemplateEngine::class)
                ->render('@core/alert-message.twig', [
                    'type' => 'danger',
                    'message' => _t('PERFORMABLE_ERROR') . '<br/>' . $this->services->get(\YesWiki\Kernel\Service\ThrowableFormatter::class)->dump($t),
                ]);
        }

        return $output;
    }

    /**
     * One entry rendered whole, or nothing at all when it fails.
     *
     * @param mixed $entryId the entry, by tag or already loaded
     */
    public function renderEntryOrNothing($entryId): string
    {
        try {
            return (string)$this->services->get(EntryController::class)->view($entryId, '', 0);
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Every form and every list this wiki has, by name, for a picker to offer.
     *
     * @return array{lists: array<mixed>, forms: array<mixed>}
     */
    public function formAndListNames(): array
    {
        $forms = [];
        $lists = $this->services->get(ListManager::class)->getAll();
        $lists = array_map(function ($list) {
            return $list['title'];
        }, $lists);
        $forms = $this->services->get(FormManager::class)->getAllLabels();

        return [
            'lists' => $lists,
            'forms' => $forms,
            'menus' => $this->services->get(MenuManager::class)->readable(),
        ];
    }
}
