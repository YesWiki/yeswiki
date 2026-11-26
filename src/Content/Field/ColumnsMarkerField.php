<?php

namespace YesWiki\Content\Field;

use Psr\Container\ContainerInterface;

/** A layout marker of a columns block: it stores nothing and renders only its markup. */
abstract class ColumnsMarkerField extends LabelField
{
    /** @param array<int|string, mixed> $values */
    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);
        $this->formText = null;
        $this->viewText = null;
        $this->maxChars = null;
        $this->default = null;
        $this->width = 12;
        $this->newRow = false;
    }

    /** The markup this marker writes into the form and the entry view. */
    abstract public function markup(): string;

    protected function renderInput($entry)
    {
        return $this->markup();
    }

    protected function renderStatic($entry)
    {
        return $this->markup();
    }

    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return [
            'type' => $this->getType(),
        ];
    }

    public function translatableAttributes(): array
    {
        return [];
    }
}
