<?php

namespace YesWiki\Content\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Content\Service\EntryFastAccessService;
use YesWiki\Identity\Service\AclService;

#[\Field(['champs_mail'])]
class EmailField extends BazarField
{
    use ContributesNoSearchableText;

    /** @var string */
    protected $seeEmailAcls;
    /** @var bool */
    protected $sendMail;
    /** @var bool */
    protected $showContactForm;

    protected const FIELD_SHOW_CONTACT_FORM = 6;
    protected const FIELD_SEE_MAIL_ACLS = 4;
    protected const FIELD_SEND_EMAIL = 9;

    /**
     * @param array<int|string, mixed> $values
     */
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
            $this->getService(\YesWiki\Kernel\Service\AssetRegistry::class)->addJsFile('javascripts/contact.js');
        }

        return $this->render('@core/fields/email.twig', [
            'value' => $value,

            'pageTag' => (string)($entry['tag'] ?? ''),
        ]);
    }

    public function canRead($entry, ?string $userNameForRendering = null)
    {
        $aclService = $this->getService(AclService::class);
        $entryFastAccessService = $this->getService(EntryFastAccessService::class);

        $canBeRead = parent::canRead($entry, $userNameForRendering);

        if ($canBeRead && $this->getShowContactForm()) {
            $tag = $this->getService(\YesWiki\Kernel\Service\PageContext::class)->getTag();
            if ($tag === 'api') {
                $canBeRead = $entryFastAccessService->isFastAccessRequest($this->getRequest());
            } elseif ($aclService->check($this->getSeeEmailAcls(), $userNameForRendering, true)) {
                $canBeRead = true;
            } elseif ($tag === ($entry['tag'] ?? null)) {
                $canBeRead = in_array($this->getService(\YesWiki\Kernel\Service\PageContext::class)->getMethod(), ['show', 'html', 'edit', 'editiframe', 'mail']);
            } else {
                $canBeRead = false;
            }
        }

        return $canBeRead;
    }

    public function getShowContactForm(): bool
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
