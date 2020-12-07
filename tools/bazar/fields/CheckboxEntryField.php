<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Bazar\Service\FormManager;

/**
 * @Field({"checkboxfiche"})
 */
class CheckboxEntryField extends CheckboxField
{
    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);
        $this->type = 'checkboxfiche';

        $this->loadOptionsFromEntries();

        $this->displayFilterLimit =  $GLOBALS['wiki']->config['BAZ_MAX_CHECKBOXLISTE_SANS_FILTRE'] ;      
        $this->displaySelectAllLimit =  empty($GLOBALS['wiki']->config['BAZ_MAX_CHECKBOXENTRY_WITHOUT_SELECTALL']) ? $this->displayFilterLimit : $GLOBALS['wiki']->config['BAZ_MAX_CHECKBOXENTRY_WITHOUT_SELECTALL'] ;
        $this->formName = 'Fiches ' . $services->get(FormManager::class)->getOne($this->name)['bn_label_nature'] ;
        $this->displayMode = (in_array($GLOBALS['wiki']->config['BAZ_MAX_CHECKBOXENTRY_DISPLAY_MODE'],array_keys(self::CHECKBOX_TWIG_LIST))) ? 
            $GLOBALS['wiki']->config['BAZ_MAX_CHECKBOXENTRY_DISPLAY_MODE'] : self::CHECKBOX_DISPLAY_MODE_LIST ;
    }
    
    public function renderStatic($entry)
    {
        $keys = $this->getValues($entry);
        $values = [] ;
        foreach ($keys as $key_option) {
            if (in_array($key_option,array_keys($this->options['label']))) {
                $values[$key_option]['value'] = $this->options['label'][$key_option] ;
                $values[$key_option]['href'] = $GLOBALS['wiki']->href('', $key_option) ;
            }
        }

        return (count($values) > 0) ? $this->render('@bazar/fields/checkboxentry.twig', [
            'values' => $values
        ]) : '' ;
    }
    
    protected function renderDragAndDrop($entry)
    {
        $optionsUrl = [] ;
        foreach ($this->options['label'] as $key => $option){
            $optionsUrl[$key] = $GLOBALS['wiki']->href('', $key) ;
        }
        
        return $this->render('@bazar/inputs/checkbox_drag_and_drop_entry.twig', [
                'options' => $this->options['label'],
                'selectedOptionsId' => $this->getValues($entry),
                'optionsUrl' => $optionsUrl,
                'formName' => $this->formName,
                'name' => _t('BAZ_DRAG_n_DROP_CHECKBOX_LIST'),
                'height' => empty($GLOBALS['wiki']->config['BAZ_CHECKBOX_DRAG_AND_DROP_MAX_HEIGHT']) ? null : $GLOBALS['wiki']->config['BAZ_CHECKBOX_DRAG_AND_DROP_MAX_HEIGHT']
            ]);
    }
}
