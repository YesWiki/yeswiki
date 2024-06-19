<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;

/**
 * @Field({"lien_internet"})
 */
class LinkField extends BazarField
{
    protected const FIELD_DISPLAYVIDEO = 3;
    protected const FIELD_OPTIONS = 6;
    protected const FIELD_CLASS = 7;

    protected $class;
    protected $displayVideo;
    protected $maxHeight;
    protected $maxWidth;
    protected $ratio;

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->type = 'link';
        $this->maxChars = $this->maxChars ?? 255;
        $this->default = $this->default ?? 'https://';

        $this->size = '';
        $this->displayVideo = ($values[self::FIELD_DISPLAYVIDEO] ?? '') === 'displayvideo';
        $this->class = $values[self::FIELD_CLASS] ?? '';
        $options = (!empty($values[self::FIELD_OPTIONS]) && is_string($values[self::FIELD_OPTIONS]))
            ? explode('|', $values[self::FIELD_OPTIONS])
            : [];
        $this->maxHeight = $options[2] ?? '';
        $this->maxWidth = $options[1] ?? '';
        $this->ratio = $options[0] ?? '';
    }

    public function formatValuesBeforeSave($entry)
    {
        $value = $this->getValue($entry);

        return [$this->propertyName => $value !== 'https://' ? $value : null];
    }

    public function getDisplayVideo(): bool
    {
        return $this->displayVideo;
    }

    public function getRatio(): string
    {
        return is_scalar($this->ratio) ? strval($this->ratio) : '';
    }

    public function getMaxWidth(): int
    {
        return (is_numeric($this->maxWidth) && intval($this->maxWidth) > 0) ? intval($this->maxWidth) : 0;
    }

    public function getMaxHeight(): int
    {
        return (is_numeric($this->maxHeight) && intval($this->maxHeight) > 0) ? intval($this->maxHeight) : 0;
    }

    public function getClass(): string
    {
        return is_scalar($this->class) ? strval($this->class) : '';
    }

    // change return of this method to keep compatible with php 7.3 (mixed is not managed)
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return array_merge(
            parent::jsonSerialize(),
            [
                'displayVideo' => $this->getDisplayVideo(),
                'ratio' => $this->getRatio(),
                'maxWidth' => $this->getMaxWidth(),
                'maxHeight' => $this->getMaxHeight(),
                'class' => $this->getClass(),
            ]
        );
    }
}
