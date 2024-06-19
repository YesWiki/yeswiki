<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Core\Service\AclService;
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

    public function __construct(array $values, ContainerInterface $services)
    {
        $this->services = $services;

        $this->type = $values[self::FIELD_TYPE];
        $this->name = $values[self::FIELD_NAME];
        $this->label = empty($values[self::FIELD_LABEL]) ? '' : html_entity_decode($values[self::FIELD_LABEL]);
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
        if (!$this->canEdit($entry, !$entry)) {
            return '';
        }

        return $this->renderInput($entry);
    }

    public function formatValuesBeforeSaveIfEditable($entry, bool $isCreation = false)
    {
        // this method is defined to check $this->canEdit with $isCreation
        // without changing signature of formatValuesBeforeSave()
        return $this->formatValuesBeforeSave($entry);
    }

    // Format input values before save
    public function formatValuesBeforeSave($entry)
    {
        // to prevent creation of empty keys
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
        $isCreation = !$entry;

        return empty($readAcl) || $this->getService(AclService::class)->check($readAcl, $userNameForRendering, true, $isCreation ? '' : $entry['id_fiche']);
    }

    /* Return true if we are if editing is allowed for the field */
    public function canEdit($entry, bool $isCreation = false)
    {
        $writeAcl = empty($this->writeAccess) ? '' : $this->writeAccess;

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
}
