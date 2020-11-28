<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;

class PasswordField extends BazarField
{
    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->type = 'password';
        $this->maxChars = $this->maxChars ?? 255;
    }

    public function formatInput()
    {
        if (!empty($this->value)) {
            // If a new password has been set, encode it
            return [$this->entryId => md5($this->value)];
        } else {
            // If no new password was set, keep the old encoded one
            return [$this->entryId => $this->getEntryProp($this->entryId.'-previous')];
        }
    }

    public function renderField()
    {
        // We never want to display passwords
        return null;
    }
}
