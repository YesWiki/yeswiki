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
        $this->displaySelectAllLimit = empty($GLOBALS['wiki']->config['BAZ_MAX_CHECKBOXENTRY_WITHOUT_SELECTALL']) ?
            $this->displayFilterLimit :
            $GLOBALS['wiki']->config['BAZ_MAX_CHECKBOXENTRY_WITHOUT_SELECTALL'];
        $form = $services->get(FormManager::class)->getOne($this->name);
        $this->formName = $form ?
            ('Fiches ' . $services->get(FormManager::class)->getOne($this->name)['bn_label_nature']) :
            _t('BAZ_NO_FORMS_FOUND');
        $this->normalDisplayMode = (in_array($GLOBALS['wiki']->config['BAZ_MAX_CHECKBOXENTRY_DISPLAY_MODE'],
            array_keys(self::CHECKBOX_TWIG_LIST))) ? $GLOBALS['wiki']->config['BAZ_MAX_CHECKBOXENTRY_DISPLAY_MODE'] :
            self::CHECKBOX_DISPLAY_MODE_LIST ;
        $this->dragAndDropDisplayMode='@bazar/inputs/checkbox_drag_and_drop_entry.twig' ;
    }
    
    protected function renderStatic($entry)
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
}
