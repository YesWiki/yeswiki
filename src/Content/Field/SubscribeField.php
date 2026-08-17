<?php

namespace YesWiki\Content\Field;

use Psr\Container\ContainerInterface;

#[\Field(['inscriptionliste'])]
class SubscribeField extends BazarField
{
    protected $mailerEmail;
    protected $emailField;
    protected $mailerTool;

    protected const FIELD_MAILER_EMAIL = 1;
    protected const FIELD_EMAIL_FIELD = 3;
    protected const FIELD_MAILER_TOOL = 4;

    public const MAILER_EZMLM = 'ezmlm';
    public const MAILER_SYMPA = 'sympa';

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->mailerEmail = $values[self::FIELD_MAILER_EMAIL];
        $this->emailField = $values[self::FIELD_EMAIL_FIELD] ?? 'bf_mail';
        $this->mailerTool = $values[self::FIELD_MAILER_TOOL];

        $this->propertyName = str_replace(['@', '.'], ['', ''], $this->mailerEmail);

        $this->default = null;
    }

    protected function renderInput($entry)
    {
        return $this->render('@core/inputs/subscribe.twig', [
            'value' => $this->getValue($entry),
            'subscribeEmail' => $this->getSubscribeEmail($entry),
        ]);
    }

    public function formatValuesBeforeSave($entry)
    {
        $value = $this->getValue($entry);

        $subscribeEmail = $this->getSubscribeEmail($entry);
        $unsubscribeEmail = $this->getUnsubscribeEmail($entry);

        if (!class_exists('Mail')) {
            include_once YESWIKI_SOURCE_DIR . '/src/Contact/contact.functions.php';
        }

        if (isset($GLOBALS['_BAZAR_']['provenance']) && $GLOBALS['_BAZAR_']['provenance'] == 'import') {
            if ($value === $subscribeEmail) {
                send_mail($entry[$this->emailField], $entry['title'] ?? $entry['bf_titre'] ?? '', $subscribeEmail, 'subscribe', 'subscribe', 'subscribe');

                return [$this->propertyName => $value];
            } elseif ($value === $unsubscribeEmail) {
                return [$this->propertyName => $value];
            }
        } else {
            if (isset($value)) {
                send_mail($entry[$this->emailField], $entry['title'] ?? $entry['bf_titre'] ?? '', $subscribeEmail, 'subscribe', 'subscribe', 'subscribe');

                return [$this->propertyName => $subscribeEmail];
            }
            send_mail($entry[$this->emailField], $entry['title'] ?? $entry['bf_titre'] ?? '', $unsubscribeEmail, 'unsubscribe', 'unsubscribe', 'unsubscribe');

            return [$this->propertyName => $unsubscribeEmail];
        }
    }

    protected function renderStatic($entry)
    {
        return '';
    }

    protected function getSubscribeEmail($entry)
    {
        $subscribeEmail = str_replace('@', '-subscribe@', $this->mailerEmail);

        if (isset($entry[$this->emailField]) && $this->mailerTool == self::MAILER_EZMLM) {
            $subscribeEmail = str_replace('@', '-' . str_replace('@', '=', $entry[$this->emailField]) . '@', $subscribeEmail);
        }

        return $subscribeEmail;
    }

    protected function getUnsubscribeEmail($entry)
    {
        $unsubscribeEmail = str_replace('@', '-unsubscribe@', $this->mailerEmail);

        if (isset($entry[$this->emailField]) && $this->mailerTool == self::MAILER_EZMLM) {
            $unsubscribeEmail = str_replace('@', '-' . str_replace('@', '=', $entry[$this->emailField]) . '@', $unsubscribeEmail);
        }

        return $unsubscribeEmail;
    }
}
