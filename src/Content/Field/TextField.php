<?php

namespace YesWiki\Content\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Content\Attribute\Field;
use YesWiki\Kernel\Service\HtmlPurifierService;
use YesWiki\Kernel\Service\NumberDisplay;

#[Field(['texte'])]
class TextField extends BazarField
{
    /** @var string|null */
    protected $pattern;
    /** @var string|null */
    protected $subType;
    /** @var string|null */
    protected $placeholder;

    protected ?int $decimals = null;

    protected const FIELD_PATTERN = 6;
    protected const FIELD_SUB_TYPE = 7;
    protected const FIELD_DECIMALS = 13;
    protected const FIELD_PLACEHOLDER = 15;

    protected const ALLOWED_SUB_TYPES = ['text', 'date', 'email', 'url', 'range', 'password', 'number', 'color'];

    /**
     * @param array<int|string, mixed> $values
     */
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

        $this->placeholder = $values[self::FIELD_PLACEHOLDER];
        $this->decimals = NumberDisplay::decimalsFrom($values[self::FIELD_DECIMALS] ?? null);
        $this->maxChars = $this->maxChars ?? 255;

        if ($this->type === 'range') {
            $this->size = empty($this->size) ? 0 : $this->size;
            $this->maxChars = empty($this->maxChars) ? 100 : $this->maxChars;
        }
    }

    /** Only a plain text input holds prose; a date, an email or a colour is the same in every language. */
    public function translatesValue(): bool
    {
        return $this->type === 'text';
    }

    public function getValueStructure()
    {
        if ($this->type == 'number' || $this->type == 'range') {
            return [$this->propertyName => ['_mode_' => 'single', '_type_' => 'number']];
        }

        return [$this->propertyName => ['_mode_' => 'single', '_type_' => 'string']];
    }

    protected function renderInput($entry)
    {
        return $this->render('@core/inputs/' . ($this->getType() == 'range' ? 'range' : 'text') . '.twig', [
            'value' => $this->getValue($entry),
        ]);
    }

    protected function renderStatic($entry)
    {
        $value = $this->getValue($entry);
        if ($value !== '0' && !$value) {
            return '';
        }

        if ($this->isTheFormsTitleField($entry)) {
            return $this->render('@core/fields/title.twig', [
                'value' => $value,
            ]);
        }

        if ($this->isNumeric()) {
            return $this->render('@core/fields/text.twig', [
                'value' => htmlspecialchars($this->getService(NumberDisplay::class)->format((string)$value, $this->decimals)),
            ]);
        }

        return $this->render('@core/fields/text.twig', [
            'value' => $value,
        ]);
    }

    /** Whether the field holds a number, which is written in the reader's language and lined up in tables. */
    public function isNumeric(): bool
    {
        return in_array($this->type, ['number', 'range'], true);
    }

    public function getDecimals(): ?int
    {
        return $this->decimals;
    }

    /** The input's `step`, so that a value with the decimals the field asks for is valid. */
    public function getStep(): string
    {
        return NumberDisplay::step($this->decimals);
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

    public function getPattern(): ?string
    {
        return $this->pattern;
    }

    public function getSubType(): ?string
    {
        return $this->subType;
    }

    public function getPlaceholder(): ?string
    {
        return $this->placeholder;
    }

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
                'placeholder' => $this->getPlaceholder(),
                'decimals' => $this->getDecimals(),
            ]
        );
    }

    /**
     * @param array<string, mixed>|null $entry
     */
    private function isTheFormsTitleField($entry): bool
    {
        $formId = $entry['form_id'] ?? null;
        if (empty($formId)) {
            return false;
        }
        $form = $this->getService(\YesWiki\Content\Service\FormManager::class)->getOne($formId);

        return $this->name === $this->getService(\YesWiki\Content\Service\FormPropertiesService::class)->titleFieldName($form);
    }
}
