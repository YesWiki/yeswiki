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
        // TODO remove this call creation of class extended from EnumListField and use EnumListPerformer
        $this->EnumListFieldObject =  new class($values, $services) extends EnumListField{} ;    
        $this->options = $this->EnumListFieldObject->getOptions()['label'];  
        $this->displayFilterLimit =  $GLOBALS['wiki']->config['BAZ_MAX_CHECKBOXLIST_WITHOUT_FILTER'] ;      
        $this->displaySelectAllLimit =  empty($GLOBALS['wiki']->config['BAZ_MAX_CHECKBOXLIST_WITHOUT_SELECTALL']) ? $this->displayFilterLimit : $GLOBALS['wiki']->config['BAZ_MAX_CHECKBOXLIST_WITHOUT_SELECTALL'] ;
        $this->formName = _t('BAZ_DRAG_n_DROP_CHECKBOX_LIST') . ' ' . $this->name ;
        $this->displayMode = (in_array($GLOBALS['wiki']->config['BAZ_MAX_CHECKBOXLIST_DISPLAY_MODE'],array_keys(self::CHECKBOX_TWIG_LIST))) ? 
            $GLOBALS['wiki']->config['BAZ_MAX_CHECKBOXLIST_DISPLAY_MODE'] : self::CHECKBOX_DISPLAY_MODE_DIV ;
    }
}
