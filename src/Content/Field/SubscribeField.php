<?php

namespace YesWiki\Content\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Content\Service\ImportContext;
use YesWiki\Kernel\Service\Mailer;

#[\Field(['inscriptionliste'])]
class SubscribeField extends BazarField
{
    /** @var string the list address subscriptions are sent to */
    protected $mailerEmail;
    /** @var string the entry key holding the subscriber's address */
    protected $emailField;
    /** @var string which list manager the address belongs to, self::MAILER_EZMLM or self::MAILER_SYMPA */
    protected $mailerTool;

    protected const FIELD_MAILER_EMAIL = 1;
    protected const FIELD_EMAIL_FIELD = 3;
    protected const FIELD_MAILER_TOOL = 4;

    public const MAILER_EZMLM = 'ezmlm';
    public const MAILER_SYMPA = 'sympa';

    /**
     * @param array<int|string, mixed> $values
     */
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

        if ($this->getService(ImportContext::class)->isImporting()) {
            if ($value === $subscribeEmail) {
                $this->getService(Mailer::class)->send($entry[$this->emailField] ?? '', $entry['title'] ?? $entry['bf_titre'] ?? '', $subscribeEmail, 'subscribe', 'subscribe', 'subscribe');

                return [$this->propertyName => $value];
            } elseif ($value === $unsubscribeEmail) {
                return [$this->propertyName => $value];
            }

            return [];
        }
        if (isset($value)) {
            $this->getService(Mailer::class)->send($entry[$this->emailField] ?? '', $entry['title'] ?? $entry['bf_titre'] ?? '', $subscribeEmail, 'subscribe', 'subscribe', 'subscribe');

            return [$this->propertyName => $subscribeEmail];
        }
        $this->getService(Mailer::class)->send($entry[$this->emailField] ?? '', $entry['title'] ?? $entry['bf_titre'] ?? '', $unsubscribeEmail, 'unsubscribe', 'unsubscribe', 'unsubscribe');

        return [$this->propertyName => $unsubscribeEmail];
    }

    protected function renderStatic($entry)
    {
        return '';
    }

    /**
     * @param array<string, mixed>|null $entry
     */
    protected function getSubscribeEmail($entry): string
    {
        $subscribeEmail = str_replace('@', '-subscribe@', $this->mailerEmail);

        if (isset($entry[$this->emailField]) && $this->mailerTool == self::MAILER_EZMLM) {
            $subscribeEmail = str_replace('@', '-' . str_replace('@', '=', $entry[$this->emailField]) . '@', $subscribeEmail);
        }

        return $subscribeEmail;
    }

    /**
     * @param array<string, mixed>|null $entry
     */
    protected function getUnsubscribeEmail($entry): string
    {
        $unsubscribeEmail = str_replace('@', '-unsubscribe@', $this->mailerEmail);

        if (isset($entry[$this->emailField]) && $this->mailerTool == self::MAILER_EZMLM) {
            $unsubscribeEmail = str_replace('@', '-' . str_replace('@', '=', $entry[$this->emailField]) . '@', $unsubscribeEmail);
        }

        return $unsubscribeEmail;
    }
}
