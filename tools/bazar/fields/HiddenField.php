<?php

namespace YesWiki\Bazar\Field;

class HiddenField extends BazarField
{
    public function renderField($entry)
    {
        return null;
    }

    public function renderInput($entry)
    {
        return $this->render('@bazar/inputs/hidden.twig');
    }
}
