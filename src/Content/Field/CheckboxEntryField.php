<?php

namespace YesWiki\Content\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Content\Service\FormManager;

#[\Field(['checkboxfiche'])]
class CheckboxEntryField extends CheckboxField
{
    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);
        $this->type = 'checkboxfiche';

        // load options only when needed but not at construct to prevent infinite loops

        $this->displayFilterLimit = $this->getService(\YesWiki\Kernel\Service\RuntimeConfig::class)['BAZ_MAX_CHECKBOXLISTE_SANS_FILTRE'];
        $this->displaySelectAllLimit = empty($this->getService(\YesWiki\Kernel\Service\RuntimeConfig::class)['BAZ_MAX_CHECKBOXENTRY_WITHOUT_SELECTALL']) ?
            $this->displayFilterLimit :
            $this->getService(\YesWiki\Kernel\Service\RuntimeConfig::class)['BAZ_MAX_CHECKBOXENTRY_WITHOUT_SELECTALL'];
        $this->formName = null;
        $this->normalDisplayMode = (in_array(
            $this->getService(\YesWiki\Kernel\Service\RuntimeConfig::class)['BAZ_MAX_CHECKBOXENTRY_DISPLAY_MODE'],
            array_keys(self::CHECKBOX_TWIG_LIST)
        )) ? $this->getService(\YesWiki\Kernel\Service\RuntimeConfig::class)['BAZ_MAX_CHECKBOXENTRY_DISPLAY_MODE'] :
            self::CHECKBOX_DISPLAY_MODE_LIST;
        $this->dragAndDropDisplayMode = '@core/inputs/checkbox_drag_and_drop_entry.twig';

        // ticket 34: a linked form is local, never a URL to another wiki
        $this->options = null;
    }

    protected function renderStatic($entry)
    {
        $keys = $this->getValues($entry);
        $values = [];
        foreach ($keys as $key) {
            if (in_array($key, array_keys($this->getOptions()))) {
                $values[$key]['value'] = $this->options[$key];
                $values[$key]['href'] = $this->getService(\YesWiki\Kernel\Service\UrlFormatter::class)->href('', $key);
            }
        }

        return (count($values) > 0) ? $this->render('@core/fields/checkboxentry.twig', [
            'values' => $values,
        ]) : '';
    }

    protected function getFormName()
    {
        // needed for CheckboxEntry to update title only when
        // rendering Input and prevent infinite loop at construct

        if (!empty($this->name)) {
            $form = $this->services->get(FormManager::class)->getOne($this->name);
            $this->formName = isset($form['label']) ? ('Fiches ' . $form['label']) : _t('BAZ_NO_FORMS_FOUND');
        }

        return $this->formName;
    }

    public function getOptions()
    {
        return $this->getEntriesOptions();
    }

    /**
     * check if the current class is EnumEntry.
     */
    public function isEnumEntryField(): bool
    {
        return true;
    }
}
