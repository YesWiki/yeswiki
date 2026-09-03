<?php

namespace YesWiki\Content\Field;

use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Service\HtmlPurifierService;
use YesWiki\Render\Service\TemplateEngine;

abstract class BazarField implements \JsonSerializable
{
    /** @var ContainerInterface */
    protected $services;

    /** @var string */
    protected $type;
    /** @var string|null */
    protected $name;
    /** @var string|null */
    protected $label;
    /** @var int|string|null */
    protected $size;
    /** @var int|string|null */
    protected $maxChars;
    /** @var mixed the value stored when the Content says nothing about this field */
    protected $default;
    /** @var bool */
    protected $required;
    /** @var string|null */
    protected $searchable;
    /** @var string|null */
    protected $hint;
    /** @var string */
    protected $readAccess;
    /** @var string */
    protected $writeAccess;

    /** @var string|null the entry key this field reads and writes, null for fields that store nothing */
    protected $propertyName;

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

    /**
     * @param array<int|string, mixed> $values one field's line of the form definition, by positional index
     */
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

        $this->propertyName = $values[self::FIELD_NAME];
    }

    /**
     * Get the structure of the value.
     *
     * @return mixed the structure
     */
    public function getValueStructure()
    {
        return [$this->propertyName => ['_mode_' => 'single', '_type_' => 'string']];
    }

    /**
     * Whether formatValuesBeforeSave() needs the Content's tag to already exist -- an upload names its file after it.
     *
     * @return bool
     */
    public function requiresTagBeforeFormatting()
    {
        return false;
    }

    /**
     * Render the edit view of the field.
     *
     * @param array<string, mixed>|null $entry
     * @param string|null               $userNameForRendering username to render the field, if empty uses connected user
     *
     * @return string $html
     */
    public function renderStaticIfPermitted($entry, ?string $userNameForRendering = null): string
    {
        if (!$this->canRead($entry, $userNameForRendering)) {
            return '';
        }

        if ($this->hasNoStoredValue($entry)) {
            return '';
        }

        ob_start();
        try {
            $rendered = (string)$this->renderStatic($entry);
        } finally {
            $printed = (string)ob_get_clean();
        }

        return $printed . $rendered;
    }

    /**
     * Whether $entry carries nothing for this field -- as opposed to carrying an empty value, which is a webmaster's answer and renders like any other.
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

    /**
     * @param array<string, mixed>|null $entry
     */
    public function renderInputIfPermitted($entry): string
    {
        if (!$this->canEdit($entry)) {
            return '';
        }

        return (string)$this->renderInput($entry);
    }

    /**
     * @param array<string, mixed>|null $entry
     *
     * @return array<string, mixed>
     */
    public function formatValuesBeforeSaveIfEditable($entry): array
    {
        if (empty($this->propertyName)) {
            return [];
        }

        if ($this->canEdit($entry)) {
            return $this->formatValuesBeforeSave($entry);
        }

        return [$this->propertyName => $this->getValue($entry) ?? $this->default];
    }

    /**
     * What this field contributes to the entry being written.
     *
     * @param array<string, mixed>|null $entry
     *
     * @return array<string, mixed> the keys to store, or `['fields-to-remove' => [...]]` to drop some
     */
    public function formatValuesBeforeSave($entry)
    {
        return empty($this->propertyName) ? [] : [$this->propertyName => $this->getValue($entry)];
    }

    /**
     * @param array<string, mixed>|null $entry
     *
     * @return string|null
     */
    protected function renderStatic($entry)
    {
        $value = $this->getValue($entry);

        return ($value) ? $this->render("@core/fields/{$this->type}.twig", [
            'value' => $this->getValue($entry),
        ]) : '';
    }

    /**
     * @param array<string, mixed>|null $entry
     *
     * @return string|null
     */
    protected function renderInput($entry)
    {
        return $this->render("@core/inputs/{$this->type}.twig", [
            'value' => $this->getValue($entry),
        ]);
    }

    /**
     * @param class-string $class
     *
     * @return mixed the service, as the container has no generic contract to narrow it
     */
    protected function getService($class)
    {
        return $this->services->get($class);
    }

    /**
     * @param array<string, mixed>|null $entry
     *
     * @return mixed the stored value, the value being submitted, or this field's default
     */
    protected function getValue($entry)
    {
        return $entry[$this->propertyName] ?? $_REQUEST[$this->propertyName] ?? $this->default;
    }

    public function isEmpty(mixed $pValue): bool
    {
        return is_null($pValue) || (is_array($pValue) && count(array_keys($pValue)) == 0) || (is_string($pValue) && trim($pValue) == '');
    }

    /**
     * What this field contributes to the search index (ticket 18 / ADR-0015).
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

    /** A stored value as one line of indexable text. */
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

    /**
     * Return true if we are if reading is allowed for the field.
     *
     * @param array<string, mixed>|null $entry
     * @param string|null               $userNameForRendering username to render the field, if empty uses connected user
     *
     * @return bool
     */
    public function canRead($entry, ?string $userNameForRendering = null)
    {
        $readAcl = empty($this->readAccess) ? '' : $this->readAccess;
        $isCreation = !isset($entry['tag']);

        return empty($readAcl) || $this->getService(AclService::class)->check($readAcl, $userNameForRendering, true, $isCreation ? '' : $entry['tag']);
    }

    /**
     * @param array<string, mixed>|null $entry
     */
    public function canEdit($entry): bool
    {
        $writeAcl = empty($this->writeAccess) ? '' : $this->writeAccess;

        $isCreation = !isset($entry['tag']);

        return empty($writeAcl) || $this->getService(AclService::class)->check($writeAcl, null, true, $isCreation ? '' : $entry['tag'], $isCreation ? 'creation' : 'edit');
    }

    /**
     * @param string               $templatePath
     * @param array<string, mixed> $data
     *
     * @return string
     */
    protected function render($templatePath, $data = [])
    {
        $data = array_merge([
            'field' => $this,
        ], $data);

        return $this->services->get(TemplateEngine::class)->render($templatePath, $data);
    }

    /**
     * @return string|null
     */
    public function getPropertyName()
    {
        return $this->propertyName;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return string|null
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string|null
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * @return int|string|null
     */
    public function getSize()
    {
        return $this->size;
    }

    /**
     * @return int|string|null
     */
    public function getMaxChars()
    {
        return $this->maxChars;
    }

    /**
     * @return mixed the value stored when the Content says nothing about this field
     */
    public function getDefault()
    {
        return $this->default;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    /**
     * @return string|null
     */
    public function getSearchable()
    {
        return $this->searchable;
    }

    /**
     * @return string|null
     */
    public function getHint()
    {
        return $this->hint;
    }

    /**
     * @return string
     */
    public function getReadAccess()
    {
        return $this->readAccess;
    }

    /**
     * @return string
     */
    public function getWriteAccess()
    {
        return $this->writeAccess;
    }

    /**
     * @return array<string, mixed>
     */
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

    /** return wiki from service but do not instanciate it at construct to prevent infinite loop. */
    protected function getRequest(): Request
    {
        return $this->services->get(\YesWiki\Kernel\Service\CurrentRequest::class)->get();
    }
}
