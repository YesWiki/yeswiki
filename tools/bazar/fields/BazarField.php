<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\HtmlPurifierService;
use YesWiki\Core\Service\TemplateEngine;
use YesWiki\Wiki;

abstract class BazarField implements \JsonSerializable
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
    protected $propertyName;

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
    private const FIELD_CLASS_TYPE = 'BazarField';

    public function __construct(array $values, ContainerInterface $services)
    {



        $this->services = $services;

        $this->type = $values[self::FIELD_TYPE];
        $this->name = $values[self::FIELD_NAME];
        if (is_string($values[self::FIELD_LABEL]))
        {
             $this->label = empty($values[self::FIELD_LABEL]) ? '' : $this->services->get(HtmlPurifierService::class)->cleanHTML(html_entity_decode($values[self::FIELD_LABEL]));
        }
        $this->size = $values[self::FIELD_SIZE];
        $this->maxChars = $values[self::FIELD_MAX_CHARS];
        $this->default = $values[self::FIELD_DEFAULT];
        $this->required = $values[self::FIELD_REQUIRED] == 1;
        $this->searchable = $values[self::FIELD_SEARCHABLE];
        $this->hint = $values[self::FIELD_HINT];
        $this->readAccess = str_replace(',', "\n", $values[self::FIELD_READ_ACCESS]);
        $this->writeAccess = str_replace(',', "\n", $values[self::FIELD_WRITE_ACCESS]);
        $this->semanticPredicate = $values[self::FIELD_SEMANTIC_PREDICATE];
        $this->semanticPredicate = strpos($this->semanticPredicate, ',')
                ? array_map('trim', explode(',', $this->semanticPredicate))
            : $this->semanticPredicate;

        // By default, the entry ID is the field name
        $this->propertyName = $values[self::FIELD_NAME];
    }

    /**
     * Get the structure of the value.
     *
     *	The structure indicates how to handle SQL filtering
     *
     *	It has the format [ _mode_ => "single"|"multiple", _type_ => "string"|"number"|"boolean" ]
     *
     * 	example :
     *		[ _mode_ => "single", _type_ => "string" ]
     *			-> bf_montext = "toto"
     *		[ _mode_ => "multiple", _type_ => "string"  ]
     *			-> bf_jours = "lundi,mardi"
     *
     *  the structure may contain subfields descrived in the same format
     *
     *	example :
     *		[ bf_latitude => [ _mode_ => "single", _type_ => "number"  ], bf_longitude => [ _mode_ => "single", _type_ => "number"  ] ]
     *	example :
     *		[ geolocation => [ bf_latitude => [ _mode_ => "single", _type_ => "number"  ], bf_longitude => [ _mode_ => "single", _type_ => "number"  ] ] ]
     *
     *	By default field's value are considered as single string
     *	[ bf_monfield => [ _mode_ => "single", _type_ => "string"  ]
     *
     * @return the structure
     */
    public function getValueStructure()
    {
        return [$this->propertyName => ['_mode_' => 'single', '_type_' => 'string']];
    }

    public static function mapToFieldArray($fieldProps): array
    {
        $new = [];
        $new[self::FIELD_TYPE] = $fieldProps['type'] ?? '';
        $new[self::FIELD_NAME] = $fieldProps['name'] ?? '';
        $new[self::FIELD_LABEL] = $fieldProps['label'] ?? '';
        $new[self::FIELD_SIZE] = $fieldProps['size'] ?? '';
        $new[self::FIELD_MAX_CHARS] = $fieldProps['maxChars'] ?? '';
        $new[self::FIELD_DEFAULT] = $fieldProps['default'] ?? '';
        $new[6] = '';
        $new[7] = '';
        if (isset($fieldProps['required']) and $fieldProps['required']) {
            $new[self::FIELD_REQUIRED] = 1;
        } else {
            $new[self::FIELD_REQUIRED] = '';
        };
        $new[self::FIELD_SEARCHABLE] = $fieldProps['searchable'] ?? '';
        $new[self::FIELD_HINT] = $fieldProps['helper'] ?? '';
        $new[self::FIELD_READ_ACCESS] = $fieldProps['read_acl'] ?? '';
        $new[self::FIELD_WRITE_ACCESS] = $fieldProps['write_acl'] ?? '';
        $new[13] = '';
        $new[self::FIELD_SEMANTIC_PREDICATE] = $fieldProps['sem_type'] ?? '';
        $new[15] = '';
        $new[16] = '';

        return $new;
    }

    /*
    *	indicates if id_fiche must be set before to format the value
    */

    public function requireIDFiche()
    {
        return false;
    }

    /**
     * Render the edit view of the field. Check ACLS first.
     *
     * @param array|null  $entry
     * @param string|null $userNameForRendering username to render the field, if empty uses connected user
     *
     * @return string|null $html
     */
    public function renderStaticIfPermitted($entry, ?string $userNameForRendering = null)
    {
        // Safety checks, must be run before every renderStatic
        if (!$this->canRead($entry, $userNameForRendering)) {
            return '';
        }

        return $this->renderStatic($entry);
    }

    // Render the edit view of the field. Check ACLS first
    public function renderInputIfPermitted($entry)
    {
        // Safety checks, must be run before every renderInput
        if (!$this->canEdit($entry)) {
            return '';
        }

        return $this->renderInput($entry);
    }

    public function formatValuesBeforeSaveIfEditable($entry)
    {
        // Let's prevent creation of empty keys

        if (empty($this->propertyName)) {
            return [];
        }

        // Let's check if we are authorized to set or modify the field

        if ($this->canEdit($entry)) {
            // We can : let's return the formatted given value

            return $this->formatValuesBeforeSave($entry);
        } else {
            // We cannot : let's return the previous value or the default value

            return [$this->propertyName => $this->getValue($entry) ?? $this->default];
        }

        // We cannot : let's return nothing

        return [];
    }

    // Format input values before save
    public function formatValuesBeforeSave($entry)
    {
        // By default, let's return the value
        // NOTE : The emptiness test is already done in formatValuesBeforeSaveIfEditable. Let's keep it for safety reason.

        return empty($this->propertyName) ? [] : [$this->propertyName => $this->getValue($entry)];
    }

    // Render the show view of the field
    protected function renderStatic($entry)
    {
        $value = $this->getValue($entry);

        return ($value) ? $this->render("@bazar/fields/{$this->type}.twig", [
            'value' => $this->getValue($entry),
        ]) : '';
    }

    // each field should implement this method instead of the renderInputIfPermitted
    // so we are sure same safety checks are done for all fields
    protected function renderInput($entry)
    {
        return $this->render("@bazar/inputs/{$this->type}.twig", [
            'value' => $this->getValue($entry),
        ]);
    }

    // SHORTCUTS

    protected function getService($class)
    {
        return $this->services->get($class);
    }

    protected function getValue($entry)
    {
        // TODO see if it is necessary to look for $_REQUEST
        return $entry[$this->propertyName] ?? $_REQUEST[$this->propertyName] ?? $this->default;
    }

    public function isEmpty($pValue)
    {
        return is_null($pValue) || (is_array($pValue) && count(array_keys($pValue)) == 0) || (is_string($pValue) && trim($pValue) == '');
    }

    // HELPERS
    /**
     * Return true if we are if reading is allowed for the field.
     *
     * @param array|null  $entry
     * @param string|null $userNameForRendering username to render the field, if empty uses connected user
     *
     * @return bool
     */
    public function canRead($entry, ?string $userNameForRendering = null)
    {
        $readAcl = empty($this->readAccess) ? '' : $this->readAccess;
        $isCreation = !isset($entry) || !is_array($entry) || !isset($entry['id_fiche']);

        return empty($readAcl) || $this->getService(AclService::class)->check($readAcl, $userNameForRendering, true, $isCreation ? '' : $entry['id_fiche']);
    }

    /* Return true if editing is allowed for the field */
    public function canEdit($entry)
    {
        $writeAcl = empty($this->writeAccess) ? '' : $this->writeAccess;

        $isCreation = !$entry;
        $isCreation = !isset($entry) || !is_array($entry) || !isset($entry['id_fiche']);

        return empty($writeAcl) || $this->getService(AclService::class)->check($writeAcl, null, true, $isCreation ? '' : $entry['id_fiche'], $isCreation ? 'creation' : 'edit');
    }

    protected function render($templatePath, $data = [])
    {
        $data = array_merge([
            'field' => $this,
        ], $data); // Data given as param takes predominance

        return $this->services->get(TemplateEngine::class)->render($templatePath, $data);
    }

    // GETTERS

    public function getPropertyName()
    {
        return $this->propertyName;
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

    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return [
            'id' => $this->getPropertyName(),
            'propertyname' => $this->getPropertyName(),
            'label' => $this->getLabel(),
            'name' => $this->getName(),
            'type' => $this->getType(),
            'default' => $this->getDefault(),
            'searchable' => $this->getSearchable(),
            'required' => $this->isRequired(),
            'helper' => $this->getHint(),
            'read_acl' => $this->getReadAccess(),
            'write_acl' => $this->getWriteAccess(),
            'sem_type' => $this->getSemanticPredicate(),
            'field_type' => self::FIELD_CLASS_TYPE,
            'size' => $this->getSize(),
        ];
    }

    /**
     * return wiki from service but do not instanciate it at construct to prevent infinite loop.
     *
     * @return Wiki $wiki
     */
    protected function getWiki(): Wiki
    {
        return $this->getService(Wiki::class);
    }

    protected function getRequest(): Request
    {
        return $this->getWiki()->request;
    }
}
