<?php

namespace YesWiki\Render\Component;

use YesWiki\Content\Service\FileManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\MenuManager;
use YesWiki\Kernel\Service\UrlFormatter;

/**
 * The settings rail of a Component, worked out on the server: the values a tag holds, what the panel shows of them,
 * and the tag those values write. The panel posts its whole state back on every change and draws what this answers.
 */
class ComponentSettings
{
    /** The settings drawn as rows of sub-settings, and the parameter each sub-setting writes, the row's key first. */
    public const ROWS = [
        'facets' => ['groups' => 'field', 'titles' => 'title', 'groupicons' => 'icon'],
        'sort-fields' => ['sortfields' => 'field', 'sortfieldstitles' => 'title'],
        'nav-links' => ['links' => 'link', 'titles' => 'title'],
        'reaction' => ['labels' => 'label', 'images' => 'image'],
        'columns-width' => ['columnswidth' => 'field'],
    ];

    public const GEO_DEFAULTS = ['lat' => '46.22763', 'lon' => '2.21374', 'zoom' => '5'];

    private const EXTRA_FIELD_KEYS = ['ACTION_BUILDER_GENERATED_TITLE' => 'title', 'ACTION_BUILDER_CREATION_DATE' => 'created_at', 'ACTION_BUILDER_MODIFICATION_DATE' => 'updated_at', 'ACTION_BUILDER_OWNER' => 'owner', 'URL' => 'url'];

    public function __construct(
        private readonly ComponentRegistry $registry,
        private readonly FormManager $forms,
        private readonly FileManager $files,
        private readonly MenuManager $menus,
        private readonly UrlFormatter $urls,
    ) {
    }

    /**
     * The Component a tag of the page was written by, and the panel's state for it.
     *
     * @return array{component: array<string, mixed>, state: array<string, mixed>}|null
     */
    public function fromTag(string $source): ?array
    {
        $source = trim(preg_replace('/^\{\{|\}\}$/', '', trim($source)) ?? '');
        if (preg_match('/^([a-zA-Z0-9_-]+)(.*)$/s', $source, $match) !== 1) {
            return null;
        }
        $tag = strtolower($match[1]);
        $values = self::attributes($match[2]);
        $matched = $this->registry->match($tag, $values);
        if ($matched === null) {
            return null;
        }
        $component = $matched->toArray();
        foreach ($this->configs($component) as $name => $config) {
            if (!empty($config['decidesTag'])) {
                $values[$name] = $tag;
            }
        }

        return ['component' => $component, 'state' => $this->split($component, $values)];
    }

    /**
     * The panel's state for a Component just picked from the palette: its pinned values and what its settings suggest.
     *
     * @return array<string, mixed>|null
     */
    public function fresh(string $componentId): ?array
    {
        $component = $this->component($componentId);
        if ($component === null) {
            return null;
        }
        $values = [];
        foreach ($component['properties'] ?? [] as $name => $config) {
            $suggested = $config['value'] ?? $config['default'] ?? null;
            if ($suggested !== null && $suggested !== '' && !isset(self::ROWS[$config['type'] ?? ''])) {
                $values[$name] = (string)$suggested;
            }
        }
        foreach ($this->configs($component) as $name => $config) {
            if (!array_key_exists($name, $values) && isset($config['value']) && ($config['type'] ?? '') !== 'checkbox') {
                $values[$name] = (string)$config['value'];
            }
        }

        return $this->split($component, $values);
    }

    /** @return array<string, mixed>|null */
    public function component(string $componentId): ?array
    {
        return $this->registry->byId()[$componentId] ?? null;
    }

