<?php

namespace YesWiki\Search\Service;

use YesWiki\Content\Field\EnumField;
use YesWiki\Content\Service\FormManager;

/**
 * Resolves a searched *label* to the option *keys* the index stores (ticket 18 / ADR-0015).
 *
 * An enum or checkbox field stores `3` or `atelier`; a visitor types "Atelier participatif".
 * Something has to bridge that, and where it happens decides what a wiki costs to run:
 *
 * - at **index** time -- store the label -- and renaming one option invalidates every entry
 *   referencing it. On a form with hundreds of thousands of entries that is hours of
 *   reindexing triggered by one word typed in the designer.
 * - at **query** time -- here -- and the cost is the number of forms, which is a few dozen.
 *   Renaming an option costs nothing at all, because no entry ever stored the label.
 *
 * `SearchManager::searchWithLists()` already had this idea and is the ancestor of this
 * class. What does not come with it is that method's *output*: it built
 * `body LIKE '%"propertyName":"key"%'` predicates and checkbox regex alternations, which
 * only ever existed because the text search had no index to consult.
 */
class FormOptionTranslator
{
    private FormManager $formManager;

    /** @var array<string, list<string>>|null word => keys, built once per request */
    private ?array $keysByWord = null;

    /** @var array<string, string>|null raw stored key => label, for reading an excerpt back */
    private ?array $labelsByKey = null;

    public function __construct(FormManager $formManager)
    {
        $this->formManager = $formManager;
    }

    /**
     * The option keys whose label contains $word, across every form in the wiki.
     *
     * Matching is substring and case/accent-insensitive, because that is how a visitor
     * types a label they half-remember -- "atelier" should find "Ateliers participatifs".
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

    /**
     * The label an enum option key stands for, or null when nothing claims it.
     *
     * The reverse of keysFor(), and the reason it is needed: the index deliberately stores
     * option **keys**, so an excerpt cut from indexed text reads `atelier` or `3` where a
     * visitor expects "Atelier participatif". Only display goes through here -- matching
     * still works on keys, which is what keeps a relabel from costing a reindex.
     *
     * Keys are matched raw, not normalised: the excerpt carries what was stored.
     */
    public function labelForKey(string $key): ?string
    {
        if ($this->labelsByKey === null) {
            $labelsByKey = [];
            $this->walkOptions(function (string $key, string $label) use (&$labelsByKey): void {
                // first form to claim a key wins; a key colliding across forms with two
                // different labels is unresolvable from an excerpt alone, and picking one is
                // better than showing the raw key
                $labelsByKey[$key] ??= $label;
            });
            $this->labelsByKey = $labelsByKey;
        }

        return $this->labelsByKey[trim($key)] ?? null;
    }

    /**
     * normalized label => the keys carrying it.
     *
     * Built from every form once and held for the request. The forms are already cached by
     * FormManager, so this is a walk over data in memory rather than a query per form -- and
     * it is what keeps translation O(forms) instead of O(entries).
     *
     * @return array<string, list<string>>
     */
    private function index(): array
    {
        if ($this->keysByWord !== null) {
            return $this->keysByWord;
        }

        // built into a local first, then assigned: writing straight into the property from
        // inside the callback leaves it nullable as far as static analysis is concerned
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
     * Shared by both directions so that the label→key and key→label maps cannot come to
     * disagree about which options exist -- a class of bug that only shows up as an excerpt
     * displaying a raw key for something the search matched perfectly well.
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
                        // rare, e.g. an option list of usernames
                        $label = implode(' ', array_map('strval', $label));
                    }
                    $visit(trim((string)$key), trim((string)$label));
                }
            }
        }
    }

    /**
     * Lowercased, unaccented, and stripped to what the full-text engines tokenise on.
     *
     * The same reduction search terms get, which is what makes the returned keys safe to
     * interpolate into a match expression -- a key is user data, and an option keyed
     * `'; DROP` would otherwise arrive in a query string no driver can escape.
     */
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
