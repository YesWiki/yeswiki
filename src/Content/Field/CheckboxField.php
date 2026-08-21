<?php

namespace YesWiki\Content\Field;

use Psr\Container\ContainerInterface;

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

    protected const FIELD_DISPLAY_METHOD = 7;
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
    }

    public function getValueStructure()
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
                    'options' => $this->getOptions(),
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
     *
     * @return array<string, mixed>
     */
    private function generateTagsData($entry)
    {
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

    /**
     * @return string|null
     */
    protected function getFormName()
    {
        return $this->formName;
    }
}
