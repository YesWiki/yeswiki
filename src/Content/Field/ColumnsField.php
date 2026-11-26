<?php

namespace YesWiki\Content\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Content\Attribute\Field;

/** Opens a columns block, closed by `columnsend`, its columns separated by `columnchange`. */
#[Field(['columns'])]
class ColumnsField extends ColumnsMarkerField
{
    public const ALLOWED_LAYOUTS = ['6-6', '4-8', '8-4', '4-4-4', '3-9', '9-3', '3-3-3-3'];
    protected const FIELD_LAYOUT = 1;

    private string $layout;

    /** @param array<int|string, mixed> $values */
    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);
        $rawLayout = trim((string)($values[self::FIELD_LAYOUT] ?? ''));
        $this->layout = in_array($rawLayout, self::ALLOWED_LAYOUTS, true) ? $rawLayout : '6-6';
    }

    public function getLayout(): string
    {
        return $this->layout;
    }

    public function markup(): string
    {
        return ColumnsBalancer::open($this->layout);
    }

    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return [
            'type' => $this->getType(),
            'layout' => $this->layout,
        ];
    }
}
