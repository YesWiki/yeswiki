<?php

namespace YesWiki\Content\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Content\Service\ListManager;
use YesWiki\Search\Service\SearchManager;

abstract class EnumField extends BazarField
{
    /** @var array<int|string, mixed>|null null while the options have not been loaded yet */
    protected $options;

    /** @var array<int|string, mixed>|null the raw list nodes, when the list is hierarchical */
    protected $optionsTree;

    /** @var string name of the list, or id of the form, the options come from */
    protected $linkedObjectName;

    /** @var string */
    protected $keywords;

    /** @var string */
    protected $queries;

    protected const FIELD_LINKED_OBJECT = 1;
    public const FIELD_NAME = 6;
    protected const FIELD_KEYWORDS = 13;
    protected const FIELD_QUERIES = 15;

    /**
     * @param array<int|string, mixed> $values
     */
    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->name = $values[self::FIELD_NAME];
        $this->linkedObjectName = (string)($values[self::FIELD_LINKED_OBJECT] ?? '');
        $this->keywords = (string)($values[self::FIELD_KEYWORDS] ?? '');
        $this->queries = (string)($values[self::FIELD_QUERIES] ?? '');

        $this->options = [];

        $this->propertyName = $this->name;
    }

    /**
     * @return void
     */
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

    /**
     * @param array<string, mixed> $node
     */
    private function loadOptionsFromListNode(array $node, string $parentLabel = ''): void
    {
        $this->options[$node['id']] = $parentLabel . $node['label'];
        if (!empty($node['children'])) {
            foreach ($node['children'] as $childNode) {
                $this->loadOptionsFromListNode($childNode, "$parentLabel {$node['label']} ➤ ");
            }
        }
    }

    /**
     * @return void
     */
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
            true,
            true
        );

        $this->options = [];
        foreach ($linkedEntries as $linkedEntry) {
            $this->options[$linkedEntry['tag']] = $linkedEntry['title'] ?? $linkedEntry['bf_titre'] ?? $linkedEntry['tag'];
        }
        asort($this->options);
    }

    /** A linked form named by a URL is no longer resolvable (ticket 34). */
    protected function remoteLinkedFormNotice(): ?string
    {
        $linked = (string)$this->getLinkedObjectName();
        if ($linked === '' || filter_var($linked, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return _t('BAZ_EXTERNAL_IDS_REMOVED', ['ids' => $linked]);
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getOptions()
    {
        return $this->options ?? [];
    }

    /** The stored **keys**, not the option labels (ticket 18 / ADR-0015). */
    public function searchableText($entry): string
    {
        return parent::searchableText($entry);
    }

    /**
     * @return array<int|string, mixed>|null
     */
    public function getOptionsTree()
    {
        return $this->optionsTree;
    }

    /**
     * @return array<int|string, mixed>
     */
    protected function getEntriesOptions()
    {
        if (is_null($this->options)) {
            $this->loadOptionsFromEntries();
        }

        return $this->options ?? [];
    }

    /**
     * @return string
     */
    public function getLinkedObjectName()
    {
        return $this->linkedObjectName;
    }

    /** check if the current class is EnumEntry. */
    public function isEnumEntryField(): bool
    {
        return false;
    }

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
