<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;

/**
 * @Field({"inscriptionliste"})
 */
class SubscribeField extends BazarField
{
    protected $mailerEmail;
    protected $emailField;
    protected $mailerTool;

    protected const FIELD_MAILER_EMAIL = 1;
    protected const FIELD_EMAIL_FIELD = 3;
    protected const FIELD_MAILER_TOOL = 4;

    public const MAILER_EZMLM = 'ezmlm'; // OVH
    public const MAILER_SYMPA = 'sympa'; // Framaliste

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->mailerEmail = $values[self::FIELD_MAILER_EMAIL];
        $this->emailField = $values[self::FIELD_EMAIL_FIELD] ?? 'bf_mail';
        $this->mailerTool = $values[self::FIELD_MAILER_TOOL];

        $this->propertyName = str_replace(['@', '.'], ['', ''], $this->mailerEmail);

        // We have no default value
        $this->default = null;
    }

    protected function renderInput($entry)
    {
        return $this->render('@bazar/inputs/subscribe.twig', [
            'value' => $this->getValue($entry),
            'subscribeEmail' => $this->getSubscribeEmail($entry),
        ]);
    }

    public function formatValuesBeforeSave($entry)
    {
        $value = $this->getValue($entry);

        $subscribeEmail = $this->getSubscribeEmail($entry);
        $unsubscribeEmail = $this->getUnsubscribeEmail($entry);

        // TODO use the Mailer service
        if (!class_exists('Mail')) {
            include_once 'tools/contact/libs/contact.functions.php';
        }

        // TODO improve import detection
        if (isset($GLOBALS['_BAZAR_']['provenance']) && $GLOBALS['_BAZAR_']['provenance'] == 'import') {
            if ($value === $subscribeEmail) {
                send_mail($entry[$this->emailField], $entry['bf_titre'], $subscribeEmail, 'subscribe', 'subscribe', 'subscribe');

                return [$this->propertyName => $value];
            } elseif ($value === $unsubscribeEmail) {
                // Don't send emails when mass unsubscribing
                return [$this->propertyName => $value];
            }
        } else {
            // TODO fix this, as $value is always equal to $subscribeEmail, even when user did not check the checkbox
            if (isset($value)) {
                send_mail($entry[$this->emailField], $entry['bf_titre'], $subscribeEmail, 'subscribe', 'subscribe', 'subscribe');

                return [$this->propertyName => $subscribeEmail];
            } else {
                send_mail($entry[$this->emailField], $entry['bf_titre'], $unsubscribeEmail, 'unsubscribe', 'unsubscribe', 'unsubscribe');

                return [$this->propertyName => $unsubscribeEmail];
            }
        }
    }

    protected function renderStatic($entry)
    {
        return '';
    }

    protected function getSubscribeEmail($entry)
    {
        // list@provider.com -> list-subscribe@provider.com
        $subscribeEmail = str_replace('@', '-subscribe@', $this->mailerEmail);
        // If the mailing list tool is ezmlm, reformat the email address
        if (isset($entry[$this->emailField]) && $this->mailerTool == self::MAILER_EZMLM) {
            // list@provider.com -> list-subscribe-user=gmail.com@provider.com
            $subscribeEmail = str_replace('@', '-' . str_replace('@', '=', $entry[$this->emailField]) . '@', $subscribeEmail);
        }

        return $subscribeEmail;
    }

    protected function getUnsubscribeEmail($entry)
    {
        // list@provider.com -> list-unsubscribe@provider.com
        $unsubscribeEmail = str_replace('@', '-unsubscribe@', $this->mailerEmail);
        // If the mailing list tool is ezmlm, reformat the email address
        if (isset($entry[$this->emailField]) && $this->mailerTool == self::MAILER_EZMLM) {
            // list@provider.com -> list-unsubscribe-user=gmail.com@provider.com
            $unsubscribeEmail = str_replace('@', '-' . str_replace('@', '=', $entry[$this->emailField]) . '@', $unsubscribeEmail);
        }

        return $unsubscribeEmail;
    }
}