    /**
     * The state as the panel posted it, with the one operation a button asked for applied.
     *
     * @param array<string, mixed> $component
     * @param array<string, mixed> $post
     *
     * @return array<string, mixed>
     */
    public function fromPost(array $component, array $post): array
    {
        $values = [];
        foreach ((array)($post['v'] ?? []) as $name => $value) {
            $values[(string)$name] = is_array($value) ? implode(',', array_filter(array_map('strval', $value), static fn ($v) => $v !== '')) : (string)$value;
        }
        foreach ($component['pins'] ?? [] as $name => $value) {
            $values[$name] = (string)$value;
        }
        $state = ['values' => $values, 'special' => [], 'rest' => array_map('strval', (array)($post['rest'] ?? []))];
        foreach ($this->configs($component) as $name => $config) {
            $special = (array)($post['s'][$name] ?? []);
            $type = $config['type'] ?? '';
            if (isset(self::ROWS[$type])) {
                $state['special'][$name] = array_values(array_map(fn ($row) => array_map('strval', (array)$row), (array)($special['rows'] ?? [])));
            } elseif (in_array($type, ['class', 'field-mapping'], true)) {
                $state['special'][$name] = array_map('strval', (array)($special['sub'] ?? []));
            } elseif ($type === 'query') {
                $state['special'][$name] = [
                    'mode' => ($special['mode'] ?? '') === 'raw' ? 'raw' : 'visual',
                    'raw' => (string)($special['raw'] ?? ''),
                    'tree' => self::cleanTree((array)($special['tree'] ?? [])),
                ];
            }
        }

        return $this->operate($component, $state, (string)($post['op'] ?? ''));
    }

    /**
     * Everything the panel draws: the settings in their blocks, each with its value, its options and whether it shows.
     *
     * @param array<string, mixed> $component
     * @param array<string, mixed> $state
     *
     * @return array<string, mixed>
     */
    public function view(array $component, array $state, bool $editing): array
    {
        $fields = $this->fieldsOf($this->selectedForms($component, $state));
        $flat = $this->flatValues($component, $state);
        $panels = [];
        $own = array_filter($component['properties'] ?? [], static fn ($config) => !empty($config['type']));
        if ($own !== []) {
            $panels[] = ['title' => '', 'width' => null, 'class' => 'specific-action-params', 'properties' => $own];
        }
        foreach ($component['groups'] ?? [] as $group) {
            $panels[] = ['title' => $group['title'] ?? '', 'width' => $group['width'] ?? null, 'class' => '', 'properties' => $group['properties'] ?? []];
        }
        foreach ($panels as &$panel) {
            $panel['visible'] = false;
            foreach ($panel['properties'] as $name => $config) {
                $visible = $this->visible($config, $flat, $component['id']);
                $panel['settings'][$name] = $this->settingView((string)$name, $config, $state, $fields, $flat, $component['id'], $editing) + ['visible' => $visible];
                $panel['visible'] = $panel['visible'] || $visible;
            }
            if (count($panels) === 1 && $panel['width'] === null) {
                $panel['width'] = '50%';
            }
        }

        return [
            'component' => $component,
            'panels' => $panels,
            'carried' => $this->carried($component, $state),
            'rest' => $state['rest'],
            'code' => $this->code($component, $state, $editing),
            'preview_code' => $this->code($component, $state, false),
        ];
    }

    /**
     * The tag the panel's values write: what is left at its default or hidden is left out, `id` and `template` lead.
     *
     * @param array<string, mixed> $component
     * @param array<string, mixed> $state
     */
    public function code(array $component, array $state, bool $editing): string
    {
        $params = $this->params($component, $state);
        $tag = $this->tagOf($component, $state['values']);
        $code = '{{' . $tag;
        foreach ($params as $name => $value) {
            $code .= ' ' . $name . '="' . $value . '"';
        }
        $code .= '}}';
        if (!empty($component['isWrapper']) && !$editing) {
            $code .= $this->wrappedContent($component, $params, $state['values']) . '{{end elem="' . $component['id'] . '"}}';
        }

        return $code;
    }

