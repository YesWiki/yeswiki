<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Bazar\Controller\ApiController as BazarApiController;
use YesWiki\Core\Service\AclService;

/**
 * @Field({"champs_mail"})
 */
class EmailField extends BazarField
{
    protected $seeEmailAcls;
    protected $sendMail;
    protected $showContactForm;

    // Field-specific
    protected const FIELD_SHOW_CONTACT_FORM = 6;
    protected const FIELD_SEE_MAIL_ACLS = 4;
    protected const FIELD_SEND_EMAIL = 9;

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->type = 'email';
        $this->sendMail = $values[self::FIELD_SEND_EMAIL] == 1;
        $this->showContactForm = $values[self::FIELD_SHOW_CONTACT_FORM] === 'form';
        $this->maxChars = $this->maxChars ?? 255;
        $this->seeEmailAcls = (!empty($values[self::FIELD_SEE_MAIL_ACLS]) && is_string($values[self::FIELD_SEE_MAIL_ACLS]) && !empty(trim($values[self::FIELD_SEE_MAIL_ACLS])))
        ? trim($values[self::FIELD_SEE_MAIL_ACLS])
        : '@admins' ; // default
        $this->seeEmailAcls = str_replace(',',"\n",$this->seeEmailAcls);
        $this->maxChars = '';
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
            return '';
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
        $wiki = $this->getWiki();
        $aclService = $this->getService(AclService::class);
        $bazarApiController = $this->getService(BazarApiController::class);

        // we test if we need an acl exception for an entry's email in a contact form, even if the display acls are against
        if ($this->getShowContactForm()) {
            $contactFormException = $entry['id_fiche'] ===  $wiki->GetPageTag() // exception only if one entry's page
            && (
              ($wiki->GetPageTag() !== 'api' // if not api, just for certain handlers
                && in_array($wiki->getMethod(), ['show', 'html', 'edit', 'editiframe', 'mail'])
              )
              || ($wiki->GetPageTag() == 'api' // only authorized api routes /api/entries/html/{selectedEntry}&fields=html_output
                && $bazarApiController->isEntryViewFastAccessHelper() 
              )
            );
        } else {
            // by default, if `showContactForm` is false, show email everywhere if read acl OK
            $contactFormException = true; 
        }
        return parent::canRead($entry, $userNameForRendering) // field acls
           && (
            $contactFormException // can read the email's value for contact form in the entry's page
            || $aclService->check($this->getSeeEmailAcls(), $userNameForRendering, true) // check if user is allowed to see raw email
          );
    }

    public function getShowContactForm()
    {
        return $this->showContactForm ;
    }

    public function getSeeEmailAcls(): string
    {
        return $this->seeEmailAcls ;
    }

    // change return of this method to keep compatible with php 7.3 (mixed is not managed)
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return array_merge(
            parent::jsonSerialize(),
            [
                'sendMail' => $this->sendMail,
                'showContactForm' => $this->getShowContactForm(),
                'seeEmailAcls' => $this->getSeeEmailAcls()
            ]
        );
    }
}
