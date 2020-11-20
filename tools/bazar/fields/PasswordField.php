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

    public function formatInput($entry)
    {
        if (!empty($entry[$this->entryId])) {
            // If a new password has been set, encode it
            return [$this->entryId => md5($entry[$this->entryId])];
        } elseif (isset($entry[$this->entryId.'-previous']) && !empty($entry[$this->entryId.'-previous'])) {
            // If no new password was set, keep the old encoded one
            return [$this->entryId => $entry[$this->entryId.'-previous']];
        }
    }

    public function renderField($entry)
    {
        // We never want to display passwords
        return null;
    }

    public function renderInput($entry)
    {
        if( $this->isInputHidden($entry) ) return '';

        return $this->render('@bazar/inputs/password.twig', [
            'value' => $entry !== '' ? $entry[$this->entryId] : null
        ]);
    }
}
