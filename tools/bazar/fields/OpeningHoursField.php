<?php

namespace YesWiki\Bazar\Field;

/**
 * @Field({"openinghours"})
 */
class OpeningHoursField extends BazarField
{

    private const FIELD_CLASS_TYPE = 'OpeningHoursField';

    protected function renderInput($entry)
    {
        return $this->render('@bazar/inputs/openingHours.twig', [
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
        $GLOBALS['wiki']->addJavascriptFile('tools/bazar/presentation/javascripts/fields/opening_hours.js');
        $GLOBALS['wiki']->addJavascriptFile('tools/bazar/presentation/javascripts/vueapp.js');
        $GLOBALS['wiki']->AddCSSFile('tools/bazar/presentation/styles/opening_hours.css');

        return $this->render('@bazar/fields/openingHours.twig', [
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
