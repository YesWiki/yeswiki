<?php

namespace YesWiki\Core\Field;

use Field;

#[\Field(['openinghours'])]
class OpeningHoursField extends BazarField
{
    protected function renderInput($entry)
    {
        return $this->render('@core/inputs/openingHours.twig', [
            'opening_hours' => $this->getValue($entry) ?? '',
            'title' => $this->getLabel(),
        ]);
    }

    protected function renderStatic($entry)
    {
        $value = $this->getValue($entry);
        if (!$value) {
            return '';
        }

        $GLOBALS['wiki']->addJavascriptFile('javascripts/vendor/vue/vue.js');
        $GLOBALS['wiki']->addJavascriptFile('javascripts/vendor/opening_hours/opening_hours.js');
        $GLOBALS['wiki']->addJavascriptFile('javascripts/fields/opening_hours.js');
        $GLOBALS['wiki']->addJavascriptFile('javascripts/vueapp.js');
        $GLOBALS['wiki']->AddCSSFile('styles/bazar/opening_hours.css');

        return $this->render('@core/fields/openingHours.twig', [
            'opening_hours' => $value,
            'title' => $this->getLabel(),
        ]);
    }

    protected function getValue($entry)
    {
        // TODO see if it is necessary to look for $_REQUEST
        // do not take default for this field
        return $entry[$this->propertyName] ?? null;
    }
}
