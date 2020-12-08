<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;

/**
 * @Field({"champs_mail"})
 */
class EmailField extends BazarField
{
    protected $sendMail;
    protected $showContactForm;

    // Field-specific
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

    public function formatValuesBeforeSave($entry)
    {
        // TODO make sure the sendmail parameter is correctly passed
        return [
            $this->propertyName => $this->getValue($entry),
            'sendmail' => $this->sendMail
        ];
    }

    public function renderStatic($entry)
    {
        // TODO add JS libraries with Twig
        if( $this->showContactForm ) {
            $GLOBALS['wiki']->addJavascriptFile('tools/contact/libs/contact.js');
        }

        return $this->render('@bazar/fields/email.twig', [
            'value' => $this->getValue($entry),
            'showContactForm' => $this->showContactForm,
            'contactFormUrl' => $this->showContactForm ? $GLOBALS['wiki']->href('mail', $GLOBALS['wiki']->GetPageTag(), 'field='.$this->propertyName) : null
        ]);
    }
}
