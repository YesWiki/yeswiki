<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;

abstract class BazarField
{
    protected $services;

    protected $id;
    protected $recordId; // How the field is identified in the Bazar record
    protected $type;
    protected $required;
    protected $label;
    protected $default;
    protected $attributes;
    protected $values;
    protected $helper;

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

        // champs obligatoire
        if ($values[self::FIELD_REQUIRED]==1) {
            $this->required = true;
        } else {
            $this->required = false;
        }

        $this->id = $values[self::FIELD_ID];

        // texte d'invitation à la saisie
        $this->label = $values[self::FIELD_LABEL];

        $this->default = $values[self::FIELD_DEFAULT];

        // attributs html du champs
        $this->attributes = '';

        // valeurs associées
        $this->values = '';

        // texte d'aide
        $this->helper = $values[self::FIELD_HELP];
    }

    public function getService($class)
    {
        return $this->services->get($class);
    }

    abstract public function showInput($record);

    abstract public function getHtml($record);
}
