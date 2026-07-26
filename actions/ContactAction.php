<?php

// ticket 18: relocated from tools/contact/actions/ContactAction.php.

use YesWiki\Core\YesWikiAction;

include_once YESWIKI_SOURCE_DIR . '/src/contact.functions.php';

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
            return '<div class="yw-alert yw-alert--danger"><strong>' . _t('CONTACT_ACTION_CONTACT') . ' :</strong>&nbsp;' . _t('CONTACT_MAIL_REQUIRED') . '</div>';
        }
        // this global is for identifying different contact forms on the same page
        if (isset($GLOBALS['nbactionmail'])) {
            $GLOBALS['nbactionmail']++;
        } else {
            $GLOBALS['nbactionmail'] = 1;
        }
        $options = array_merge($this->arguments, [
            'nbactionmail' => $GLOBALS['nbactionmail'],
            'pageTag' => $this->wiki->GetPageTag(),
        ]);

        $this->wiki->addJavascriptFile('javascripts/contact.js');

        return $this->render('@core/' . $this->arguments['template'], $options);
    }
}
