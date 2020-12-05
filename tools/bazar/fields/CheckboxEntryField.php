<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;

class CheckboxEntryField extends CheckboxField
{
    
    private $EnumEntryFieldObject ; // EnumListField object 
    
    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);
        $this->type = 'checkboxfiche';
        $this->EnumEntryFieldObject =  new class($values, $services) extends EnumEntryField{} ;    
        $this->options = $this->EnumEntryFieldObject->getOptions()['label'];
        $this->display_filter_limit =  empty($GLOBALS['wiki']->config['BAZ_MAX_CHECKBOXLISTE_SANS_FILTRE']) ? false : $GLOBALS['wiki']->config['BAZ_MAX_CHECKBOXLISTE_SANS_FILTRE'] ;      
        $this->display_select_all_limit =  empty($GLOBALS['wiki']->config['BAZ_MAX_CHECKBOXENTRY_WITHOUT_SELECTALL']) ? $this->display_filter_limit : $GLOBALS['wiki']->config['BAZ_MAX_CHECKBOXENTRY_WITHOUT_SELECTALL'] ;
    }
    
    public function renderStatic($entry)
    {
        $keys = $this->getValues($entry);
        $values = array() ;
        foreach ($keys as $key_option) {
            if (in_array($key_option,array_keys($this->options))) {
                $values[$key_option]['value'] = $this->options[$key_option] ;
                $values[$key_option]['href'] = $GLOBALS['wiki']->href('', $key_option) ;
            }
        }
        return (count($values) > 0) ? $this->render('@bazar/fields/checkboxentry.twig', [
            'values' => $values
        ]) : '' ;
    }
}
