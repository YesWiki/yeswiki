<?php

namespace YesWiki\Content\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Kernel\Service\StringUtilService;

abstract class CheckboxField extends EnumField
{
    /** @var mixed how many options before the "select all" control shows up; false to never show it */
    protected $displaySelectAllLimit;

    /** @var mixed how many options before the filter box shows up; false to never show it */
    protected $displayFilterLimit;

    /** @var string */
    protected $displayMethod;

    /** @var string|null */
    protected $formName;

    /** @var mixed one of the CHECKBOX_TWIG_LIST keys */
    protected $normalDisplayMode;

    /** @var string */
    protected $dragAndDropDisplayMode;

    /** @var string option criterion to sort on, empty to keep the natural order */
    protected $orderBy;

    /** @var string asc or desc */
    protected $orderDirection;

    /** @var int number of offered options, 0 for no limit */
    protected $maxOptions;

    protected const FIELD_DISPLAY_METHOD = 7;
    protected const FIELD_ORDER_BY = 16;
    protected const FIELD_ORDER_DIRECTION = 17;
    protected const FIELD_MAX_OPTIONS = 18;

    protected const ORDER_BY_LABEL = 'label';
    protected const ORDER_BY_ID = 'id';
    protected const ORDER_DIRECTION_ASC = 'asc';
    protected const ORDER_DIRECTION_DESC = 'desc';

    protected const CHECKBOX_DISPLAY_MODE_LIST = 'list';
    protected const CHECKBOX_DISPLAY_MODE_DIV = 'div';
    protected const CHECKBOX_TWIG_LIST = [
        self::CHECKBOX_DISPLAY_MODE_DIV => '@core/inputs/checkbox.twig',
        self::CHECKBOX_DISPLAY_MODE_LIST => '@core/inputs/checkbox_list.twig',
    ];

    protected const FROM_FORM_ID = '_fromForm';

