<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;

/**
 * @Field({"checkbox"})
 */
class CheckboxListField extends CheckboxField
{
    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);
        $this->type = 'checkbox';

        $this->loadOptionsFromList();

        $this->displayFilterLimit =  $GLOBALS['wiki']->config['BAZ_MAX_CHECKBOXLIST_WITHOUT_FILTER'] ;      
        $this->displaySelectAllLimit =  empty($GLOBALS['wiki']->config['BAZ_MAX_CHECKBOXLIST_WITHOUT_SELECTALL']) ? $this->displayFilterLimit : $GLOBALS['wiki']->config['BAZ_MAX_CHECKBOXLIST_WITHOUT_SELECTALL'] ;
        $this->formName = _t('BAZ_DRAG_n_DROP_CHECKBOX_LIST') . ' ' . $this->name ;
        $this->displayMode = (in_array($GLOBALS['wiki']->config['BAZ_MAX_CHECKBOXLIST_DISPLAY_MODE'],array_keys(self::CHECKBOX_TWIG_LIST))) ? 
            $GLOBALS['wiki']->config['BAZ_MAX_CHECKBOXLIST_DISPLAY_MODE'] : self::CHECKBOX_DISPLAY_MODE_DIV ;
    }

    public function renderStatic($entry)
    {
        $keys = $this->getValues($entry);
        $values = [] ;
        foreach ($this->options as $key => $label ) {
            if (in_array($key,$keys)) {
                $values[$key] = $label ;
            }
        }
        return (count($values) > 0) ? $this->render('@bazar/fields/checkbox.twig', [
            'values' => $values
        ]) : '' ;
    }

    protected function renderDragAndDrop($entry)
    {
        return $this->render('@bazar/inputs/checkbox_drag_and_drop.twig', [
            'options' => $this->options,
            'selectedOptionsId' => $this->getValues($entry),
            'formName' => $this->formName,
            'name' => _t('BAZ_DRAG_n_DROP_CHECKBOX_LIST'),
            'height' => empty($GLOBALS['wiki']->config['BAZ_CHECKBOX_DRAG_AND_DROP_MAX_HEIGHT']) ? null : $GLOBALS['wiki']->config['BAZ_CHECKBOX_DRAG_AND_DROP_MAX_HEIGHT']
        ]);
    }
}
