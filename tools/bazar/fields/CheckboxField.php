<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;


// TODO extends EnumField for parameter options but after refacto EnumPerformer should extends BazarField
// and have options parameter or equivalent
class CheckboxField extends EnumField
{   
    protected const FIELD_DISPLAY_METHOD = 7;
    protected const CHECKBOX_DISPLAY_MODE_LIST = 'list' ;
    protected const CHECKBOX_DISPLAY_MODE_DIV = 'div' ;
    protected const CHECKBOX_TWIG_LIST = [
        self::CHECKBOX_DISPLAY_MODE_DIV => '@bazar/inputs/checkbox.twig',
        self::CHECKBOX_DISPLAY_MODE_LIST => '@bazar/inputs/checkbox_list.twig',
        ];
    protected $displaySelectAllLimit ; // number of items without selectall box ; false if no limit
    protected $displayFilterLimit ; // number of items without filter ; false if no limit
    protected $displayMethod ; // empty, tags or dragndrop
    protected $formName ; //form name for drag and drop
    protected $displayMode ;
    
    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);
        $this->displayMethod = $values[self::FIELD_DISPLAY_METHOD];
        $this->displaySelectAllLimit = false ;
        $this->displayFilterLimit = false ;
        $this->formName = $this->name ;
        $this->displayMode = self::CHECKBOX_DISPLAY_MODE_DIV ;
    }

    public function renderInput($entry)
    {
        switch ($this->displayMethod) {
            case "tags":
                $script = $this->generateTagsScript($entry) ;
                $GLOBALS['wiki']->AddJavascriptFile('tools/tags/libs/vendor/bootstrap-tagsinput.min.js');
                $GLOBALS['wiki']->AddJavascript($script);
                return $this->render('@bazar/inputs/checkbox_tags.twig'); 
                break ;
            case "dragndrop":
                $GLOBALS['wiki']->AddCSSFile('tools/bazar/presentation/styles/checkbox-drag-and-drop.css');
                
                // ONLY FOR TWIG waiting for function twig allowing AddJavascriptFile
                $GLOBALS['wiki']->AddJavascriptFile('tools/bazar/libs/vendor/jquery-ui-sortable/jquery-ui.min.js');
                $GLOBALS['wiki']->AddJavascriptFile('tools/bazar/libs/vendor/jquery.fastLiveFilter.js');
                $GLOBALS['wiki']->AddJavascriptFile('tools/bazar/presentation/javascripts/checkbox-drag-and-drop.js');
                return $this->renderDragAndDrop($entry);
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
                return $this->render(self::CHECKBOX_TWIG_LIST[$this->displayMode], [
                    'options' => $this->options,
                    'values' => $this->getValues($entry),
                    'displaySelectAllLimit' => $this->displaySelectAllLimit,
                    'displayFilterLimit' => $this->displayFilterLimit
                ]); 
        }
    }

    public function renderStatic($entry)
    {
        $keys = $this->getValues($entry);
        $values = [] ;
        foreach ($this->options as $key_option => $option ) {
            if (in_array($key_option,$keys)) {
                $values[$key_option] = $option ;
            }
        }
        return (count($values) > 0) ? $this->render('@bazar/fields/checkbox.twig', [
            'values' => $values
        ]) : '' ;
    }
    
    public function getValues($entry)
    {
        $value = $this->getValue($entry);
        return explode(',', $value);
    }
    
    public function formatValuesBeforeSave($entry)
    {            
        foreach ($entry as $key => $val) {
            if (is_array($val)) {
                foreach ($val as $key_val => $value) {
                    if ($value == 0 && $key_val == "END_INDEX NO_CHANGE_IT"){
                        unset($val[$key_val]) ;
                    }
                }
                
                if (empty(array_keys($val))) {
                    unset($entry[$key]) ;
                } else {
                    $entry[$key] = implode(',', array_keys($val)) ;
                }
            }
        }
        return [$this->propertyName => $this->getValue($entry)];
    }
    
    private function generateTagsScript($entry)
    {
        // list of choices available from options
        $array_choices = [] ; 
        foreach ($this->options as $key_option => $option ) {
            $array_choices[$key_option] = '{"id":"' . $key_option . '", "title":"'
                . str_replace('\'', '&#39;', str_replace('"', '\"', $option)) . '"}';
        }
        $script = '' ;
        $script = '$(function(){
            var tagsexistants = [' . implode(',', $array_choices) . '];
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
                if (isset($array_choices[$selectedOption])) {
                    $script .= 'bazartag["'.$this->propertyName.'"].tagsinput(\'add\', '.$array_choices[$selectedOption].');'."\n";
                }
            }
        }
        $script .= '});' . "\n";
        
        return $script ;        
    }
    
    protected function renderDragAndDrop($entry)
    {     
        return $this->render('@bazar/inputs/checkbox_drag_and_drop.twig', [
                'options' => $this->options,
                'selected_options_id' => $this->getValues($entry),
                'formName' => $this->formName,
                'name' => _t('BAZ_DRAG_n_DROP_CHECKBOX_LIST'),
                'height' => empty($GLOBALS['wiki']->config['BAZ_CHECKBOX_DRAG_AND_DROP_MAX_HEIGHT']) ? null : $GLOBALS['wiki']->config['BAZ_CHECKBOX_DRAG_AND_DROP_MAX_HEIGHT']
            ]);
    }
}
