<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;

abstract class CheckboxField extends EnumField
{
    protected $displaySelectAllLimit; // number of items without selectall box ; false if no limit
    protected $displayFilterLimit; // number of items without filter ; false if no limit
    protected $displayMethod; // empty, tags or dragndrop
    protected $formName; //form name for drag and drop
    protected $normalDisplayMode;
    protected $dragAndDropDisplayMode;

    protected const FIELD_DISPLAY_METHOD = 7;
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
    }

    protected function renderInput($entry)
    {
        switch ($this->displayMethod) {
            case 'tags':
                $htmlReturn = $this->render('@bazar/inputs/checkbox_tags.twig', [
                    'bazarlistTagsInputsData' => json_encode($this->generateTagsData($entry)),
                ]);

                return $htmlReturn;
            case 'dragndrop':
                return $this->render($this->dragAndDropDisplayMode, [
                    'options' => $this->getOptions(),
                    'selectedOptionsId' => $this->getValues($entry),
                    'formName' => ($this->formName) ?? $this->getFormName(),
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
                    'options' => $this->getOptions(),
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

    public function formatValuesBeforeSave($entry)
    {
        return $this->formatValuesBeforeSaveIfEditable($entry, false);
    }

    public function formatValuesBeforeSaveIfEditable($entry, bool $isCreation = false)
    {
        if ($this->canEdit($entry, $isCreation)) {
            // get value
            $checkboxField = $entry[$this->propertyName] ?? null;
            // detect if from Form to check if clean field
            if (isset($entry[$this->propertyName . self::FROM_FORM_ID])) {
                $oldValue = $entry[$this->propertyName . self::FROM_FORM_ID];
                $oldValue = ($oldValue == "''") ? '' : $oldValue;
                if (!is_array($checkboxField) && ($checkboxField == $oldValue)) {
                    $checkboxField = '';
                }
            }

            // format value
            $entry[$this->propertyName] = $this->sanitizeValues($checkboxField, 'string');
        }

        return [$this->propertyName => $this->getValue($entry),
            'fields-to-remove' => [
                $this->propertyName . self::FROM_FORM_ID,
                $this->propertyName,
            ], ];
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

    private function generateTagsData($entry)
    {
        // list of choices available from options
        $existingTags = [];
        foreach ($this->getOptions() as $key => $label) {
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