    /**
     * @param array<string, mixed> $component
     * @param array<string, mixed> $state
     *
     * @return array<string, string>
     */
    private function params(array $component, array $state): array
    {
        $configs = $this->configs($component);
        $flat = $this->flatValues($component, $state);
        $result = [];
        foreach ($state['values'] as $name => $value) {
            $config = $configs[$name] ?? null;
            if ($config !== null && (self::isSpecial($config) || ($config['mapped'] ?? true) === false || in_array($config['type'] ?? '', ['divider', 'hint'], true)
                || self::isDefault($config, $value) || !$this->visible($config, $flat, $component['id']) || !empty($config['writesTo']))) {
                continue;
            }
            $result[$name] = $value;
        }
        foreach (self::sharedTargets($configs) as $target => $names) {
            $tokens = [];
            foreach ($names as $name) {
                if ($this->visible($configs[$name], $flat, $component['id'])) {
                    $tokens[] = trim((string)($state['values'][$name] ?? ''));
                }
            }
            $tokens[] = trim((string)($state['rest'][$target] ?? ''));
            $joined = implode(' ', array_filter($tokens, static fn ($t) => $t !== ''));
            if ($joined !== '') {
                $result[$target] = $joined;
            }
        }
        foreach ($configs as $name => $config) {
            if (self::isSpecial($config) && $this->visible($config, $flat, $component['id'])) {
                $result = array_merge($result, $this->specialParams((string)$name, $config, $state));
            }
        }
        if ($component['id'] === 'entrylist' && empty($result['template'])) {
            $result['template'] = 'liste_accordeon';
        }

        $ordered = [];
        foreach (['id', 'template'] as $first) {
            if (isset($result[$first]) && $result[$first] !== '') {
                $ordered[$first] = $result[$first];
            }
        }
        $rest = array_diff_key($result, $ordered);
        ksort($rest);
        foreach ($rest as $name => $value) {
            if ((string)$value !== '') {
                $ordered[$name] = (string)$value;
            }
        }

        return $ordered;
    }

    /**
     * What a composite setting writes: a list of rows, a mapping, a class, a query or a place on a map.
     *
     * @param array<string, mixed> $config
     * @param array<string, mixed> $state
     *
     * @return array<string, string>
     */
    private function specialParams(string $name, array $config, array $state): array
    {
        $type = $config['type'];
        $special = $state['special'][$name] ?? [];
        if ($type === 'columns-width') {
            $pairs = array_filter(array_map(static fn ($row) => ($row['field'] ?? '') !== '' && ($row['width'] ?? '') !== '' ? $row['field'] . '=' . $row['width'] : '', $special));

            return ['columnswidth' => implode(',', $pairs)];
        }
        if (isset(self::ROWS[$type])) {
            $columns = self::ROWS[$type];
            $key = reset($columns);
            $rows = array_values(array_filter($special, static fn ($row) => trim((string)($row[$key] ?? '')) !== ''));
            $params = [];
            foreach ($columns as $param => $column) {
                $cells = array_map(static fn ($row) => trim((string)($row[$column] ?? '')), $rows);
                if ($type === 'sort-fields' && $column === 'title') {
                    $cells = array_map(static fn ($cell, $row) => $cell !== '' ? $cell : self::titleOf((string)$row['field']), $cells, $rows);
                }
                if ($type === 'nav-links' && $column === 'link') {
                    $cells = array_map(static fn ($cell) => (string)preg_replace('/\s+/', '-', $cell), $cells);
                }
                $params[$param] = array_filter($cells, static fn ($c) => $c !== '') === [] ? '' : implode(',', $cells);
            }

            return $params;
        }
        if ($type === 'field-mapping') {
            $pairs = [];
            foreach ($config['subproperties'] ?? [] as $slot => $sub) {
                $value = (string)($special[$slot] ?? '');
                if ($value !== '' && $value !== ',' && $value !== (string)($sub['default'] ?? '')) {
                    $pairs[] = $slot . '=' . $value;
                }
            }

            return [$name => implode(',', $pairs)];
        }
        if ($type === 'class') {
            $classes = array_values(array_filter(array_map('trim', $special), static fn ($c) => $c !== ''));
            if (empty($state['values']['text']) && !empty($state['values']['icon'])) {
                $classes[] = 'btn-icon';
            }

            return ['class' => implode(' ', $classes)];
        }
        if ($type === 'query') {
            return [$name => $special['mode'] === 'raw' ? trim($special['raw']) : QueryTree::write($special['tree'])];
        }
        if ($type === 'geo') {
            $params = [];
            foreach (self::GEO_DEFAULTS as $param => $default) {
                $value = (string)($state['values'][$param] ?? '');
                if ($value !== '' && (float)$value !== (float)$default) {
                    $params[$param] = $value;
                }
            }

            return $params;
        }

        return [];
    }

