<?php

namespace YesWiki\Content\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Content\Attribute\Field;

#[Field(['lien_internet'])]
class LinkField extends BazarField
{
    use ContributesNoSearchableText;

    protected const FIELD_DISPLAYVIDEO = 3;
    protected const FIELD_OPTIONS = 6;
    protected const FIELD_CLASS = 7;

    /** @var mixed the CSS class the form definition gives this link */
    protected $class;
    /** @var bool */
    protected $displayVideo;
    /** All three come straight out of the form definition's option list, so they are whatever the webmaster typed; the getters below narrow them. */
    protected mixed $maxHeight;
    protected mixed $maxWidth;
    protected mixed $ratio;

    /**
     * @param array<int|string, mixed> $values
     */
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
