<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;

/**
 * @Field({"mot_de_passe"})
 */
class PasswordField extends BazarField
{
    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->type = 'password';
        $this->maxChars = $this->maxChars ?? 255;
    }

    public function formatValuesBeforeSave($entry)
    {
        $value = $this->getValue($entry);
        if (!empty($value)) {
            // If a new password has been set, encode it
            return [$this->propertyName => md5($value),
                'fields-to-remove' => [$this->propertyName.'-previous']];
        } else {
            // If no new password was set, keep the old encoded one
            return [$this->propertyName => $entry[$this->propertyName.'-previous'] ?? null,
                'fields-to-remove' => [$this->propertyName.'-previous']];
        }
    }

    protected function renderStatic($entry)
    {
        // We never want to display passwords
        return null;
    }
}
