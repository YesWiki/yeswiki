<?php

namespace YesWiki\Content\Service;

use Psr\Container\ContainerInterface;
use YesWiki\Content\Entity\ContributesEntryFields;
use YesWiki\Kernel\Service\TripleStore;

// Get more data about an entry
class EntryExtraFieldsService
{
    /**
     * The names this service answers for a template or an API response.
     *
     * `triples` and `linked_data` are answered here because they are Content's own. The rest
     * are answered by whichever module contributes them -- `comments` and `reactions` come
     * from `Social` (ADR-0019) -- and stay on this list because callers iterate it.
     */
    public const EXTRA_FIELDS = ['comments', 'comments_count', 'reactions', 'reactions_count', 'triples', 'linked_data'];

    protected ContainerInterface $container;
    protected $entryId;

    /** @var iterable<ContributesEntryFields> tagged `yeswiki.entry_fields` */
    private iterable $contributors;

    /**
     * @param iterable<ContributesEntryFields> $contributors
     */
    public function __construct(ContainerInterface $container, iterable $contributors = [])
    {
        $this->container = $container;
        $this->contributors = $contributors;
    }

    public function setEntryId($entryId)
    {
        $this->entryId = $entryId;
    }

    // get('comments'), get('nb_comments')
    public function get($prop)
    {
        $methodName = 'get' . $this->snakeToPascal($prop);
        if (method_exists($this, $methodName)) {
            return $this->$methodName();
        }

        // ...and whatever another module says it can answer. Asked after this service's own
        // methods so Content keeps the last word on the names it owns.
        foreach ($this->contributors as $contributor) {
            if (in_array($prop, $contributor->contributedFieldNames(), true)) {
                return $contributor->contributedField($prop, (string)$this->entryId);
            }
        }

        return null;
    }

    private function snakeToPascal(string $string): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $string)));
    }

    public function getTriples()
    {
        return $this->container->get(TripleStore::class)->getMatching($this->entryId, null, null, '=');
    }

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

    public function appendHtmlData(array $linkedData): string
    {
        $sep = '_-_';
        $htmlData = '';
        foreach ($linkedData as $fieldName => $entries) {
            foreach ($entries as $entry) {
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
