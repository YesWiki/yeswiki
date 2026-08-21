<?php

namespace YesWiki\Contact\Api;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use YesWiki\Contact\Service\MailForm;
use YesWiki\Content\Controller\EntryController;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\YesWikiController;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Service\Mailer;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\MarkdownFormatterService;
use YesWiki\Render\Service\ThemeManager;

class ContactApiController extends YesWikiController
{
    /**
     * Consolidated contact-mail-sending route (ticket 18, replaces the ajax branch of tools/contact's handlers/page/mail.php page-handler).
     */
    #[Route('/api/contact/mail', methods: ['POST'], options: ['acl' => ['public']])]
    public function sendContactMail(Request $request)
    {
        $pageTag = (string)$request->request->get('pageTag', '');
        if (empty($pageTag)) {
            return new ApiResponse(['type' => 'danger', 'message' => "'pageTag' should not be empty"], Response::HTTP_BAD_REQUEST);
        }

        $aclService = $this->getService(AclService::class);
        $pageManager = $this->getService(PageManager::class);
        $entryManager = $this->getService(EntryManager::class);

        $field = (string)$request->request->get('field', '');
        $infomsg = '';
        $hasReadAccess = true;

        if (!empty($field)) {
            $hasReadAccess = $aclService->hasAccess('read', $pageTag);
            $mailReceiver = [];
            if ($hasReadAccess) {
                $val = $entryManager->getOne($pageTag);
                if (is_array($val) && isset($val[$field])) {
                    $mailReceiver[] = $val[$field];
                }
                $form = $this->getService(FormManager::class)->getOne($val['form_id'] ?? null) ?? [];
                $mailSenderForMsg = (string)$request->request->get('email', '');
                $infomsg .= '<em>' . _t('CONTACT_THIS_MESSAGE') . ' « <a href="' . $this->getService(UrlFormatter::class)->href('', $val['tag'] ?? '') . '">'
                    . ($val['title'] ?? $val['bf_titre'] ?? $pageTag) . '</a> » ' . _t('CONTACT_FROM_FORM') . ' « ' . ($form['label'] ?? '') . ' » '
                    . _t('CONTACT_FROM_WEBSITE') . ' « ' . $this->getService(\YesWiki\Kernel\Service\RuntimeConfig::class)['yeswiki_name'] . ' ». ' .
                    ($mailSenderForMsg ? _t('CONTACT_REPLY') . ' <strong>' . $mailSenderForMsg . '</strong> '
                        . _t('CONTACT_REPLY2') : '') . '.</em><br><br>';
            }
        } else {
            $mailReceiver = trim((string)$request->request->get('mail', '')) ?: false;
        }

        $page = $pageManager->getOne($pageTag, null, true, true);

        if (!$mailReceiver) {
            $hasReadAccess = $aclService->hasAccess('read', $pageTag);
            if ($hasReadAccess) {
                $themeManager = $this->getService(ThemeManager::class);
                $chemin = 'themes/' . $themeManager->getFavoriteTheme() . '/squelettes/' . $themeManager->getFavoriteSquelette();

                $pageContent = PageBody::content($page['body'] ?? []);
                $fileContent = file_exists($chemin) ? (string)file_get_contents($chemin) : '';
                $body = preg_replace('/\{\{\s*page_content[^}]*\}\}/', $pageContent, $fileContent, 1, $replaced);
                if (!$replaced) {
                    $body = $fileContent . "\n" . $pageContent;
                }
                $nbActionMail = $request->request->get('nbactionmail');
                $mailReceiver = !empty($nbActionMail) ? $this->getService(MailForm::class)->addressOnPage($body, (int)$nbActionMail) : false;
                if ($mailReceiver) {
                    $mailList = array_map('trim', explode(',', $mailReceiver));
                    $mailReceiver = $this->getService(MailForm::class)->resolveRecipients($mailList);
                }
            }
        }

        $mailSender = trim((string)$request->request->get('email', ''));
        $nameSender = (string)$request->request->get('name', '');
        $type = (string)$request->request->get('type', '');

        $subject = '';
        $messageTxt = '';
        $messageHtml = '';

        if ($type == 'mail') {
            $hasReadAccess = $aclService->hasAccess('read', $pageTag);
            if ($hasReadAccess) {
                $subject = (string)$request->request->get('subject', '');
                if ($entryManager->isEntry($pageTag)) {
                    $renderedPage = $this->getService(EntryController::class)->view($pageTag);
                } else {
                    $renderedPage = $this->getService(MarkdownFormatterService::class)->format(PageBody::content($page['body'] ?? []));
                }
                $messageHtml = html_entity_decode($renderedPage);
                $messageTxt = strip_tags($messageHtml);
            }
        } elseif ($type == 'subscribe' || $type == 'unsubscribe') {
            $messageHtml = $messageTxt = 'Mailinglist : ' . $type;
        } else {
            $subjectprefix = (string)$request->request->get('subjectprefix', '');
            $subject = (!empty($subjectprefix) ? '[' . trim($subjectprefix) . '] ' : '') . (string)$request->request->get('subject', '');
            $rawMessage = (string)$request->request->get('message', '');
            $messageTxt = trim(strip_tags($rawMessage));
            $messageHtml = trim(nl2br(str_replace('€', '&euro;', htmlspecialchars($rawMessage, ENT_COMPAT, YW_CHARSET))));
        }

        if ($hasReadAccess) {
            $message = $this->getService(MailForm::class)->problemsWith($type, $mailSender, $nameSender, $mailReceiver, $subject, $messageTxt);
            if ($type != 'subscribe' && $type != 'unsubscribe' && !empty($infomsg)) {
                $messageTxt = strip_tags($infomsg) . '\n\n' . $messageTxt;
                $messageHtml = $infomsg . $messageHtml;
            }
        } else {
            $message = [
                'class' => 'danger',
                'message' => _t('CONTACT_MESSAGE_NOT_SENT') . ' :<br />' . _t('LOGIN_NOT_AUTORIZED'),
            ];
        }

        if ($message['class'] == 'success') {
            $mailingList = (string)$request->request->get('mailinglist', '');
            if (!empty($mailingList)) {
                // a mailing list has one address; the page-body form yields a string and
                // the recipient list an array, and only the second has anything to pop
                $mailReceiver = is_array($mailReceiver) ? array_pop($mailReceiver) : $mailReceiver;
                if ($mailingList == 'ezmlm') {
                    $mailReceiver = str_replace('@', '-' . str_replace('@', '=', $mailSender) . '@', $mailReceiver);
                } elseif ($mailingList == 'sympa') {
                    $tabmail = explode('@', $mailReceiver);
                    $listname = $tabmail[0];
                    $listdomain = $tabmail[1];
                    $mailReceiver = 'sympa@' . $listdomain;
                    if ($type == 'subscribe') {
                        $subject = 'subscribe ' . $listname;
                    } elseif ($type == 'unsubscribe') {
                        $subject = 'unsubscribe ' . $listname;
                    }
                }
                if (empty($messageTxt)) {
                    $messageTxt = $messageHtml = 'dummy message';
                }
            }
            if ($this->getService(Mailer::class)->send($mailSender, $nameSender, $mailReceiver, $subject, $messageTxt, $messageHtml)) {
                if (empty($type) || $type == 'contact' || $type == 'mail') {
                    $message['message'] = _t('CONTACT_MESSAGE_SUCCESSFULLY_SENT');
                } elseif ($type == 'subscribe') {
                    $message['message'] = _t('CONTACT_SUBSCRIBE_ORDER_SENT');
                } elseif ($type == 'unsubscribe') {
                    $message['message'] = _t('CONTACT_UNSUBSCRIBE_ORDER_SENT');
                }
            } else {
                $message['class'] = 'danger';
                $message['message'] = _t('CONTACT_MESSAGE_NOT_SENT');
            }
        }

        return new ApiResponse(['type' => $message['class'], 'message' => $message['message']], Response::HTTP_OK);
    }

