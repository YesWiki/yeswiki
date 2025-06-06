<?php

namespace YesWiki\Bazar\Field;

use YesWiki\Bazar\Field\BazarField;

/**
 * @Field({"openinghours"})
 */
class OpeningHoursField extends BazarField
{
    protected function renderInput($entry)
    {
        return $this->render('@bazar/inputs/openingHours.twig', [
            'opening_hours' => $this->getValue($entry) ?? "",
            'title' => $this->getLabel()
        ]);
    }

    protected function renderStatic($entry)
    {
        $value = $this->getValue($entry);
        if (!$value) {
            return '';
        }


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
