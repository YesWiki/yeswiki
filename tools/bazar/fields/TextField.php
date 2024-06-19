<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Core\Service\HtmlPurifierService;

/**
 * @Field({"texte"})
 */
class TextField extends BazarField
{
    protected $pattern;
    protected $subType;

    protected const FIELD_PATTERN = 6;
    protected const FIELD_SUB_TYPE = 7;

    protected const ALLOWED_SUB_TYPES = ['text', 'date', 'email', 'url', 'range', 'password', 'number', 'color'];

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->pattern = $values[self::FIELD_PATTERN];
        $this->subType = $values[self::FIELD_SUB_TYPE];

        if (!empty($this->subType) && in_array($this->subType, self::ALLOWED_SUB_TYPES)) {
            $this->type = $this->subType;
        } else {
            $this->type = 'text';
        }

        $this->maxChars = $this->maxChars ?? 255;

        if ($this->type === 'range') {
            $this->size = empty($this->size) ? 0 : $this->size;
            $this->maxChars = empty($this->maxChars) ? 100 : $this->maxChars;
        }
    }

    protected function renderInput($entry)
    {
        // Handling all subtypes (url, number) in the text.twig
        return $this->render('@bazar/inputs/' . ($this->getType() == 'range' ? 'range' : 'text') . '.twig', [
            'value' => $this->getValue($entry),
        ]);
    }

    protected function renderStatic($entry)
    {
        $value = $this->getValue($entry);
        if ($value !== '0' && !$value) {
            return '';
        }

        if ($this->name === 'bf_titre') {
            return $this->render('@bazar/fields/title.twig', [
                'value' => $value,
            ]);
        } else {
            return $this->render('@bazar/fields/text.twig', [
                'value' => $value,
            ]);
        }
    }

    public function formatValuesBeforeSave($entry)
    {
        if (empty($this->propertyName)) {
            return [];
        }
        $dirtyHtml = $this->getValue($entry);
        $cleanHTML = $this->getService(HtmlPurifierService::class)->cleanHTML($dirtyHtml);

        return [$this->propertyName => $cleanHTML];
    }

    public function getPattern()
    {
        return $this->pattern;
    }

    public function getSubType()
    {
        return $this->subType;
    }

    // change return of this method to keep compatible with php 7.3 (mixed is not managed)
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return array_merge(
            parent::jsonSerialize(),
            [
                'maxChars' => $this->getMaxChars(),
                'size' => $this->getSize(),
                'subType' => $this->getSubType(),
                'pattern' => $this->getPattern(),
            ]
        );
    }
}
