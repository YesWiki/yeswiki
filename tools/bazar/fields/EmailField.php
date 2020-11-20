<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;

class EmailField extends BazarField
{
    protected $sendMail;
    protected $showContactForm;

    protected const FIELD_SHOW_CONTACT_FORM = 6;
    protected const FIELD_SEND_EMAIL = 9;

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->type = 'email';
        $this->sendMail = $values[self::FIELD_SEND_EMAIL] == 1;
        $this->showContactForm = $values[self::FIELD_SHOW_CONTACT_FORM] == 'form';
        $this->maxChars = $this->maxChars ?? 255;
    }

    public function formatInput($entry)
    {
        return array_key_exists($this->recordId, $entry) ?
            [
                $this->recordId => $entry[$this->recordId],
                'sendmail' => $this->sendMail
            ] :
            [
                $this->recordId => null,
                'sendmail' => $this->sendMail
            ];
    }

    public function renderField($entry)
    {
        // TODO add JS libraries with Twig
        if( $this->showContactForm ) {
            $GLOBALS['wiki']->addJavascriptFile('tools/contact/libs/contact.js');
        }

        return $this->render('@bazar/fields/email.twig', [
            'value' => $entry !== '' ? $entry[$this->recordId] : null,
            'showContactForm' => $this->showContactForm,
            'contactFormUrl' => $this->showContactForm ? $GLOBALS['wiki']->href('mail', $GLOBALS['wiki']->GetPageTag(), 'field='.$this->recordId) : null
        ]);
    }

    public function renderInput($entry)
    {
        if( $this->isInputHidden($entry) ) return '';

        return $this->render('@bazar/inputs/email.twig', [
            'value' => $entry !== '' ? $entry[$this->recordId] : null
        ]);
    }
}
