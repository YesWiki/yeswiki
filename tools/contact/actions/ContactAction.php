<?php

namespace YesWiki\Contact;

use YesWiki\Core\YesWikiAction;

include_once YESWIKI_SOURCE_DIR . '/tools/contact/libs/contact.functions.php';

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
            'entete' => $arg['entete'] ?? $this->wiki->config['yeswiki_name'],
            'template' => $arg['template'] ?? 'complete-contact-form.twig',
            'class' => (!empty($arg['class']) ? 'form-contact ' . $arg['class'] : 'form-contact'),
        ];
    }

    public function run()
    {
        if (empty($this->arguments['mail'])) {
            return '<div class="alert alert-danger"><strong>' . _t('CONTACT_ACTION_CONTACT') . ' :</strong>&nbsp;' . _t('CONTACT_MAIL_REQUIRED') . '</div>';
        }
        // this global is for identifying different contact forms on the same page
        if (isset($GLOBALS['nbactionmail'])) {
            $GLOBALS['nbactionmail']++;
        } else {
            $GLOBALS['nbactionmail'] = 1;
        }
        $options = array_merge($this->arguments, [
            'nbactionmail' => $GLOBALS['nbactionmail'],
            'mailerurl' => $this->wiki->href('mail'),
        ]);

        $this->wiki->addJavascriptFile('tools/contact/libs/contact.js');

        return $this->render('@contact/' . $this->arguments['template'], $options);
    }
}
