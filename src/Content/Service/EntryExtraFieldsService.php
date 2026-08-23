<?php

namespace YesWiki\Content\Service;

use Psr\Container\ContainerInterface;
use YesWiki\Content\Entity\ContributesEntryFields;
use YesWiki\Kernel\Service\TripleStore;

class EntryExtraFieldsService
{
    /** The names this service answers for a template or an API response. */
    public const EXTRA_FIELDS = ['comments', 'comments_count', 'reactions', 'reactions_count', 'triples', 'linked_data'];

    protected ContainerInterface $container;
    protected string $entryId = '';

    /**
     * @var iterable<ContributesEntryFields> tagged `yeswiki.entry_fields`
     */
    private iterable $contributors;

    /**
     * @param iterable<ContributesEntryFields> $contributors
     */
    public function __construct(ContainerInterface $container, iterable $contributors = [])
    {
        $this->container = $container;
        $this->contributors = $contributors;
    }

    public function setEntryId(string $entryId): void
    {
        $this->entryId = $entryId;
    }

    public function get(string $prop): mixed
    {
        $methodName = 'get' . $this->snakeToPascal($prop);
        if (method_exists($this, $methodName)) {
            return $this->$methodName();
        }

        foreach ($this->contributors as $contributor) {
            if (in_array($prop, $contributor->contributedFieldNames(), true)) {
                return $contributor->contributedField($prop, $this->entryId);
            }
        }

        return null;
    }

    private function snakeToPascal(string $string): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $string)));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getTriples(): array
    {
        return $this->container->get(TripleStore::class)->getMatching($this->entryId, null, null, '=');
    }

    /**
     * @return array<string, array<string, array<string, mixed>|null>>
     */
    public function getLinkedData(): array
    {
        $fields = [];
        $entryManager = $this->container->get(EntryManager::class);
        $formManager = $this->container->get(FormManager::class);

        $entry = $entryManager->getOne($this->entryId);
        $entryFields = $formManager->findTypeOfFields($entry['form_id'], ['SelectEntryField', 'CheckboxEntryField']);
        foreach ($entryFields as $field) {
            $prop = $field->getPropertyName();
            $fields[$prop] = [];
            if (!empty($entry[$prop])) {
                $entries = explode(',', $entry[$prop]);
                if (count($entries) === 1) {
                    $val = array_pop($entries);
                    $fields[$prop][$val] = $entryManager->getOne($entry[$prop]);
                } else {
                    foreach ($entries as $val) {
                        $fields[$prop][$val] = $entryManager->getOne($val);
                    }
                }
            }
        }

        return $fields;
    }

    /**
     * @param array<string, array<string, array<string, mixed>|null>> $linkedData
     */
    public function appendHtmlData(array $linkedData): string
    {
        $sep = '_-_';
        $htmlData = '';
        foreach ($linkedData as $fieldName => $entries) {
            foreach ($entries as $entry) {
                if ($entry === null) {
                    continue;
                }
                $htmlData .= str_replace(
                    'data-',
                    'data-' . $fieldName . $sep . $entry['tag'] . $sep,
                    $entry['html_data']
                ) . ' ';
            }
        }

        return $htmlData;
    }
}
