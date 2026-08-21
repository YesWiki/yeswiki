<?php

namespace YesWiki\Contact\Service;

use Psr\Container\ContainerInterface;
use YesWiki\Identity\Service\GroupManager;
use YesWiki\Identity\Service\UserManager;

/**
 * Who a `{{contact}}`, `{{subscribe}}` or `{{unsubscribe}}` form writes to, and whether it may send.
 *
 * The address is not in the form: it is in the page body, on the action call that drew the form,
 * which is why the Nth form on a page needs the Nth call (see MailFormCounter). A recipient list
 * may also name a group rather than an address, and a group has to be expanded to the addresses
 * of its members.
 *
 * Was `FindMailFromWikiPage()`, `parseMails()`, `ValidateEmail()` and `check_parameters_mail()`
 * in `Contact/contact.functions.php` (ticket 50).
 */
class MailForm
{
    public function __construct(private readonly ContainerInterface $services)
    {
    }

    /** The address the $nth mail form on this page writes to, or '' when the page names none. */
    public function addressOnPage(?string $pageBody, int $nth): string
    {
        preg_match_all('/{{(contact|subscribe|unsubscribe).*mail=\"(.*)\".*}}/U', (string)$pageBody, $matches);

        return $matches[2][$nth - 1] ?? '';
    }

    /**
     * $addresses with every `@group` expanded to the addresses of its members.
     *
     * @param array<mixed> $addresses
     *
     * @return array<string, string> address => address, so duplicates collapse
     */
    public function resolveRecipients(array $addresses): array
    {
        $mailList = [];
        $groupManager = $this->services->get(GroupManager::class);
        $userManager = $this->services->get(UserManager::class);
        foreach ($addresses as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $mailList[$email] = $email;
            } elseif (preg_match('/^@[a-zA-Z0-9]+/m', $email)) {
                $group = substr($email, 1);
                if ($groupManager->groupExists($group)) {
                    $users = $groupManager->getMembers($group);
                    foreach ($users as $user) {
                        $em = $userManager->getOneByName($user)->getEmail();
                        if (filter_var($em, FILTER_VALIDATE_EMAIL)) {
                            $mailList[$em] = $em;
                        }
                    }
                }
            }
        }

        return $mailList;
    }

    /**
     * What is wrong with this submission, as the message and severity a form shows.
     *
     * @return array{message: string, class: string}
     */
    public function problemsWith(string $type, mixed $mailSender, mixed $nameSender, mixed $mailReceiver, string $subject, string $messageBody): array
    {
        $message['message'] = '';
        $message['class'] = 'danger';

        if ($type == 'contact' && !$nameSender) {
            $message['message'] .= _t('CONTACT_ENTER_NAME') . '<br />';
        }

        if (!$mailSender) {
            $message['message'] .= _t('CONTACT_ENTER_SENDER_MAIL') . '<br />';
        }
        if ($mailSender && !filter_var($mailSender, FILTER_VALIDATE_EMAIL)) {
            $message['message'] .= _t('CONTACT_SENDER_MAIL_INVALID') . '<br />';
        }

        if (!$mailReceiver) {
            $message['message'] .= _t('CONTACT_ENTER_RECEIVER_MAIL') . '<br />';
        }
        if (is_string($mailReceiver)) {
            $mailReceiver = [$mailReceiver];
        }
        if (!is_array($mailReceiver) || count($mailReceiver) < 1) {
            $message['message'] .= _t('CONTACT_RECEIVER_MAIL_INVALID') . '<br />';
        }

        if ($type != 'subscribe' && $type != 'unsubscribe' && (!$messageBody || strlen($messageBody) < 10)) {
            $message['message'] .= _t('CONTACT_ENTER_MESSAGE') . '<br />';
        }

        if ($message['message'] == '') {
            $message['class'] = 'success';
        }

        return $message;
    }
}
