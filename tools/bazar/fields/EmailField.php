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
        : '@admins';
        $this->seeEmailAcls = str_replace(',', "\n", $this->seeEmailAcls);
        $this->maxChars = '';
    }

    /**
     * Whether saving an entry sends a copy to the address typed in this field.
     */
    public function sendsMail(): bool
    {
        return $this->sendMail;
    }

    public function formatValuesBeforeSave($entry)
    {
        if ($this->sendMail) {
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

        $canBeRead = parent::canRead($entry, $userNameForRendering);

        if ($canBeRead && $this->getShowContactForm()) {
            $tag = $wiki->GetPageTag();
            if ($tag === 'api') {
                $canBeRead = $bazarApiController->isEntryViewFastAccessHelper();
            } elseif ($aclService->check($this->getSeeEmailAcls(), $userNameForRendering, true)) {
                $canBeRead = true;
            } elseif ($tag === $entry['id_fiche']) {
                $canBeRead = in_array($wiki->getMethod(), ['show', 'html', 'edit', 'editiframe', 'mail']);
            } else {
                $canBeRead = false;
            }
        }

        return $canBeRead;
    }

    public function getShowContactForm()
    {
        return $this->showContactForm;
    }

    public function getSeeEmailAcls(): string
    {
        return $this->seeEmailAcls;
    }

    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return array_merge(
            parent::jsonSerialize(),
            [
                'sendMail' => $this->sendMail,
                'showContactForm' => $this->getShowContactForm(),
                'seeEmailAcls' => $this->getSeeEmailAcls(),
            ]
        );
    }
}
