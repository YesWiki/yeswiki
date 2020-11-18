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
    protected $default;     // 5
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
    protected const FIELD_MAX_LENGTH = 4;
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
        $this->required = $values[self::FIELD_REQUIRED] == 1;
        $this->label = $values[self::FIELD_LABEL];
        $this->default = $values[self::FIELD_DEFAULT];
        $this->readAccess = $values[self::FIELD_READ_ACCESS];
        $this->writeAccess = $values[self::FIELD_WRITE_ACCESS];
        $this->helper = $values[self::FIELD_HELP];

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

        $isCreation = isset($entry['id_fiche']);

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
                'id' => $this->id,
                'recordId' => $this->recordId,
                'type' => $this->type,
                'required' => $this->required,
                'label' => $this->label,
                'default' => $this->default,
                'attributes' => $this->attributes,
                'values' => $this->values,
                'helper' => $this->helper
            ]
        ]);

        return $this->services->get(TemplateEngine::class)->render($templatePath, $data);
    }

    abstract public function renderField($record);

    abstract public function renderInput($record);
}
