<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;

class CheckboxListField extends CheckboxField
{
    
    private $EnumListFieldObject ; // EnumListField object 
    
    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);
        $this->type = 'checkbox';
        $this->EnumListFieldObject =  new class($values, $services) extends EnumListField{} ;    
        $this->options = $this->EnumListFieldObject->getOptions()['label'];  
        $this->display_filter_limit =  empty($GLOBALS['wiki']->config['BAZ_MAX_CHECKBOXLIST_WITHOUT_FILTER']) ? false : $GLOBALS['wiki']->config['BAZ_MAX_CHECKBOXLIST_WITHOUT_FILTER'] ;      
        $this->display_select_all_limit =  empty($GLOBALS['wiki']->config['BAZ_MAX_CHECKBOXLIST_WITHOUT_SELECTALL']) ? $this->display_filter_limit : $GLOBALS['wiki']->config['BAZ_MAX_CHECKBOXLIST_WITHOUT_SELECTALL'] ;
        $this->form_name = _t('BAZ_DRAG_n_DROP_CHECKBOX_LIST') . ' ' . $this->name ;
        $this->checkbox_display_mode = (!empty($GLOBALS['wiki']->config['BAZ_MAX_CHECKBOXLIST_DISPLAY_MODE']) &&
            in_array($GLOBALS['wiki']->config['BAZ_MAX_CHECKBOXLIST_DISPLAY_MODE'],array_keys(self::CHECKBOX_TWIG_LIST))) ? 
            $GLOBALS['wiki']->config['BAZ_MAX_CHECKBOXLIST_DISPLAY_MODE'] : self::CHECKBOX_DISPLAY_MODE_DIV ;
    }
}
