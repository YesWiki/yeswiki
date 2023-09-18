<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Bazar\Field\LinkField;

/**
 * @Field({"video"})
 */
class VideoField extends LinkField
{
    protected const FIELD_RATIO = 3;
    protected const FIELD_MAXWIDTH = 4;
    protected const FIELD_MAXHEIGHT = 6;
    protected const FIELD_CLASS = 7;
    protected $ratio;
    protected $maxWidth;
    protected $maxHeight;
    protected $class;

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);
        $this->type = 'video';
        $this->size = '';
        $this->ratio = $values[self::FIELD_RATIO];
        $this->maxChars = '';
        $this->maxWidth = $values[self::FIELD_MAXWIDTH];
        $this->maxHeight = $values[self::FIELD_MAXHEIGHT];
        $this->class = $values[self::FIELD_CLASS];
    }

    protected function renderInput($entry)
    {
        return $this->render("@bazar/inputs/link.twig", [
            'value' => $this->getValue($entry)
        ]);
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
                'ratio' => $this->getRatio(),
                'maxWidth' => $this->getMaxWidth(),
                'maxHeight' => $this->getMaxHeight(),
                'class' => $this->getClass()
            ]
        );
    }
}
