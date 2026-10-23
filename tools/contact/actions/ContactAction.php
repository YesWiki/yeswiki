<?php

namespace YesWiki\Contact;

use YesWiki\Core\Service\BotGuard;
use YesWiki\Core\YesWikiAction;

include_once 'tools/contact/libs/contact.functions.php';

class ContactAction extends YesWikiAction
{
    public function formatArguments($arg)
    {
        $mailList = $this->formatArray($arg['mail'] ?? null);
        if (!empty($mailList)) {
            $mailList = parseMails($mailList);
        }

        return [
            'correspondance' => $arg['correspondance'] ?? null,
            'mail' => $mailList,
            'entete' => $arg['entete'] ?? $this->wiki->config['wakka_name'],
            'template' => $this->safeTemplate($arg['template'] ?? 'complete-contact-form.twig'),
            'class' => (!empty($arg['class']) ? 'form-contact ' . $arg['class'] : 'form-contact'),
        ];
    }

    private function safeTemplate($template): string
    {
        $template = basename((string)$template);

        return str_ends_with($template, '.twig') ? $template : 'complete-contact-form.twig';
    }

    public function run()
    {
        if (empty($this->arguments['mail'])) {
            return '<div class="alert alert-danger"><strong>' . _t('CONTACT_ACTION_CONTACT') . ' :</strong>&nbsp;' . _t('CONTACT_MAIL_REQUIRED') . '</div>';
        }
        if (isset($GLOBALS['nbactionmail'])) {
            $GLOBALS['nbactionmail']++;
        } else {
            $GLOBALS['nbactionmail'] = 1;
        }
        $botGuard = $this->getService(BotGuard::class);
        $botGuardFields = $botGuard->fields();
        $options = array_merge($this->arguments, [
            'nbactionmail' => $GLOBALS['nbactionmail'],
            'mailerurl' => $this->wiki->href('mail'),
            'botGuardFields' => $botGuardFields,
        ]);

        $this->wiki->addJavascriptFile('tools/contact/libs/contact.js');

        return $botGuard->placeFields($this->render('@contact/' . $this->arguments['template'], $options), $botGuardFields);
    }
}
