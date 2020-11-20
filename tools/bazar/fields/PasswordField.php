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
        if (!empty($entry[$this->recordId])) {
            // If a new password has been set, encode it
            return [$this->recordId => md5($entry[$this->recordId])];
        } elseif (isset($entry[$this->recordId.'-previous']) && !empty($entry[$this->recordId.'-previous'])) {
            // If no new password was set, keep the old encoded one
            return [$this->recordId => $entry[$this->recordId.'-previous']];
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
            'value' => $entry !== '' ? $entry[$this->recordId] : null
        ]);
    }
}
