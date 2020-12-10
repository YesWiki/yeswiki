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
        foreach ($keys as $key) {
            if (in_array($key,array_keys($this->options))) {
                $values[$key]['value'] = $this->options[$key] ;
                $values[$key]['href'] = $GLOBALS['wiki']->href('', $key) ;
            }
        }

        return (count($values) > 0) ? $this->render('@bazar/fields/checkboxentry.twig', [
            'values' => $values
        ]) : '' ;
    }
    
    protected function renderDragAndDrop($entry)
    {
        return $this->render('@bazar/inputs/checkbox_drag_and_drop_entry.twig', [
                'options' => $this->options,
                'selectedOptionsId' => $this->getValues($entry),
                'formName' => $this->formName,
                'name' => _t('BAZ_DRAG_n_DROP_CHECKBOX_LIST'),
                'height' => empty($GLOBALS['wiki']->config['BAZ_CHECKBOX_DRAG_AND_DROP_MAX_HEIGHT']) ? null : $GLOBALS['wiki']->config['BAZ_CHECKBOX_DRAG_AND_DROP_MAX_HEIGHT']
            ]);
    }
}
