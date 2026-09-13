<?php

namespace YesWiki\Content\Service;

use YesWiki\Content\Entity\Translations;
use YesWiki\Content\Field\BazarField;
use YesWiki\Kernel\Service\HtmlPurifierService;
use YesWiki\Kernel\Service\LanguageService;
use YesWiki\Kernel\Service\RuntimeConfig;

/** Which parts of a Content are translatable, and what a reader of this request's language sees. */
class TranslatableContent
{
    /** The form-body keys a translator retypes, beyond the field definitions inside `template`. */
    private const FORM_PROPERTIES = ['label', 'description', 'only_one_entry_message'];

    public function __construct(
        private readonly LanguageService $languageService,
        private readonly HtmlPurifierService $htmlPurifierService,
        private readonly RuntimeConfig $runtimeConfig,
    ) {
    }

    /** The language this request is being served in. */
    public function readerLanguage(): string
    {
        return $this->languageService->preferredLanguage();
    }

    /** The wiki's own language, which every Content body is written in unless its form says otherwise. */
    public function wikiLanguage(): string
    {
        return self::normalize((string)$this->runtimeConfig->getValue('default_language', 'fr')) ?: 'fr';
    }

    /**
     * The language $form's own body is written in.
     *
     * @param array<string, mixed> $form
     */
    public function sourceLanguageOf(array $form): string
    {
        return self::normalize((string)($form['lang'] ?? '')) ?: $this->wikiLanguage();
    }

    /**
     * The language a page's body is written in: what its Metadata declares, else the wiki's own.
     *
     * @param array<string, mixed>|null $metadata
     */
    public function sourceLanguageOfPage(?array $metadata): string
    {
        return self::normalize((string)($metadata['lang'] ?? '')) ?: $this->wikiLanguage();
    }

    /**
     * The languages a translator may fill in for a Content written in $sourceLanguage.
     *
     * @return list<string>
     */
    public function targetLanguages(string $sourceLanguage): array
    {
        return array_values(array_filter(
            $this->languageService->availableLanguages(),
            fn ($language) => $language !== $sourceLanguage
        ));
    }

    /**
     * The language an edit screen is writing: the one it was asked for, and otherwise the one
     * being read. Opening the editor from a page read in English writes the English
     * translation, which is the language the reader was already in; `editlang` names the
     * source language explicitly, so it stays one click away.
     */
    public function editingLanguage(mixed $asked, string $sourceLanguage): string
    {
        $asked = is_string($asked) ? $asked : '';
        $targets = $this->targetLanguages($sourceLanguage);

        if ($asked !== '') {
            return $asked === $sourceLanguage || in_array($asked, $targets, true) ? $asked : $sourceLanguage;
        }

        return in_array($this->readerLanguage(), $targets, true) ? $this->readerLanguage() : $sourceLanguage;
    }

    /**
     * What an edit screen offers to switch between: the source language first, then every target.
     *
     * @param array<string, mixed>                     $body
     * @param list<array{path: string, label: string}> $paths what this Content has to translate, which is what a complete translation is counted against
     *
     * @return list<array{code: string, source: bool, current: bool, state: string}>
     */
    public function editingLanguages(string $sourceLanguage, string $editing, array $body, array $paths = []): array
    {
        $offered = [[
            'code' => $sourceLanguage,
            'source' => true,
            'current' => $editing === $sourceLanguage,
            'state' => 'source',
        ]];
        foreach ($this->targetLanguages($sourceLanguage) as $code) {
            $offered[] = [
                'code' => $code,
                'source' => false,
                'current' => $editing === $code,
                'state' => $this->translationState($body, $code, $paths),
            ];
        }

        return $offered;
    }

    /**
     * How far a translation has got: none of it, some of it, or all of what this Content has to translate.
     *
     * @param array<string, mixed>                     $body
     * @param list<array{path: string, label: string}> $paths
     */
    private function translationState(array $body, string $language, array $paths): string
    {
        $written = Translations::of($body, $language);
        if ($written === []) {
            return 'empty';
        }
        if ($paths === []) {
            return 'full';
        }

        $wanted = array_column($paths, 'path');
        $done = count(array_intersect($wanted, array_keys($written)));

        return $done >= count($wanted) ? 'full' : 'partial';
    }