    /**
     * Take a tag's flat parameters apart into what each setting edits: rows, mappings, classes, a query, shared tokens.
     *
     * @param array<string, mixed>  $component
     * @param array<string, string> $values
     *
     * @return array<string, mixed>
     */
    private function split(array $component, array $values): array
    {
        foreach ($component['pins'] ?? [] as $name => $value) {
            $values[$name] = (string)$value;
        }
        $configs = $this->configs($component);
        $state = ['values' => $values, 'special' => [], 'rest' => []];
        foreach (self::sharedTargets($configs) as $target => $names) {
            $tokens = preg_split('/\s+/', trim((string)($values[$target] ?? ''))) ?: [];
            $claimed = [];
            foreach ($names as $name) {
                $mine = array_values(array_intersect(self::tokensOf($configs[$name]), $tokens));
                if ($mine !== []) {
                    $state['values'][$name] = $mine[0];
                    $claimed[] = $mine[0];
                } elseif (($configs[$name]['type'] ?? '') === 'checkbox') {
                    $state['values'][$name] = (string)($configs[$name]['uncheckedvalue'] ?? '');
                }
            }
            $state['rest'][$target] = implode(' ', array_filter($tokens, static fn ($t) => $t !== '' && !in_array($t, $claimed, true)));
            unset($state['values'][$target]);
        }
        foreach ($configs as $name => $config) {
            $type = $config['type'] ?? '';
            if (isset(self::ROWS[$type])) {
                $state['special'][$name] = self::rowsFrom($type, $values);
                foreach (array_keys(self::ROWS[$type]) as $param) {
                    unset($state['values'][$param]);
                }
            } elseif ($type === 'field-mapping') {
                $mapping = [];
                foreach (array_filter(explode(',', (string)($values[$name] ?? ''))) as $pair) {
                    [$slot, $field] = array_pad(explode('=', $pair, 2), 2, '');
                    if (isset($config['subproperties'][$slot])) {
                        $mapping[$slot] = $field;
                    }
                }
                $state['special'][$name] = $mapping;
                unset($state['values'][$name]);
            } elseif ($type === 'class') {
                $state['special'][$name] = self::classesFrom($config, (string)($values['class'] ?? ''));
                unset($state['values']['class']);
            } elseif ($type === 'query') {
                $written = (string)($values[$name] ?? '');
                $tree = QueryTree::read($written);
                $state['special'][$name] = $tree === null
                    ? ['mode' => 'raw', 'raw' => trim($written), 'tree' => QueryTree::EMPTY]
                    : ['mode' => 'visual', 'raw' => '', 'tree' => $tree];
                unset($state['values'][$name]);
            }
        }

        return $state;
    }

    /**
     * @param array<string, mixed> $component
     * @param array<string, mixed> $state
     *
     * @return array<string, mixed>
     */
    private function operate(array $component, array $state, string $op): array
    {
        $parts = explode(':', $op);
        $name = $parts[1] ?? '';
        $config = $this->configs($component)[$name] ?? null;
        if ($config === null) {
            return $state;
        }
        $type = $config['type'] ?? '';
        if ($parts[0] === 'add-row' && isset(self::ROWS[$type])) {
            $state['special'][$name][] = array_fill_keys(array_keys($config['subproperties'] ?? []), '');
        } elseif ($parts[0] === 'remove-row' && isset(self::ROWS[$type])) {
            array_splice($state['special'][$name], (int)($parts[2] ?? -1), 1);
        } elseif ($type === 'query') {
            $query = $state['special'][$name];
            $path = array_values(array_filter(explode('.', $parts[2] ?? ''), static fn ($p) => $p !== ''));
            $state['special'][$name] = match ($parts[0]) {
                'query-add-condition' => ['tree' => QueryTree::add($query['tree'], $path, QueryTree::CONDITION)] + $query,
                'query-add-group' => ['tree' => QueryTree::add($query['tree'], $path, QueryTree::GROUP)] + $query,
                'query-remove' => ['tree' => QueryTree::remove($query['tree'], $path)] + $query,
                'query-raw' => ['mode' => 'raw', 'raw' => QueryTree::write($query['tree'])] + $query,
                'query-visual' => ($tree = QueryTree::read($query['raw'])) === null ? $query : ['mode' => 'visual', 'tree' => $tree] + $query,
                default => $query,
            };
        }

        return $state;
    }

