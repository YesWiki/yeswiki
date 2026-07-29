<?php

namespace YesWiki\Content\Field;

use Psr\Container\ContainerInterface;

abstract class CheckboxField extends EnumField
{
    protected $displaySelectAllLimit; // number of items without selectall box ; false if no limit
    protected $displayFilterLimit; // number of items without filter ; false if no limit
    protected $displayMethod; // empty, tags or dragndrop
    protected $formName; // form name for drag and drop
    protected $normalDisplayMode;
    protected $dragAndDropDisplayMode;

    protected const FIELD_DISPLAY_METHOD = 7;
    protected const CHECKBOX_DISPLAY_MODE_LIST = 'list';
    protected const CHECKBOX_DISPLAY_MODE_DIV = 'div';
    protected const CHECKBOX_TWIG_LIST = [
        self::CHECKBOX_DISPLAY_MODE_DIV => '@core/inputs/checkbox.twig',
        self::CHECKBOX_DISPLAY_MODE_LIST => '@core/inputs/checkbox_list.twig',
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

    public function getValueStructure() // See BazarField::getValueStructure
    {
        return [$this->propertyName => ['_mode_' => 'multiple', '_type_' => 'string']];
    }

    protected function renderInput($entry)
    {
        switch ($this->displayMethod) {
            case 'tags':
                $htmlReturn = $this->render('@core/inputs/checkbox_tags.twig', [
                    'tagsData' => $this->generateTagsData($entry),
                ]);

                return $htmlReturn;
            case 'dragndrop':
                return $this->render($this->dragAndDropDisplayMode, [
                    'options' => $this->getOptions(),
                    'selectedOptionsId' => $this->getValues($entry),
                    'formName' => $this->formName ?? $this->getFormName(),
                    'name' => _t('BAZ_DRAG_n_DROP_CHECKBOX_LIST'),
                    'height' => empty($GLOBALS['wiki']->config['BAZ_CHECKBOX_DRAG_AND_DROP_MAX_HEIGHT']) ? null : $GLOBALS['wiki']->config['BAZ_CHECKBOX_DRAG_AND_DROP_MAX_HEIGHT'],
                    'oldValue' => $this->sanitizeValues($this->getValue($entry), 'string'),
                ]);
            default:
                // List with multi levels
                if ($this->optionsTree) {
                    return $this->render('@core/inputs/checkbox-tree.twig', [
                        'data' => $this->optionsTree,
                        'values' => $this->getValues($entry),
                        'displaySelectAllLimit' => $this->displaySelectAllLimit,
                        'oldValue' => $this->sanitizeValues($this->getValue($entry), 'string'),
                    ]);
                }

                if ($this->displayFilterLimit) {
                    $GLOBALS['wiki']->services->get(\YesWiki\Kernel\Service\AssetsManager::class)->AddJavascriptFile('javascripts/inputs/filter-entries.js');
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