    /**
     * $body as this request's reader sees it, with the translation store removed either way.
     *
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    public function forReader(array $body, string $sourceLanguage): array
    {
        $language = $this->readerLanguage();
        if ($language === $sourceLanguage) {
            return Translations::strip($body);
        }

        return Translations::apply($body, $language);
    }

    /**
     * What a translator is asked to retype for one entry of $form: one row per translatable field value.
     *
     * @param array<string, mixed> $form
     *
     * @return list<array{path: string, label: string, multiline: bool}>
     */
    public function entryPaths(array $form): array
    {
        $paths = [];
        foreach ($form['prepared'] ?? [] as $field) {
            if (!$field instanceof BazarField || !$field->translatesValue()) {
                continue;
            }
            $propertyName = (string)$field->getPropertyName();
            if ($propertyName === '') {
                continue;
            }
            $paths[] = [
                'path' => $propertyName,
                'label' => $field->getLabel() ?: $propertyName,
                'multiline' => $field->getType() !== 'text',
            ];
        }

        return $paths;
    }

    /**
     * What a translator is asked to retype for a form: its own wording, then each field's.
     *
     * @param array<string, mixed> $form
     *
     * @return list<array{path: string, label: string, multiline: bool}>
     */
    public function formPaths(array $form): array
    {
        $paths = [];
        foreach (self::FORM_PROPERTIES as $property) {
            if (trim((string)($form[$property] ?? '')) === '') {
                continue;
            }
            $paths[] = [
                'path' => $property,
                'label' => _t('TRANSLATE_FORM_' . strtoupper($property)),
                'multiline' => $property !== 'label',
            ];
        }

        $byName = [];
        foreach ($form['prepared'] ?? [] as $field) {
            if ($field instanceof BazarField && (string)$field->getName() !== '') {
                $byName[(string)$field->getName()] = $field;
            }
        }

        foreach ($form['template'] ?? [] as $fieldObject) {
            if (!is_array($fieldObject)) {
                continue;
            }
            $name = (string)($fieldObject['name'] ?? '');
            $field = $byName[$name] ?? null;
            if ($name === '' || $field === null) {
                continue;
            }
            foreach ($field->translatableAttributes() as $attribute) {
                if (trim((string)($fieldObject[$attribute] ?? '')) === '') {
                    continue;
                }
                $paths[] = [
                    'path' => 'template.' . $name . '.' . $attribute,
                    'label' => _t('TRANSLATE_FIELD_' . strtoupper($attribute), ['field' => $field->getLabel() ?: $name]),
                    'multiline' => $attribute !== 'label',
                ];
            }
        }

        return $paths;
    }

    /**
     * What a translator is asked to retype for a value list: its title, then every node's label.
     *
     * @param array<string, mixed> $list
     *
     * @return list<array{path: string, label: string, multiline: bool}>
     */
    public function listPaths(array $list): array
    {
        $paths = [[
            'path' => 'title',
            'label' => _t('TRANSLATE_LIST_TITLE'),
            'multiline' => false,
        ]];

        self::collectNodePaths($list['nodes'] ?? [], 'nodes', $paths);

        return $paths;
    }

    /**
     * The posted translations, cleaned and restricted to the paths that were offered.
     *
     * @param array<array-key, mixed>                                   $posted
     * @param list<array{path: string, label: string, multiline: bool}> $offered
     *
     * @return array<string, string>
     */
    public function sanitize(array $posted, array $offered): array
    {
        $allowed = array_column($offered, 'path');

        $values = [];
        foreach ($posted as $path => $text) {
            if (!is_string($path) || !in_array($path, $allowed, true) || !is_scalar($text)) {
                continue;
            }
            $text = trim((string)$text);
            if ($text !== '') {
                $values[$path] = $this->htmlPurifierService->cleanHTML($text);
            }
        }

        return $values;
    }

    /**
     * @param array<array-key, mixed>                                   $nodes
     * @param list<array{path: string, label: string, multiline: bool}> &$paths
     */
    private static function collectNodePaths(array $nodes, string $prefix, array &$paths): void
    {
        foreach ($nodes as $node) {
            if (!is_array($node) || !isset($node['id'])) {
                continue;
            }
            $id = (string)$node['id'];
            $paths[] = [
                'path' => $prefix . '.' . $id . '.label',
                'label' => (string)($node['label'] ?? $id),
                'multiline' => false,
            ];
            if (is_array($node['children'] ?? null)) {
                self::collectNodePaths($node['children'], $prefix . '.' . $id . '.children', $paths);
            }
        }
    }

    /** `fr-FR` and `FR` both name the `fr` catalogue. */
    private static function normalize(string $language): string
    {
        return strtolower(explode('-', trim($language))[0]);
    }
}