    /**
     * What one setting shows: its value, the options a select offers, the rows of a list.
     *
     * @param array<string, mixed>       $config
     * @param array<string, mixed>       $state
     * @param list<array<string, mixed>> $fields
     * @param array<string, string>      $flat
     *
     * @return array<string, mixed>
     */
    private function settingView(string $name, array $config, array $state, array $fields, array $flat, string $componentId, bool $editing): array
    {
        $type = $config['type'] ?? 'hidden';
        $value = $state['values'][$name] ?? null;
        $shown = $value ?? (string)($editing ? ($config['default'] ?? '') : ($config['value'] ?? $config['default'] ?? ''));
        $view = ['name' => $name, 'config' => $config, 'type' => $type, 'value' => (string)$shown];

        if ($type === 'list') {
            $view['options'] = $this->listOptions($config, $fields, $flat, $componentId);
        } elseif ($type === 'form-field') {
            $view['options'] = $this->fieldOptions($config, $fields);
            $view['picked'] = array_values(array_filter(explode(',', (string)$shown)));
        } elseif ($type === 'menu-list') {
            $view['options'] = array_map('strval', $this->menus->readable());
        } elseif ($type === 'image') {
            $entry = $shown === '' ? null : $this->files->getOne((string)$shown);
            $view['imageUrl'] = empty($entry) ? '' : $this->urls->href('', 'api/files/' . rawurlencode((string)$shown) . '/download', [], false);
        } elseif ($type === 'form-list') {
            $view['options'] = array_map('strval', $this->forms->getAllLabels());
            ksort($view['options'], SORT_NATURAL);
            $view['picked'] = array_values(array_filter(array_map('trim', explode(',', (string)$shown))));
        } elseif ($type === 'checkbox') {
            $checked = (string)($config['checkedvalue'] ?? 'true');
            $view['checked'] = (string)($value ?? $config['default'] ?? 'false') === $checked;
        } elseif (isset(self::ROWS[$type])) {
            $view['rows'] = array_map(fn (array $row) => $this->subViews($config, $row, $fields, $flat, $componentId, $editing), $state['special'][$name] ?? []);
        } elseif (in_array($type, ['class', 'field-mapping'], true)) {
            $view['subs'] = $this->subViews($config, $state['special'][$name] ?? [], $fields, $flat, $componentId, $editing, $type === 'class' && !$editing);
        } elseif ($type === 'query') {
            $view['query'] = $state['special'][$name];
            $view['fields'] = $this->fieldOptions(['extraFields' => ['form_id', 'created_at', 'updated_at']], $fields, true);
        } elseif ($type === 'geo') {
            foreach (self::GEO_DEFAULTS as $param => $default) {
                $view[$param] = (string)($state['values'][$param] ?? $default);
            }
        }

        return $view;
    }

    /**
     * @param array<string, mixed>       $config
     * @param array<string, mixed>       $values
     * @param list<array<string, mixed>> $fields
     * @param array<string, string>      $flat
     *
     * @return array<string, array<string, mixed>>
     */
    private function subViews(array $config, array $values, array $fields, array $flat, string $componentId, bool $editing, bool $suggest = false): array
    {
        $subs = [];
        foreach ($config['subproperties'] ?? [] as $sub => $subConfig) {
            $subValue = array_key_exists($sub, $values) ? $values[$sub] : ($suggest ? ($subConfig['value'] ?? $subConfig['default'] ?? null) : ($subConfig['default'] ?? null));
            $subs[$sub] = $this->settingView((string)$sub, $subConfig, ['values' => [$sub => $subValue === null ? null : (string)$subValue], 'special' => []], $fields, $flat, $componentId, $editing)
                + ['visible' => $this->visible($subConfig, $flat, $componentId)];
        }

        return $subs;
    }

    /**
     * The choices of a select: its own, or the options of the form field another setting names.
     *
     * @param array<string, mixed>       $config
     * @param list<array<string, mixed>> $fields
     * @param array<string, string>      $flat
     *
     * @return array<string, string>
     */
    private function listOptions(array $config, array $fields, array $flat, string $componentId): array
    {
        if (!empty($config['dataFromFormField'])) {
            foreach ($fields as $field) {
                if ((string)$field['id'] === (string)($flat[$config['dataFromFormField']] ?? '')) {
                    return array_map('strval', (array)($field['options'] ?? []));
                }
            }

            return [];
        }
        $options = [];
        foreach ($config['options'] ?? [] as $value => $option) {
            if (is_array($option)) {
                if (!empty($option['showif']) && !$this->visible($option, $flat, $componentId)) {
                    continue;
                }
                $options[(string)$value] = (string)($option['label'] ?? $value);
            } else {
                $options[(string)$value] = (string)$option;
            }
        }

        return $options;
    }

