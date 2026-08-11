<?php

namespace YesWiki\Content\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Content\Service\ListManager;
use YesWiki\Search\Service\SearchManager;

abstract class EnumField extends BazarField
{
    protected $options;
    protected $optionsTree; // only for list with multi levels

    protected $linkedObjectName;
    protected $keywords;
    protected $queries;

    protected const FIELD_LINKED_OBJECT = 1;
    public const FIELD_NAME = 6;
    protected const FIELD_KEYWORDS = 13;
    protected const FIELD_QUERIES = 15;

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->name = $values[self::FIELD_NAME];
        $this->linkedObjectName = $values[self::FIELD_LINKED_OBJECT];
        $this->keywords = $values[self::FIELD_KEYWORDS];
        $this->queries = $values[self::FIELD_QUERIES];

        $this->options = [];

        $this->propertyName = $this->name;
    }

    public function loadOptionsFromList()
    {
        if (!empty($this->getLinkedObjectName())) {
            $list = $this->getService(ListManager::class)->getOne($this->getLinkedObjectName());
            $this->options = [];
            foreach ($list['nodes'] ?? [] as $node) {
                $this->loadOptionsFromListNode($node);
                if (isset($node['children']) && count($node['children']) > 0) {
                    $this->optionsTree = $list['nodes'];
                }
            }
        }
    }

    // Recursively load options from list, in case the list is a tree (with children)
    private function loadOptionsFromListNode($node, $parentLabel = '')
    {
        $this->options[$node['id']] = $parentLabel . $node['label'];
        if (!empty($node['children'])) {
            foreach ($node['children'] as $childNode) {
                $this->loadOptionsFromListNode($childNode, "$parentLabel {$node['label']} ➤ ");
            }
        }
    }

    public function loadOptionsFromEntries()
    {
        $vSearchManager = $this->getService(SearchManager::class);

        if (!empty($this->queries)) {
            $vQueries = $vSearchManager->parseQuery($this->queries);
        } else {
            $vQueries = [];
        }

        $linkedEntries = $vSearchManager->search(
            [
                'queries' => $vQueries,
                'formsIds' => $this->getLinkedObjectName(),
                'keywords' => (!empty($this->keywords)) ? $this->keywords : '',
            ],
            true, // filter on read ACL
            true  // use Guard
        );

        $this->options = [];
        foreach ($linkedEntries as $linkedEntry) {
            $this->options[$linkedEntry['tag']] = $linkedEntry['title'] ?? $linkedEntry['bf_titre'] ?? $linkedEntry['tag'];
        }
        if (is_array($this->options)) {
            asort($this->options);
        }
    }

    /**
     * A linked form named by a URL is no longer resolvable (ticket 34).
     *
     * `loadOptionsFromJson()`, `loadOptionsFromJSONForm()` and `prepareJSONEntryField()` lived
     * here and fetched another wiki's entries or form over HTTP -- through ExternalBazarService's
     * cache -- every time a field needed its options. That made rendering a form depend on a third
     * party being up, and the borrowed options were never searchable nor subject to this wiki's
     * permissions.
     *
     * Content from elsewhere is imported now. This returns the sentence to show in place of the
     * input, or null when the linked form is an ordinary local one.
     */
    protected function remoteLinkedFormNotice(): ?string
    {
        $linked = (string)$this->getLinkedObjectName();
        if ($linked === '' || filter_var($linked, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return _t('BAZ_EXTERNAL_IDS_REMOVED', ['ids' => $linked]);
    }

    public function getOptions()
    {
        return $this->options;
    }

    /**
     * The stored **keys**, not the option labels (ticket 18 / ADR-0015).
     *
     * This is what the inherited implementation already does, and it is overridden here so
     * that it stays that way: indexing the labels is the obvious-looking change, and it is
     * the one that does not scale. An option's label lives in the *form*, so renaming
     * "Atelier" to "Atelier participatif" would invalidate the indexed text of every entry
     * referencing it -- hours of reindexing from one word typed in the designer, on a form
     * with hundreds of thousands of entries.
     *
     * The translation happens at query time instead, where its cost is the number of forms
     * rather than the number of entries. `FormOptionTranslator` does it; a searched label is
     * resolved to the keys that carry it before the index is consulted at all.
     */
    public function searchableText($entry): string
    {
        return parent::searchableText($entry);
    }

    public function getOptionsTree()
    {
        return $this->optionsTree;
    }

    protected function getEntriesOptions()
    {
        // load options only when needed but not at construct to prevent infinite loops
        if (is_null($this->options)) {
            // no remote branch: a linked form is a local form, or it is nothing (ticket 34)
            $this->loadOptionsFromEntries();
        }

        return $this->options;
    }

    public function getLinkedObjectName()
    {
        return $this->linkedObjectName;
    }

    /**
     * check if the current class is EnumEntry.
     */
    public function isEnumEntryField(): bool
    {
        return false;
    }

    // change return of this method to keep compatible with php 7.3 (mixed is not managed)
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return array_merge(
            parent::jsonSerialize(),
            [
                'linkedObjectName' => $this->getLinkedObjectName(),
                'queries' => $this->queries,
                'options' => $this->getOptions(),
            ]
        );
    }
}
