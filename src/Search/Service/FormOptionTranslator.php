<?php

namespace YesWiki\Search\Service;

use YesWiki\Content\Field\EnumField;
use YesWiki\Content\Service\FormManager;

/** Resolves a searched *label* to the option *keys* the index stores (ticket 18 / ADR-0015). */
class FormOptionTranslator
{
    private FormManager $formManager;

    /**
     * @var array<string, list<string>>|null word => keys, built once per request
     */
    private ?array $keysByWord = null;

    /**
     * @var array<string, string>|null raw stored key => label, for reading an excerpt back
     */
    private ?array $labelsByKey = null;

    public function __construct(FormManager $formManager)
    {
        $this->formManager = $formManager;
    }

    /**
     * The option keys whose label contains $word, across every form in the wiki.
     *
     * @return list<string> already sanitised the same way search terms are, so they are safe
     *                      to hand to SqlDialect::searchMatchExpression()
     */
    public function keysFor(string $word): array
    {
        $word = self::normalize($word);
        if ($word === '') {
            return [];
        }

        $found = [];
        foreach ($this->index() as $label => $keys) {
            if (str_contains($label, $word)) {
                foreach ($keys as $key) {
                    $found[$key] = true;
                }
            }
        }

        return array_keys($found);
    }

    /** The label an enum option key stands for, or null when nothing claims it. */
    public function labelForKey(string $key): ?string
    {
        if ($this->labelsByKey === null) {
            $labelsByKey = [];
            $this->walkOptions(function (string $key, string $label) use (&$labelsByKey): void {
                $labelsByKey[$key] ??= $label;
            });
            $this->labelsByKey = $labelsByKey;
        }

        return $this->labelsByKey[trim($key)] ?? null;
    }

    /**
     * normalized label => the keys carrying it.
     *
     * @return array<string, list<string>>
     */
    private function index(): array
    {
        if ($this->keysByWord !== null) {
            return $this->keysByWord;
        }

        $keysByWord = [];
        $this->walkOptions(function (string $key, string $label) use (&$keysByWord): void {
            $normalizedLabel = self::normalize($label);
            $normalizedKey = self::normalize($key);
            if ($normalizedLabel === '' || $normalizedKey === '') {
                return;
            }
            $keysByWord[$normalizedLabel][] = $normalizedKey;
        });

        foreach ($keysByWord as $label => $keys) {
            $keysByWord[$label] = array_values(array_unique($keys));
        }
        $this->keysByWord = $keysByWord;

        return $keysByWord;
    }

    /**
     * Every (key, label) pair of every enum field of every form, once.
     *
     * @param callable(string, string): void $visit
     */
    private function walkOptions(callable $visit): void
    {
        foreach ($this->formManager->getAll() as $form) {
            foreach ($form['prepared'] ?? [] as $field) {
                if (!$field instanceof EnumField) {
                    continue;
                }
                $options = $field->getOptions();
                if (!is_array($options)) {
                    continue;
                }
                foreach ($options as $key => $label) {
                    if (is_array($label)) {
                        $label = implode(' ', array_map('strval', $label));
                    }
                    $visit(trim((string)$key), trim((string)$label));
                }
            }
        }
    }

    /** Lowercased, unaccented, and stripped to what the full-text engines tokenise on. */
    public static function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT', $value);
        if ($transliterated !== false) {
            $value = $transliterated;
        }

        return (string)preg_replace('/[^\p{L}\p{N}_]+/u', '', $value);
    }
}
