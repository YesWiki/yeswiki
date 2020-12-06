<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Bazar\Service\FormManager;

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
        $this->form_name = 'Fiches ' . $services->get(FormManager::class)->getOne($this->name)['bn_label_nature'] ;
        $this->checkbox_display_mode = (!empty($GLOBALS['wiki']->config['BAZ_MAX_CHECKBOXENTRY_DISPLAY_MODE']) &&
            in_array($GLOBALS['wiki']->config['BAZ_MAX_CHECKBOXENTRY_DISPLAY_MODE'],array_keys(self::CHECKBOX_TWIG_LIST))) ? 
            $GLOBALS['wiki']->config['BAZ_MAX_CHECKBOXENTRY_DISPLAY_MODE'] : self::CHECKBOX_DISPLAY_MODE_LIST ;
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
    
    protected function renderDragAndDrop($entry)
    {   
        $options_href = array() ;
        foreach ($this->options as $key => $option){
           $options_href[$key] = $GLOBALS['wiki']->href('', $key) ;
        }
        
        return $this->render('@bazar/inputs/checkbox_drag_and_drop_entry.twig', [
                'options' => $this->options,
                'selected_options_id' => $this->getValues($entry),
                'options_href' => $options_href,
                'form_name' => $this->form_name,
                'name' => _t('BAZ_DRAG_n_DROP_CHECKBOX_LIST'),
                'height' => empty($GLOBALS['wiki']->config['BAZ_CHECKBOX_DRAG_AND_DROP_MAX_HEIGHT']) ? null : empty($GLOBALS['wiki']->config['BAZ_CHECKBOX_DRAG_AND_DROP_MAX_HEIGHT'])
            ]);
    }
}
