<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Bazar\Controller\ApiController as BazarApiController;

/**
 * @Field({"champs_mail"})
 */
class EmailField extends BazarField
{
    protected $sendMail;
    public $showContactForm;

    // Field-specific
    protected const FIELD_SHOW_CONTACT_FORM = 6;
    protected const FIELD_SEND_EMAIL = 9;

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->type = 'email';
        $this->sendMail = $values[self::FIELD_SEND_EMAIL] == 1;
        $this->showContactForm = $values[self::FIELD_SHOW_CONTACT_FORM] === 'form';
        $this->maxChars = $this->maxChars ?? 255;
    }

    public function formatValuesBeforeSave($entry)
    {
        if ($this->sendMail) {
            // add propertyName to the list of emails if several sendmail in same form
            $sendmailList = !empty($entry['sendmail']) ?
                $entry['sendmail'] . ',' . $this->propertyName
                : $this->propertyName;
            $sendmailArray = ['sendmail' => $sendmailList];
        } else {
            $sendmailArray = [];
        }
        return array_merge(
            [$this->propertyName => $this->getValue($entry)],
            $sendmailArray
        );
    }

    protected function renderStatic($entry)
    {
        $value = $this->getValue($entry);
        if (!$value) {
            return "";
        }

        // TODO add JS libraries with Twig
        if ($this->showContactForm) {
            $GLOBALS['wiki']->addJavascriptFile('tools/contact/libs/contact.js');
        }

        return $this->render('@bazar/fields/email.twig', [
            'value' => $value,
        ]);
    }

    public function canRead($entry, ?string $userNameForRendering = null)
    {
        return parent::canRead($entry,$userNameForRendering) && (
            !$this->getShowContactForm() ||
            // cas des formulaires champs mails, qui ne doivent pas apparaitre en /raw
            $this->canDisplayEmailForThisUrl()
        );
    }

    protected function canDisplayEmailForThisUrl(): bool
    {
        $wiki = $this->getWiki();
        $bazarApiController = $this->getService(BazarApiController::class);
        return (
            $wiki->GetPageTag() !== 'api'
            &&
            in_array($wiki->getMethod(), ['show','edit','editiframe','mail'])
        ) ||
        (
            $wiki->GetPageTag() !== 'api'
            &&
            // only authorized api routes /api/entries/html/{selectedEntry}&fields=html_output
            $bazarApiController->isEntryViewFastAccessHelper()
        );
    }

    public function getShowContactForm()
    {
        return $this->showContactForm ;
    }

    // change return of this method to keep compatible with php 7.3 (mixed is not managed)
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return array_merge(
            parent::jsonSerialize(),
            [
                'sendMail' => $this->sendMail,
                'showContactForm' => $this->getShowContactForm()
            ]
        );
    }
}