    /**
     * @param array<int|string, mixed> $values
     */
    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);
        $this->displayMethod = (string)($values[self::FIELD_DISPLAY_METHOD] ?? '');
        $this->displaySelectAllLimit = false;
        $this->displayFilterLimit = false;
        $this->formName = (string)$this->name;
        $this->normalDisplayMode = self::CHECKBOX_DISPLAY_MODE_DIV;
        $this->dragAndDropDisplayMode = '';
        $this->orderBy = trim((string)($values[self::FIELD_ORDER_BY] ?? ''));
        $this->orderDirection = strtolower(trim((string)($values[self::FIELD_ORDER_DIRECTION] ?? ''))) === self::ORDER_DIRECTION_DESC
            ? self::ORDER_DIRECTION_DESC
            : self::ORDER_DIRECTION_ASC;
        $this->maxOptions = max(0, (int)($values[self::FIELD_MAX_OPTIONS] ?? 0));
    }

    public function getValueStructure()
    {
        return [$this->propertyName => ['_mode_' => 'multiple', '_type_' => 'string']];
    }

    protected function renderInput($entry)
    {
        $options = $this->getInputOptions($entry);

        switch ($this->displayMethod) {
            case 'tags':
                $htmlReturn = $this->render('@core/inputs/checkbox_tags.twig', [
                    'tagsData' => $this->generateTagsData($entry, $options),
                ]);

                return $htmlReturn;
            case 'dragndrop':
                return $this->render($this->dragAndDropDisplayMode, [
                    'options' => $options,
                    'optionsDetails' => $this->getOptionsDetails(array_keys($options)),
                    'selectedOptionsId' => $this->getValues($entry),
                    'formName' => $this->formName ?? $this->getFormName(),
                    'name' => _t('BAZ_DRAG_n_DROP_CHECKBOX_LIST'),
                    'height' => empty($this->getService(\YesWiki\Kernel\Service\RuntimeConfig::class)['BAZ_CHECKBOX_DRAG_AND_DROP_MAX_HEIGHT']) ? null : $this->getService(\YesWiki\Kernel\Service\RuntimeConfig::class)['BAZ_CHECKBOX_DRAG_AND_DROP_MAX_HEIGHT'],
                    'oldValue' => $this->sanitizeValues($this->getValue($entry), 'string'),
                ]);
            default:
                if ($this->optionsTree) {
                    return $this->render('@core/inputs/checkbox-tree.twig', [
                        'data' => $this->optionsTree,
                        'values' => $this->getValues($entry),
                        'displaySelectAllLimit' => $this->displaySelectAllLimit,
                        'oldValue' => $this->sanitizeValues($this->getValue($entry), 'string'),
                    ]);
                }

                if ($this->displayFilterLimit) {
                    $this->getService(\YesWiki\Kernel\Service\AssetRegistry::class)->addJsFile('javascripts/inputs/filter-entries.js');
                }

                return $this->render(self::CHECKBOX_TWIG_LIST[$this->normalDisplayMode] ?? self::CHECKBOX_TWIG_LIST[self::CHECKBOX_DISPLAY_MODE_DIV], [
                    'options' => $options,
                    'values' => $this->getValues($entry),
                    'displaySelectAllLimit' => $this->displaySelectAllLimit,
                    'displayFilterLimit' => $this->displayFilterLimit,
                    'oldValue' => $this->sanitizeValues($this->getValue($entry), 'string'),
                ]);
        }
    }

    /**
     * @param array<string, mixed>|null $entry
     *
     * @return list<int|string> the checked option keys
     */
    public function getValues($entry)
    {
        $value = $this->getValue($entry);

        return $this->sanitizeValues($value, 'array');
    }

    /**
     * The options the input offers: sorted, limited, and always holding the recorded values.
     *
     * @param array<string, mixed>|null $entry
     *
     * @return array<int|string, mixed>
     */
    protected function getInputOptions($entry): array
    {
        $options = $this->orderOptions($this->getOptions());
        $selectedIds = $this->getValues($entry);

        if ($this->maxOptions > 0 && count($options) > $this->maxOptions) {
            $limited = array_slice($options, 0, $this->maxOptions, true);
            foreach ($selectedIds as $selectedId) {
                if (!array_key_exists($selectedId, $limited) && array_key_exists($selectedId, $options)) {
                    $limited[$selectedId] = $options[$selectedId];
                }
            }
            $options = $limited;
        }

        foreach ($selectedIds as $selectedId) {
            if (!array_key_exists($selectedId, $options)) {
                $options[$selectedId] = $this->labelForMissingOption($selectedId);
            }
        }

        return $options;
    }

    /** The label of a recorded value the options no longer offer. */
    protected function labelForMissingOption(int|string $optionId): string
    {
        return (string)$optionId;
    }

    /**
     * @param array<int|string, mixed> $options
     *
     * @return array<int|string, mixed>
     */
    protected function orderOptions(array $options): array
    {
        switch ($this->orderBy) {
            case self::ORDER_BY_ID:
                uksort($options, fn ($first, $second) => $this->compareForOrder($first, $second));

                return $options;
            case self::ORDER_BY_LABEL:
                uasort($options, fn ($first, $second) => $this->compareForOrder($first, $second));

                return $options;
            default:
                return $this->orderDirection === self::ORDER_DIRECTION_DESC
                    ? array_reverse($options, true)
                    : $options;
        }
    }

    /** Natural, accent-blind comparison of two sort criteria, in the configured direction. */
    protected function compareForOrder(mixed $first, mixed $second): int
    {
        $comparison = strnatcasecmp($this->sortableValue($first), $this->sortableValue($second));

        return $this->orderDirection === self::ORDER_DIRECTION_DESC ? -$comparison : $comparison;
    }

    protected function sortableValue(mixed $value): string
    {
        $text = is_scalar($value) || $value === null ? (string)$value : (string)json_encode($value);

        return StringUtilService::withoutDiacritics($text);
    }

    /**
     * What the drag and drop input shows beside each option.
     *
     * @param list<int|string> $optionsIds
     *
     * @return array<int|string, array{image: ?string, description: ?string}>
     */
    protected function getOptionsDetails(array $optionsIds): array
    {
        return [];
    }

    public function formatValuesBeforeSave($entry)
    {
        $fromFormKey = $this->propertyName . self::FROM_FORM_ID;
        if (isset($_REQUEST[$fromFormKey])) {
            $checkboxField = $_REQUEST[$this->propertyName] ?? [];
        } else {
            $checkboxField = $this->getValue($entry);
        }

        $sanitized = $checkboxField === null ? '' : $this->sanitizeValues($checkboxField, 'string');
        $fieldsToRemove = [$fromFormKey];
        if (empty($sanitized)) {
            $fieldsToRemove[] = $this->propertyName;

            return ['fields-to-remove' => $fieldsToRemove];
        }

        return [
            $this->propertyName => $sanitized,
            'fields-to-remove' => $fieldsToRemove,
        ];
    }

    /**
     * @param mixed            $rawValue the stored or submitted value for this field
     * @param 'string'|'array' $format
     *
     * @return ($format is 'string' ? string : list<int|string>)
     */
    private function sanitizeValues($rawValue, string $format = 'string')
    {
        if (is_array($rawValue)) {
            $rawValue = array_filter($rawValue, function ($value) {
                return in_array($value, [1, '1', true, 'true']);
            });
            $rawValue = array_keys($rawValue);
            if ($format == 'string') {
                $rawValue = implode(',', $rawValue);
            }
        } else {
            try {
                $rawValue = strval($rawValue);
            } catch (\Throwable $th) {
                $rawValue = '';
            }
            if ($format != 'string') {
                $rawValue = empty(trim($rawValue)) ? [] : explode(',', $rawValue);
            }
        }

        return $rawValue;
    }

    /**
     * @param array<string, mixed>|null $entry
     * @param array<int|string, mixed>  $options
     *
     * @return array<string, mixed>
     */
    private function generateTagsData($entry, array $options)
    {
        $existingTags = [];
        foreach ($options as $key => $label) {
            $existingTags[$key] = [
                'id' => $key,
                'title' => $label,
            ];
        }

        $selectedOptions = $this->getValues($entry);
        $selectedOptions = empty($selectedOptions) ? [] : $selectedOptions;

        return [
            'existingTags' => $existingTags,
            'selectedOptions' => $selectedOptions,
        ];
    }

    public function getFromFormId(): string
    {
        return self::FROM_FORM_ID;
    }

    /**
     * @return string|null
     */
    protected function getFormName()
    {
        return $this->formName;
    }
}
