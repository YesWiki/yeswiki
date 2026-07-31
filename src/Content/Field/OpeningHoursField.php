<?php

namespace YesWiki\Content\Field;

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

        $GLOBALS['yeswikiServices']->get(\YesWiki\Kernel\Service\AssetRegistry::class)->addJsFile('javascripts/vendor/vue/vue.js');
        $GLOBALS['yeswikiServices']->get(\YesWiki\Kernel\Service\AssetRegistry::class)->addJsFile('javascripts/vendor/opening_hours/opening_hours.js');
        $GLOBALS['yeswikiServices']->get(\YesWiki\Kernel\Service\AssetRegistry::class)->addJsFile('javascripts/fields/opening_hours.js');
        $GLOBALS['yeswikiServices']->get(\YesWiki\Kernel\Service\AssetRegistry::class)->addJsFile('javascripts/vueapp.js');
        $GLOBALS['yeswikiServices']->get(\YesWiki\Kernel\Service\AssetRegistry::class)->addCssFile('styles/bazar/opening_hours.css');

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
