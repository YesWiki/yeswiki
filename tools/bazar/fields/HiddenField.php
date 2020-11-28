<?php

namespace YesWiki\Bazar\Field;

class HiddenField extends BazarField
{
    public function renderField()
    {
        return null;
    }

    public function renderInput()
    {
        return $this->render('@bazar/inputs/hidden.twig');
    }
}
