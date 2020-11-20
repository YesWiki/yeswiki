<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Core\Service\TemplateEngine;

abstract class BazarField
{
    protected $services;

    protected $type;         // 0
    protected $name;         // 1
    protected $label;        // 2
    protected $size;         // 3
    protected $maxChars;     // 4
    protected $default;      // 5
    protected $required;     // 8
    protected $searchable;   // 9
    protected $hint;         // 10
    protected $readAccess;   // 11
    protected $writeAccess;  // 12
    protected $semanticPredicate; // 14

    // How the field is identified in the Bazar entry
    protected $entryId;

    // Default values
    protected const FIELD_TYPE = 0;
    protected const FIELD_NAME = 1;
    protected const FIELD_LABEL = 2;
    protected const FIELD_SIZE = 3;
    protected const FIELD_MAX_CHARS = 4;
    protected const FIELD_DEFAULT = 5;
    protected const FIELD_REQUIRED = 8;
    protected const FIELD_SEARCHABLE = 9;
    protected const FIELD_HINT = 10;
    protected const FIELD_READ_ACCESS = 11;
    protected const FIELD_WRITE_ACCESS = 12;
    protected const FIELD_SEMANTIC_PREDICATE = 14;

    public function __construct(array $values, ContainerInterface $services)
    {
        $this->services = $services;

        $this->type = $values[self::FIELD_TYPE];
        $this->name = $values[self::FIELD_NAME];
        $this->label = $values[self::FIELD_LABEL];
        $this->size = $values[self::FIELD_SIZE];
        $this->maxChars = $values[self::FIELD_MAX_CHARS];
        $this->default = $values[self::FIELD_DEFAULT];
        $this->required = $values[self::FIELD_REQUIRED] == 1;
        $this->searchable = $values[self::FIELD_SEARCHABLE];
        $this->hint = $values[self::FIELD_HINT];
        $this->readAccess = $values[self::FIELD_READ_ACCESS];
        $this->writeAccess = $values[self::FIELD_WRITE_ACCESS];
        $this->semanticPredicate = $values[self::FIELD_SEMANTIC_PREDICATE];

        // By default, the entry ID is the field name
        $this->entryId = $values[self::FIELD_NAME];
    }

    /*
     * Return true if we are in edit mode and editing is not allowed
     */
    public function isInputHidden($entry)
    {
        $writeAcl = empty($this->writeAccess) ? '' : $this->writeAccess;

        $isCreation = $entry === '' || isset($entry['id_fiche']);

        return !empty($writeAcl) && !$GLOBALS['wiki']->CheckACL($writeAcl, null, true, $isCreation ? '' : $entry['id_fiche'], $isCreation ? 'creation' : '')  ;
    }

    public function render($templatePath, $data = [])
    {
        $data = array_merge($data, [
            'field' => [
                'type' => $this->type,
                'name' => $this->name,
                'label' => $this->label,
                'size' => $this->size,
                'maxChars' => $this->maxChars,
                'default' => $this->default,
                'pattern' => $this->pattern,
                'required' => $this->required,
                'hint' => $this->hint,
                'readAccess' => $this->readAccess,
                'writeAccess' => $this->writeAccess,
                'semanticPredicate' => $this->semanticPredicate,
            ],
            'entryId' => $this->entryId
        ]);

        return $this->services->get(TemplateEngine::class)->render($templatePath, $data);
    }

    public function formatInput($entry)
    {
        return array_key_exists($this->entryId, $entry) ?
            [$this->entryId => $entry[$this->entryId]] : [$this->entryId => null];
    }

    public function getService($class)
    {
        return $this->services->get($class);
    }

    public function getType()
    {
        return $this->type;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getLabel()
    {
        return $this->label;
    }

    public function getSize()
    {
        return $this->size;
    }

    public function getMaxChars()
    {
        return $this->maxChars;
    }

    public function getDefault()
    {
        return $this->default;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function getSearchable()
    {
        return $this->searchable;
    }

    public function getHint()
    {
        return $this->hint;
    }

    public function getReadAccess()
    {
        return $this->readAccess;
    }

    public function getWriteAccess()
    {
        return $this->writeAccess;
    }

    public function getSemanticPredicate()
    {
        return $this->semanticPredicate;
    }

    public function getEntryId()
    {
        return $this->entryId;
    }

    abstract public function renderField($entry);

    abstract public function renderInput($entry);
}
