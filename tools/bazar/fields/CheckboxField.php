<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;

abstract class CheckboxField extends EnumField
{
    protected $displaySelectAllLimit ; // number of items without selectall box ; false if no limit
    protected $displayFilterLimit ; // number of items without filter ; false if no limit
    protected $displayMethod ; // empty, tags or dragndrop
    protected $formName ; //form name for drag and drop
    protected $normalDisplayMode ;
    protected $dragAndDropDisplayMode ;

    protected const FIELD_DISPLAY_METHOD = 7;
    protected const CHECKBOX_DISPLAY_MODE_LIST = 'list' ;
    protected const CHECKBOX_DISPLAY_MODE_DIV = 'div' ;
    protected const CHECKBOX_TWIG_LIST = [
        self::CHECKBOX_DISPLAY_MODE_DIV => '@bazar/inputs/checkbox.twig',
        self::CHECKBOX_DISPLAY_MODE_LIST => '@bazar/inputs/checkbox_list.twig',
    ];

    protected const SUFFIX = '_raw' ;

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
            case "tags":
                $htmlReturn = $this->render('@bazar/inputs/checkbox_tags.twig') ;
                $script = $this->generateTagsScript($entry) ;
                $GLOBALS['wiki']->AddJavascript($script);
                return $htmlReturn ;
                break ;
            case "dragndrop":
                return $this->render($this->dragAndDropDisplayMode, [
                    'options' => $this->options,
                    'selectedOptionsId' => $this->getValues($entry),
                    'formName' => $this->formName,
                    'name' => _t('BAZ_DRAG_n_DROP_CHECKBOX_LIST'),
                    'height' => empty($GLOBALS['wiki']->config['BAZ_CHECKBOX_DRAG_AND_DROP_MAX_HEIGHT']) ? null : $GLOBALS['wiki']->config['BAZ_CHECKBOX_DRAG_AND_DROP_MAX_HEIGHT']
                ]);
                break ;
            default:
                if ($this->displayFilterLimit) {
                    // javascript additions
                    $GLOBALS['wiki']->AddJavascriptFile('tools/bazar/libs/vendor/jquery.fastLiveFilter.js');
                    $script = "$(function() { $('.filter-entries').each(function() {
                                $(this).fastLiveFilter($(this).siblings('.list-bazar-entries,.bazar-checkbox-cols')); });
                            });";
                    $GLOBALS['wiki']->AddJavascript($script);
                }
                return $this->render(self::CHECKBOX_TWIG_LIST[$this->normalDisplayMode], [
                    'options' => $this->options,
                    'values' => $this->getValues($entry),
                    'displaySelectAllLimit' => $this->displaySelectAllLimit,
                    'displayFilterLimit' => $this->displayFilterLimit
                ]);
        }
    }

    public function getValues($entry)
    {
        $value = $this->getValue($entry);
        return explode(',', $value);
    }
    
    public function formatValuesBeforeSave($entry)
    {
        if (isset($entry[$this->propertyName . self::SUFFIX])) {
            $checkboxField = $entry[$this->propertyName . self::SUFFIX] ;
            if (is_array($checkboxField)) {
                $checkboxField = array_filter($checkboxField, function ($value) {
                    return ($value == 1 || $value == true || $value == 'true') ;
                });
                $entry[$this->propertyName] = implode(',', array_keys($checkboxField)) ;
            } else {
                $entry[$this->propertyName] = $checkboxField ;
            }
            unset($entry[$this->propertyName . self::SUFFIX]) ;
        } elseif ($this->canEdit($entry)) {
            $entry[$this->propertyName] = '' ;
        } else {
            $entry[$this->propertyName] = $entry[$this->propertyName] ?? '' ;
        };
        return [$this->propertyName => $entry[$this->propertyName],
            'fields-to-remove' => [
                $this->propertyName . self::SUFFIX,
                $this->propertyName
                ]];
    }
    
    private function generateTagsScript($entry)
    {
        // list of choices available from options
        $choices = [] ;
        foreach ($this->options as $key => $label) {
            $choices[$key] = '{"id":"' . $key . '", "title":"' . str_replace('\'', '&#39;', str_replace('"', '\"', strip_tags($label))) . '"}';
        }

        $script = '$(function(){
            var tagsexistants = [' . implode(',', $choices) . '];
            var bazartag = [];
            bazartag["'.$this->propertyName.'"] = $(\'#formulaire .yeswiki-input-entries'.$this->propertyName.'\');
            bazartag["'.$this->propertyName.'"].tagsinput({
                itemValue: \'id\',
                itemText: \'title\',
                typeahead: {
                    afterSelect: function(val) { this.$element.val(""); },
                    source: tagsexistants
                },
                freeInput: false,
                confirmKeys: [13, 186, 188]
            });'."\n";
        
        $selectedOptions = $this->getValues($entry) ;
        if (is_array($selectedOptions) && count($selectedOptions)>0 && !empty($selectedOptions[0])) {
            foreach ($selectedOptions as $selectedOption) {
                if (isset($choices[$selectedOption])) {
                    $script .= 'bazartag["'.$this->propertyName.'"].tagsinput(\'add\', '.$choices[$selectedOption].');'."\n";
                }
            }
        }
        $script .= '});' . "\n";
        
        return $script ;
    }

    public function getSuffix(): string
    {
        return self::SUFFIX ;
    }
}
