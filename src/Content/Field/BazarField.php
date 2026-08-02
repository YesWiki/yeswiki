<?php

namespace YesWiki\Content\Field;

use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Service\HtmlPurifierService;
use YesWiki\Render\Service\TemplateEngine;

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

    public function __construct(array $values, ContainerInterface $services)
    {
        $this->services = $services;

        $this->type = $values[self::FIELD_TYPE];
        $this->name = $values[self::FIELD_NAME];
        $this->label = empty($values[self::FIELD_LABEL]) ? '' : $this->services->get(HtmlPurifierService::class)->cleanHTML(html_entity_decode($values[self::FIELD_LABEL]));
        $this->size = $values[self::FIELD_SIZE];
        $this->maxChars = $values[self::FIELD_MAX_CHARS];
        $this->default = $values[self::FIELD_DEFAULT];
        $this->required = $values[self::FIELD_REQUIRED] == 1;
        $this->searchable = $values[self::FIELD_SEARCHABLE];
        $this->hint = $values[self::FIELD_HINT];
        $this->readAccess = str_replace(',', "\n", $values[self::FIELD_READ_ACCESS]);
        $this->writeAccess = str_replace(',', "\n", $values[self::FIELD_WRITE_ACCESS]);

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
     * @return mixed the structure
     */
    public function getValueStructure()
    {
        return [$this->propertyName => ['_mode_' => 'single', '_type_' => 'string']];
    }

    /*
    *	indicates if tag must be set before to format the value
    */

    /**
     * Whether formatValuesBeforeSave() needs the Content's tag to already exist -- an
     * upload names its file after it, a keyword index is keyed by it. Such fields run in a
     * second pass, after the tag has been generated.
     */
    public function requiresTagBeforeFormatting()
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

        // A field this Content never filled in shows nothing. getValue() substitutes the
        // field's *default* for a missing value, which is right for an input -- a new
        // entry starts pre-filled -- and wrong for a view, where it invents a value the
        // Content does not have: a checkbox defaulting to "Non" appeared on every page
        // that had never been asked the question.
        if ($this->hasNoStoredValue($entry)) {
            return '';
        }

        // Captured, not just returned. A field's value can contain `{{action}}` calls, and
        // several actions still print rather than return -- bazar's own among them. Left
        // uncaptured, that output reaches the page at the moment the field is *formatted*
        // instead of where the field sits, so a page whose content is `{{bazar}}` showed
        // bazar's navbar above its own title.
        ob_start();
        try {
            $rendered = (string)$this->renderStatic($entry);
        } finally {
            $printed = (string)ob_get_clean();
        }

        return $printed . $rendered;
    }

    /**
     * Whether $entry carries nothing for this field -- as opposed to carrying an empty
     * value, which is a webmaster's answer and renders like any other.
     *
     * Fields with no property name (a label, a tab, a section) store nothing by design and
     * are never skipped; nor is a field whose default is empty, since substituting it
     * changed nothing.
     *
     * @param array<string, mixed>|null $entry
     */
    protected function hasNoStoredValue($entry): bool
    {
        if (empty($this->propertyName) || !is_array($entry) || $this->isEmpty($this->default)) {
            return false;
        }

        return !array_key_exists($this->propertyName, $entry);
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
        }
        // We cannot : let's return the previous value or the default value

        return [$this->propertyName => $this->getValue($entry) ?? $this->default];

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

        return ($value) ? $this->render("@core/fields/{$this->type}.twig", [
            'value' => $this->getValue($entry),
        ]) : '';
    }

    // each field should implement this method instead of the renderInputIfPermitted
    // so we are sure same safety checks are done for all fields
    protected function renderInput($entry)
    {
        return $this->render("@core/inputs/{$this->type}.twig", [
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

    /**
     * What this field contributes to the search index (ticket 18 / ADR-0015).
     *
     * The index is **asked** rather than built by walking a body, because some stored
     * *values* are envelope as surely as the keys are: a `stored_filename` UUID, a
     * timestamp, a `form_id`. Indexing those reproduces the bug this replaced in a subtler
     * form -- search `2026`, match everything edited this year. So the default is "my value
     * as text", and the field types for which that is wrong say so by overriding, the same
     * way field roles default from type (ADR-0012) rather than from a list of names held
     * somewhere else.
     *
     * Deliberately reads the stored value directly instead of going through getValue():
     * that method substitutes `$_REQUEST` and then the field's *default*, which is right
     * for an input and wrong here twice over -- it would index a value the Content does not
     * have, and make what gets indexed depend on the request that happened to trigger it.
     *
     * @param array<string, mixed>|null $entry the Content, in entry shape
     */
    public function searchableText($entry): string
    {
        if (!is_array($entry) || $this->propertyName === '' || $this->propertyName === null) {
            return '';
        }

        return self::flattenForIndex($entry[$this->propertyName] ?? null);
    }

    /**
     * A stored value as one line of indexable text. Arrays are flattened rather than
     * dropped: a checkbox stores several keys, a geolocation several numbers, and losing
     * them silently is how a field stops being findable without anything saying so.
     */
    protected static function flattenForIndex(mixed $value): string
    {
        if (is_array($value)) {
            return trim(implode(' ', array_map([self::class, 'flattenForIndex'], $value)));
        }
        if (is_bool($value) || $value === null) {
            return '';
        }

        return trim((string)$value);
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
        $isCreation = !isset($entry) || !is_array($entry) || !isset($entry['tag']);

        return empty($readAcl) || $this->getService(AclService::class)->check($readAcl, $userNameForRendering, true, $isCreation ? '' : $entry['tag']);
    }

    /* Return true if editing is allowed for the field */
    public function canEdit($entry)
    {
        $writeAcl = empty($this->writeAccess) ? '' : $this->writeAccess;

        $isCreation = !$entry;
        $isCreation = !isset($entry) || !is_array($entry) || !isset($entry['tag']);

        return empty($writeAcl) || $this->getService(AclService::class)->check($writeAcl, null, true, $isCreation ? '' : $entry['tag'], $isCreation ? 'creation' : 'edit');
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
        ];
    }

    /**
     * return wiki from service but do not instanciate it at construct to prevent infinite loop.
     */
    protected function getRequest(): Request
    {
        return $this->services->get(\YesWiki\Kernel\Service\CurrentRequest::class)->get();
    }
}
