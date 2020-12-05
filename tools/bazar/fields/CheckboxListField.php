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
    }
}