    /** The contact form, as an HTML fragment (ticket 35, was `/PageName/mail`). */
    #[Route('/api/contact/form', methods: ['GET'], options: ['acl' => ['public']])]
    public function contactForm(Request $request): Response
    {
        $pageTag = (string)$request->query->get('pageTag', '');
        if ($pageTag === '') {
            return new ApiResponse(['type' => 'danger', 'message' => "'pageTag' should not be empty"], Response::HTTP_BAD_REQUEST);
        }

        $aclService = $this->getService(AclService::class);
        $hasReadAccess = $aclService->hasAccess('read', $pageTag);
        $isLoggedIn = !empty($this->getService(\YesWiki\Identity\Service\AuthenticationService::class)->getLoggedUser());

        if ($hasReadAccess && ($isLoggedIn || $request->query->get('field', '') !== '')) {
            $this->getService(\YesWiki\Kernel\Service\AssetRegistry::class)->addJsFile('javascripts/contact.js');
        }

        $html = $this->getService(\YesWiki\Render\Service\TemplateEngine::class)->renderSafely(
            '@core/contact/mail-form.twig',
            [
                'pageTag' => $pageTag,
                'field' => (string)$request->query->get('field', ''),
                'hasReadAccess' => $hasReadAccess,
                'isLoggedIn' => $isLoggedIn,
            ]
        );

        return new Response($html, Response::HTTP_OK, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