    /**
     * The fields a form-field setting offers, narrowed to the kinds it accepts.
     *
     * @param array<string, mixed>       $config
     * @param list<array<string, mixed>> $fields
     *
     * @return list<array<string, mixed>>
     */
    private function fieldOptions(array $config, array $fields, bool $withExtras = false): array
    {
        $extras = array_values((array)($config['extraFields'] ?? []));
        $formIds = array_unique(array_column(array_filter($fields, static fn ($f) => isset($f['form'])), 'form'));
        $offered = array_values(array_filter($fields, static fn ($f) => !isset($f['extra'])));
        foreach ($fields as $field) {
            if (isset($field['extra']) && in_array($field['id'], $extras, true) && ($field['id'] !== 'form_id' || count($formIds) > 1 || $withExtras)) {
                $offered[] = $field;
            }
        }
        if (($config['only'] ?? '') === 'lists') {
            $offered = array_values(array_filter($offered, static fn ($f) => is_array($f['options'] ?? null)));
        }
        if (!empty($config['fieldTypes'])) {
            $offered = array_values(array_filter($offered, static fn ($f) => empty($f['type']) || in_array($f['type'], $config['fieldTypes'], true)));
        }

        return array_values(array_filter($offered, static fn ($f) => (string)($f['label'] ?? '') !== ''));
    }

    /**
     * The fields of the forms the component is pointed at, then the values every entry has.
     *
     * @param list<array<string, mixed>> $forms
     *
     * @return list<array<string, mixed>>
     */
    private function fieldsOf(array $forms): array
    {
        $fields = [];
        foreach ($forms as $form) {
            foreach ($form['prepared'] ?? [] as $field) {
                $described = json_decode((string)json_encode($field), true);
                if (!is_array($described) || empty($described['id']) || isset($fields[$described['id']])) {
                    continue;
                }
                $fields[$described['id']] = $described + ['form' => (string)$form['id']];
            }
        }
        $fields = array_values($fields);
        $fields[] = ['id' => 'form_id', 'label' => _t('ACTION_BUILDER_FORM_ID'), 'extra' => true, 'options' => array_combine(
            array_map(static fn ($f) => (string)$f['id'], $forms),
            array_map(static fn ($f) => (string)($f['bn_label_nature'] ?? $f['label'] ?? $f['id']), $forms)
        ) ?: []];
        foreach (self::EXTRA_FIELD_KEYS as $label => $id) {
            $fields[] = ['id' => $id, 'label' => _t($label), 'extra' => true];
        }

        return $fields;
    }

    /**
     * @param array<string, mixed> $component
     * @param array<string, mixed> $state
     *
     * @return list<array<string, mixed>>
     */
    private function selectedForms(array $component, array $state): array
    {
        $flat = $this->flatValues($component, $state);
        foreach ($this->configs($component) as $name => $config) {
            if (($config['type'] ?? '') !== 'form-list' || !$this->visible($config, $flat, $component['id'])) {
                continue;
            }
            $forms = [];
            foreach (explode(',', (string)($state['values'][$name] ?? '')) as $id) {
                $id = (string)preg_replace('/^https?:\/\/.+->(\d+)$/u', '$1', trim($id));
                if (preg_match('/^\d+$/', $id) === 1 && ($form = $this->forms->getOne($id)) !== null) {
                    $forms[] = $form;
                }
            }

            return $forms;
        }

        return [];
    }

