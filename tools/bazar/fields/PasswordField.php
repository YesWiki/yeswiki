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
        // Here, we force not to use public read acces for password (not empty, not presence of *)
        // because, the field is not rendered and it is not waited to see it in api, or bazar templates like tablea.twig
        $this->readAccess = empty($this->readAccess) ? '%' : str_replace(['!*', '*'], ['%', '%'], $this->readAccess);
    }

    public function formatValuesBeforeSave($entry)
    {
        return $this->formatValuesBeforeSaveIfEditable($entry, false);
    }

    public function formatValuesBeforeSaveIfEditable($entry, bool $isCreation = false)
    {
        $value = $this->getValue($entry);
        if ($this->canEdit($entry, $isCreation)) {
            if (!empty($value)) {
                // If a new password has been set, encode it
                return [$this->propertyName => md5($value),
                    'fields-to-remove' => [$this->propertyName . '-previous'], ];
            } else {
                // If no new password was set, keep the old encoded one
                return [$this->propertyName => $entry[$this->propertyName . '-previous'] ?? null,
                    'fields-to-remove' => [$this->propertyName . '-previous'], ];
            }
        } else {
            return [$this->propertyName => $value ?? null,
                'fields-to-remove' => [$this->propertyName . '-previous'], ];
        }
    }

    protected function renderStatic($entry)
    {
        // We never want to display passwords
        return '';
    }
}
