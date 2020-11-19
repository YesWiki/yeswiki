<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Core\Service\TemplateEngine;

abstract class BazarField
{
    protected $services;

    protected $type;        // 0
    protected $id;          // 1
    protected $label;       // 2
    protected $size;        // 3
    protected $minChars;    // 3
    protected $maxChars;    // 4
    protected $default;     // 5
    protected $pattern;     // 6
    protected $required;    // 8
    protected $helper;      // 10
    protected $readAccess;  // 11
    protected $writeAccess; // 12

    protected $recordId;    // How the field is identified in the Bazar record
    protected $attributes;
    protected $values;

    protected const FIELD_TYPE = 0;
    protected const FIELD_ID = 1;
    protected const FIELD_LABEL = 2;
    protected const FIELD_SIZE = 3;
    protected const FIELD_MIN_CHARS = 3;
    protected const FIELD_MAX_CHARS = 4;
    protected const FIELD_DEFAULT = 5;
    protected const FIELD_PATTERN = 6;
    protected const FIELD_SUB_TYPE = 7;
    protected const FIELD_REQUIRED = 8;
    protected const FIELD_SEARCHABLE = 9;
    protected const FIELD_HELP = 10;
    protected const FIELD_READ_ACCESS = 11;
    protected const FIELD_WRITE_ACCESS = 12;
    protected const FIELD_KEYWORDS = 13;
    protected const FIELD_SEMANTIC = 14;
    protected const FIELD_QUERIES = 15;

    public function __construct(array $values, ContainerInterface $services)
    {
        $this->services = $services;

        $this->id = $values[self::FIELD_ID];
        $this->label = $values[self::FIELD_LABEL];
        $this->size = $values[self::FIELD_SIZE];
        $this->minChars = $values[self::FIELD_MIN_CHARS];
        $this->maxChars = $values[self::FIELD_MAX_CHARS];
        $this->default = $values[self::FIELD_DEFAULT];
        $this->pattern = $values[self::FIELD_PATTERN];
        $this->required = $values[self::FIELD_REQUIRED] == 1;
        $this->helper = $values[self::FIELD_HELP];
        $this->readAccess = $values[self::FIELD_READ_ACCESS];
        $this->writeAccess = $values[self::FIELD_WRITE_ACCESS];

        // By default, the ID is the record ID
        $this->recordId = $values[self::FIELD_ID];

        // TODO see if this need to be defined here
        $this->attributes = '';
        $this->values = '';
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

    public function getService($class)
    {
        return $this->services->get($class);
    }

    public function render($templatePath, $data = [])
    {
        $data = array_merge($data, [
            'field' => [
                'type' => $this->type,
                'id' => $this->id,
                'label' => $this->label,
                'size' => $this->size,
                'minChars' => $this->minChars,
                'maxChars' => $this->maxChars,
                'default' => $this->default,
                'pattern' => $this->pattern,
                'required' => $this->required,
                'helper' => $this->helper,
                'readAccess' => $this->readAccess,
                'writeAccess' => $this->writeAccess,
                // Other data
                'attributes' => $this->attributes,
                'values' => $this->values,
                'recordId' => $this->recordId,
            ]
        ]);

        return $this->services->get(TemplateEngine::class)->render($templatePath, $data);
    }

    public function formatInput($entry)
    {
        return array_key_exists($this->recordId, $entry) ?
            [$this->recordId => $entry[$this->recordId]] : [$this->recordId => null];
    }

    public function getId()
    {
        return $this->id;
    }

    public function getRecordId()
    {
        return $this->recordId;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getValues()
    {
        return $this->values;
    }

    abstract public function renderField($entry);

    abstract public function renderInput($entry);
}
