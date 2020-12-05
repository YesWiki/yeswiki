<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;

class CheckboxListField extends EnumListField
{
    
    protected $display_select_all_limit ; // number of items without selectall box ; false if no limit
    protected const FIELD_DISPLAY_METHOD = 7;
    protected $display_method ; // empty, tags or dragndrop
    
    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->type = 'checkbox';
        $this->display_select_all_limit =  empty($GLOBALS['wiki']->config['BAZ_MAX_CHECKBOXLISTE_WITHOUT_SELECTALL']) ? false : $GLOBALS['wiki']->config['BAZ_MAX_CHECKBOXLISTE_WITHOUT_SELECTALL'] ;
        $this->display_method = $values[self::FIELD_DISPLAY_METHOD];
    }

    public function renderInput($entry)
    {
        switch ($this->display_method) {
            case "tags":
                $script = $this->generateTagsScript($entry) ;
                $GLOBALS['wiki']->AddJavascriptFile('tools/tags/libs/vendor/bootstrap-tagsinput.min.js');
                $GLOBALS['wiki']->AddJavascript($script);
                return $this->render('@bazar/inputs/checkbox_tags.twig'); 
                break ;
            case "dragndrop":
            default:
               return $this->render('@bazar/inputs/checkbox.twig', [
                    'options' => $this->options['label'],
                    'values' => $this->getValues($entry),
                    'display_select_all_limit' => $this->display_select_all_limit
                ]); 
        }
    }

    public function renderStatic($entry)
    {
        $keys = $this->getValues($entry);
        $values = array() ;
        foreach ($this->options['label'] as $key_option => $option ) {
            if (in_array($key_option,$keys)) {
                $values[] = $option ;
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
        $array_choices = array() ; 
        foreach ($this->options['label'] as $key_option => $option ) {
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
    
}
