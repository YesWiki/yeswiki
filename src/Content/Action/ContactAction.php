<?php

namespace YesWiki\Content\Action;

// ticket 18: relocated from tools/contact/actions/ContactAction.php.

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\AssetsManager;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\RuntimeConfig;

include_once YESWIKI_SOURCE_DIR . '/src/contact.functions.php';

class ContactAction extends YesWikiAction implements RegisteredAction
{
    /** `{{contact}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'contact';
    }

    public function formatArguments($arg)
    {
        $mailList = $this->formatArray($arg['mail'] ?? null);
        if (!empty($mailList)) {
            $mailList = parseMails($mailList);
        }

        return [
            'correspondance' => $arg['correspondance'] ?? null,
            'mail' => $mailList,
            'entete' => $arg['entete'] ?? $this->getService(RuntimeConfig::class)['yeswiki_name'],
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
            'pageTag' => $this->getService(PageContext::class)->getTag(),
        ]);

        $this->getService(AssetsManager::class)->AddJavascriptFile('javascripts/contact.js');

        return $this->render('@core/' . $this->arguments['template'], $options);
    }
}
