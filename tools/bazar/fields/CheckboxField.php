<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;

abstract class CheckboxField extends EnumField
{
    protected $displaySelectAllLimit; // number of items without selectall box ; false if no limit
    protected $displayFilterLimit; // number of items without filter ; false if no limit
    protected $displayMethod; // empty, tags or dragndrop
    protected $formName; // form name for drag and drop
    protected $normalDisplayMode;
    protected $dragAndDropDisplayMode;
    protected $orderBy; // option criterion to sort on ; empty keeps the natural order
    protected $orderDirection; // asc or desc
    protected $maxOptions; // number of proposed options ; 0 if no limit

    protected const FIELD_DISPLAY_METHOD = 7;
    protected const FIELD_ORDER_BY = 16;
    protected const FIELD_ORDER_DIRECTION = 17;
    protected const FIELD_MAX_OPTIONS = 18;

    protected const ORDER_BY_LABEL = 'label';
    protected const ORDER_BY_ID = 'id';
    protected const ORDER_DIRECTION_DESC = 'desc';
    protected const ORDER_DIRECTION_ASC = 'asc';

    protected const CHECKBOX_DISPLAY_MODE_LIST = 'list';
    protected const CHECKBOX_DISPLAY_MODE_DIV = 'div';
    protected const CHECKBOX_TWIG_LIST = [
        self::CHECKBOX_DISPLAY_MODE_DIV => '@bazar/inputs/checkbox.twig',
        self::CHECKBOX_DISPLAY_MODE_LIST => '@bazar/inputs/checkbox_list.twig',
    ];

    protected const FROM_FORM_ID = '_fromForm';

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);
        $this->displayMethod = $values[self::FIELD_DISPLAY_METHOD];
        $this->displaySelectAllLimit = false;
        $this->displayFilterLimit = false;
        $this->formName = $this->name;
        $this->normalDisplayMode = self::CHECKBOX_DISPLAY_MODE_DIV;
        $this->dragAndDropDisplayMode = '';
        $this->orderBy = trim(strval($values[self::FIELD_ORDER_BY] ?? ''));
        $this->orderDirection = strtolower(trim(strval($values[self::FIELD_ORDER_DIRECTION] ?? ''))) === self::ORDER_DIRECTION_DESC
            ? self::ORDER_DIRECTION_DESC
            : self::ORDER_DIRECTION_ASC;
        $this->maxOptions = max(0, intval($values[self::FIELD_MAX_OPTIONS] ?? 0));
    }

    public function getValueStructure() // See BazarField::getValueStructure
    {
        return [$this->propertyName => ['_mode_' => 'multiple', '_type_' => 'string']];
    }

    protected function renderInput($entry)
    {
        $options = $this->getInputOptions($entry);

        switch ($this->displayMethod) {
            case 'tags':
                $htmlReturn = $this->render('@bazar/inputs/checkbox_tags.twig', [
                    'bazarlistTagsInputsData' => json_encode($this->generateTagsData($entry, $options)),
                ]);

                return $htmlReturn;
            case 'dragndrop':
                return $this->render($this->dragAndDropDisplayMode, [
                    'options' => $options,
                    'optionsDetails' => $this->getOptionsDetails(array_keys($options)),
                    'selectedOptionsId' => $this->getValues($entry),
                    'formName' => $this->formName ?? $this->getFormName(),
                    'name' => _t('BAZ_DRAG_n_DROP_CHECKBOX_LIST'),
                    'height' => empty($GLOBALS['wiki']->config['BAZ_CHECKBOX_DRAG_AND_DROP_MAX_HEIGHT']) ? null : $GLOBALS['wiki']->config['BAZ_CHECKBOX_DRAG_AND_DROP_MAX_HEIGHT'],
                    'oldValue' => $this->sanitizeValues($this->getValue($entry), 'string'),
                ]);
            default:
                // List with multi levels
                if ($this->optionsTree) {
                    return $this->render('@bazar/inputs/checkbox-tree.twig', [
                        'data' => $this->optionsTree,
                        'values' => $this->getValues($entry),
                        'displaySelectAllLimit' => $this->displaySelectAllLimit,
                        'oldValue' => $this->sanitizeValues($this->getValue($entry), 'string'),
                    ]);
                }

                if ($this->displayFilterLimit) {
                    // javascript additions
                    $GLOBALS['wiki']->AddJavascriptFile('tools/bazar/libs/vendor/jquery.fastLiveFilter.js');
                    $script = "$(function() { $('.filter-entries').each(function() {
                                $(this).fastLiveFilter($(this).parent().siblings('.list-bazar-entries,.bazar-checkbox-cols')); });
                            });";
                    $GLOBALS['wiki']->AddJavascript($script);
                }

                return $this->render(self::CHECKBOX_TWIG_LIST[$this->normalDisplayMode], [
                    'options' => $options,
                    'values' => $this->getValues($entry),
                    'displaySelectAllLimit' => $this->displaySelectAllLimit,
                    'displayFilterLimit' => $this->displayFilterLimit,
                    'oldValue' => $this->sanitizeValues($this->getValue($entry), 'string'),
                ]);
        }
    }

    public function getValues($entry)
    {
        $value = $this->getValue($entry);

        return $this->sanitizeValues($value, 'array');
    }

    /**
     * Options offered by the input : ordered, limited, and always keeping the selected ones.
     */
    protected function getInputOptions($entry): array
    {
        $options = $this->getOptions();
        if (!is_array($options)) {
            return [];
        }

        $options = $this->orderOptions($options);
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

    /**
     * Label of a recorded value the options do not offer any more.
     */
    protected function labelForMissingOption($optionId): string
    {
        return strval($optionId);
    }

    /**
     * Sort the options on the configured criterion, ascending or descending.
     */
    protected function orderOptions(array $options): array
    {
        switch ($this->orderBy) {
            case self::ORDER_BY_ID:
                uksort($options, function ($a, $b) {
                    return $this->compareForOrder($a, $b);
                });

                return $options;
            case self::ORDER_BY_LABEL:
                uasort($options, function ($a, $b) {
                    return $this->compareForOrder($a, $b);
                });

                return $options;
            default:
                return $this->orderDirection === self::ORDER_DIRECTION_DESC
                    ? array_reverse($options, true)
                    : $options;
        }
    }

    /**
     * Compare two option criteria, honouring the configured direction.
     */
    protected function compareForOrder($first, $second): int
    {
        $comparison = strnatcasecmp($this->sortableValue($first), $this->sortableValue($second));

        return $this->orderDirection === self::ORDER_DIRECTION_DESC ? -$comparison : $comparison;
    }

    /**
     * Flatten a raw value into a string usable by a natural order comparison.
     */
    protected function sortableValue($value): string
    {
        if ($value === null) {
            $value = '';
        }

        return removeAccents(is_scalar($value) ? strval($value) : json_encode($value));
    }

    /**
     * Extra data shown beside each option in the drag and drop input.
     */
    protected function getOptionsDetails(array $optionsIds): array
    {
        return [];
    }

    public function formatValuesBeforeSave($entry)
    {
        // We check if the field was emptied on purpose, so there is not merge of previous value
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
     * @param string|array $rawValue
     * @param string       $format   "string" or "array"
     *
     * @return array|string
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

    private function generateTagsData($entry, array $options)
    {
        // list of choices available from options
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

    protected function getFormName()
    {
        // needed for CheckboxEntry to update title only when
        // rendering Input and prevent infinite loop at construct
        return $this->formName;
    }
}
