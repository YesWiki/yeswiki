<?php

namespace YesWiki\Content\Field;

#[\Field(['openinghours'])]
class OpeningHoursField extends BazarField
{
    use ContributesNoSearchableText;

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

        $this->getService(\YesWiki\Kernel\Service\AssetRegistry::class)->addJsFile('javascripts/vendor/vue/vue.js');
        $this->getService(\YesWiki\Kernel\Service\AssetRegistry::class)->addJsFile('javascripts/vendor/opening_hours/opening_hours.js');
        $this->getService(\YesWiki\Kernel\Service\AssetRegistry::class)->addJsFile('javascripts/fields/opening_hours.js');
        $this->getService(\YesWiki\Kernel\Service\AssetRegistry::class)->addJsFile('javascripts/vueapp.js');
        $this->getService(\YesWiki\Kernel\Service\AssetRegistry::class)->addCssFile('styles/yw-entries.css');

        return $this->render('@core/fields/openingHours.twig', [
            'opening_hours' => $value,
            'title' => $this->getLabel(),
        ]);
    }

    protected function getValue($entry)
    {
        return $entry[$this->propertyName] ?? null;
    }
}