    /**
     * Whether a setting shows, by its conditions on the other values and the Components it is limited to.
     *
     * @param array<string, mixed>  $config
     * @param array<string, string> $flat
     */
    public function visible(array $config, array $flat, string $componentId): bool
    {
        if (!empty($config['showOnlyFor']) && !in_array($componentId, $config['showOnlyFor'], true)) {
            return false;
        }
        if (!empty($config['showExceptFor']) && in_array($componentId, $config['showExceptFor'], true)) {
            return false;
        }
        $conditions = $config['showif'] ?? null;
        if ($conditions === null) {
            return true;
        }
        if (is_string($conditions)) {
            $conditions = [$conditions => 'notNull'];
        }
        foreach ($conditions as $field => $expected) {
            $said = $flat[$field] ?? '';
            if ($field === 'file') {
                $said = $this->fileName($said);
            }
            $value = $said === '' ? 'false' : $said;
            if ($expected === 'notNull') {
                if (in_array($value, ['', 'false'], true)) {
                    return false;
                }
            } elseif (@preg_match('/' . str_replace('/', '\/', (string)$expected) . '/i', $value) !== 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * Every value as one flat list, the composite settings' written parameters included: what conditions are read against.
     *
     * @param array<string, mixed> $component
     * @param array<string, mixed> $state
     *
     * @return array<string, string>
     */
    private function flatValues(array $component, array $state): array
    {
        $flat = array_map('strval', $state['values']);
        foreach ($this->configs($component) as $name => $config) {
            if (self::isSpecial($config)) {
                $flat = array_merge($flat, $this->specialParams((string)$name, $config, $state));
            }
        }

        return $flat;
    }

    /**
     * The values no setting draws, kept as they came so that editing a tag does not lose them.
     *
     * @param array<string, mixed> $component
     * @param array<string, mixed> $state
     *
     * @return array<string, string>
     */
    private function carried(array $component, array $state): array
    {
        $drawn = [];
        foreach ($this->configs($component) as $name => $config) {
            $drawn[] = $name;
            if (($config['type'] ?? '') === 'geo') {
                array_push($drawn, ...array_keys(self::GEO_DEFAULTS));
            }
        }

        return array_diff_key($state['values'], array_flip($drawn), $component['pins'] ?? []);
    }

    /**
     * @param array<string, mixed> $component
     *
     * @return array<string, array<string, mixed>>
     */
    private function configs(array $component): array
    {
        $configs = $component['properties'] ?? [];
        foreach ($component['groups'] ?? [] as $group) {
            $configs = array_merge($configs, $group['properties'] ?? []);
        }

        return $configs;
    }

    /**
     * @param array<string, mixed>  $component
     * @param array<string, string> $values
     */
    private function tagOf(array $component, array $values): string
    {
        $tags = $component['tags'] ?? [];
        foreach ($this->configs($component) as $name => $config) {
            if (!empty($config['decidesTag']) && in_array($values[$name] ?? null, $tags, true)) {
                return (string)$values[$name];
            }
        }

        return (string)($tags[0] ?? $component['id']);
    }

    /**
     * What a wrapping component holds when it is first written: an example, repeated for tabs, accordions and grids.
     *
     * @param array<string, mixed>  $component
     * @param array<string, string> $params
     * @param array<string, string> $values
     */
    private function wrappedContent(array $component, array $params, array $values): string
    {
        $example = (string)($component['wrappedContentExample'] ?? '');
        $id = $component['id'];
        if ($id === 'tabs' && !empty($params['titles'])) {
            $example = implode("\n", array_map(static fn ($title) => str_replace('{tabName}', $title, $example), explode(',', $params['titles'])));
        }
        $count = max(0, (int)($values['nb'] ?? 0));
        if ($id === 'accordion') {
            return "\n" . str_repeat($example . "\n", $count);
        }
        if ($id === 'grid') {
            $size = $count > 0 ? 12 / $count : 12;

            return "\n" . str_repeat(str_replace('{size}', (string)$size, $example) . "\n", $count);
        }

        return $id === 'label' ? $example : "\n" . $example . "\n";
    }

    private function fileName(string $value): string
    {
        if ($value === '' || preg_match('/\.[a-z0-9]+$/i', $value) === 1) {
            return $value;
        }
        $entry = $this->files->getOne($value);
        $name = (string)($entry['original_filename'] ?? $value);
        $extension = strtolower((string)($entry['extension'] ?? ''));

        return $extension !== '' && !str_ends_with(strtolower($name), '.' . $extension) ? $name . '.' . $extension : $name;
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function isSpecial(array $config): bool
    {
        return isset(self::ROWS[$config['type'] ?? '']) || in_array($config['type'] ?? '', ['class', 'field-mapping', 'query', 'geo'], true);
    }

    /** @param array<string, mixed>|null $config */
    private static function isDefault(?array $config, string $value): bool
    {
        if ($config === null) {
            return false;
        }
        $fallback = array_key_exists('default', $config) ? $config['default'] : (($config['type'] ?? '') === 'checkbox' ? ($config['uncheckedvalue'] ?? 'false') : null);
        if (is_bool($fallback)) {
            $fallback = $fallback ? 'true' : 'false';
        }

        return $fallback !== null && $value === (string)$fallback;
    }

    /**
     * @param array<string, array<string, mixed>> $configs
     *
     * @return array<string, list<string>>
     */
    private static function sharedTargets(array $configs): array
    {
        $targets = [];
        foreach ($configs as $name => $config) {
            if (!empty($config['writesTo'])) {
                $targets[$config['writesTo']][] = (string)$name;
            }
        }

        return $targets;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return list<string>
     */
    private static function tokensOf(array $config): array
    {
        if (($config['type'] ?? '') === 'list') {
            return array_map('strval', array_keys($config['options'] ?? []));
        }

        return ($config['type'] ?? '') === 'checkbox' && isset($config['checkedvalue']) ? [(string)$config['checkedvalue']] : [];
    }

    /**
     * @param array<string, string> $values
     *
     * @return list<array<string, string>>
     */
    private static function rowsFrom(string $type, array $values): array
    {
        if ($type === 'columns-width') {
            $rows = [];
            foreach (array_filter(explode(',', (string)($values['columnswidth'] ?? ''))) as $pair) {
                $parts = explode('=', $pair, 2);
                $rows[] = ['field' => $parts[0], 'width' => $parts[1] ?? ''];
            }

            return $rows;
        }
        $lists = [];
        foreach (self::ROWS[$type] as $param => $column) {
            $lists[$column] = ($values[$param] ?? '') === '' ? [] : explode(',', (string)$values[$param]);
        }
        $keyColumn = (string)array_values(self::ROWS[$type])[0];
        $rows = [];
        foreach (array_keys($lists[$keyColumn] ?? []) as $index) {
            $row = [];
            foreach ($lists as $column => $list) {
                $row[$column] = (string)($list[$index] ?? '');
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Which of a class setting's sub-settings the written classes stand for.
     *
     * @param array<string, mixed> $config
     *
     * @return array<string, string>
     */
    private static function classesFrom(array $config, string $written): array
    {
        $classes = array_values(array_filter(explode(' ', $written)));
        $pairs = [];
        foreach ($classes as $index => $class) {
            if (isset($classes[$index + 1])) {
                $pairs[] = $class . ' ' . $classes[$index + 1];
            }
        }
        $candidates = [...$classes, ...$pairs];
        $values = [];
        foreach ($config['subproperties'] ?? [] as $sub => $subConfig) {
            $values[$sub] = (string)($subConfig['default'] ?? '');
            $own = ($subConfig['type'] ?? '') === 'list'
                ? array_map('strval', array_keys($subConfig['options'] ?? []))
                : array_values(array_filter([(string)($subConfig['checkedvalue'] ?? ''), (string)($subConfig['uncheckedvalue'] ?? '')]));
            foreach ($candidates as $candidate) {
                if (in_array($candidate, $own, true)) {
                    $values[$sub] = $candidate;
                }
            }
        }

        return $values;
    }

    /** @return array<string, string> the `name="value"` pairs of a tag */
    private static function attributes(string $source): array
    {
        preg_match_all('/([a-zA-Z0-9_-]+)\s*=\s*"([^"]*)"/', $source, $matches, PREG_SET_ORDER);
        $values = [];
        foreach ($matches as $match) {
            $values[strtolower($match[1])] = html_entity_decode($match[2], ENT_QUOTES);
        }

        return $values;
    }

    private static function titleOf(string $field): string
    {
        return ucwords(str_replace('_', ' ', (string)preg_replace('/^bf_/', '', $field)));
    }

    /**
     * @param array<array-key, mixed> $node
     *
     * @return array<string, mixed>
     */
    private static function cleanTree(array $node): array
    {
        if (isset($node['children']) || isset($node['connector'])) {
            return [
                'connector' => ($node['connector'] ?? 'AND') === 'OR' ? 'OR' : 'AND',
                'children' => array_values(array_map(static fn ($child) => self::cleanTree((array)$child), (array)($node['children'] ?? []))),
            ];
        }

        return ['field' => (string)($node['field'] ?? ''), 'operator' => in_array($node['operator'] ?? '=', QueryTree::OPERATORS, true) ? (string)($node['operator'] ?? '=') : '=', 'value' => (string)($node['value'] ?? '')];
    }
}
