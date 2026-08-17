<?php

namespace YesWiki\Contact\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\AssetRegistry;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\PerformableArguments;

/**
 * `{{listsubscription}}` -- converted from the procedural actions/listsubscription.php by ticket 06.
 */
class ListsubscriptionAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'listsubscription';
    }

    public function run(): string
    {
        ob_start();
        try {
            $this->emit();
        } catch (\Throwable $t) {
            $this->output .= (string)ob_get_clean();

            throw $t;
        }

        return (string)ob_get_clean();
    }

    private function emit(): void
    {
        if ($user = $this->getService(AuthenticationService::class)->getLoggedUser()) {
            if ($user['email'] != '') {
                $list = $this->getService(PerformableArguments::class)->get('list');
                if (!empty($list)) {
                    $output = '<div class="note"></div>
        				<form id="ajax-abonne-form" class="form-mail" data-page-tag="' . htmlspecialchars($this->getService(PageContext::class)->getTag()) . '">
        					' . $list . ' : ' . "\n" .
                        '</form>' . "\n";
                    $this->getService(AssetRegistry::class)->addJsFile('javascripts/contact.js');
                } else {
                    echo '<div class="yw-alert yw-alert--danger"><strong>' . _t('CONTACT_ACTION_LISTSUBSCRIPTION') . '</strong> : ' . _t('CONTACT_LIST_REQUIRED') . '.</div>';
                }
            } else {
                echo '<div class="yw-alert yw-alert--danger"><strong>' . _t('CONTACT_ACTION_LISTSUBSCRIPTION') . '</strong> : ' . _t('CONTACT_USER_NO_EMAIL') . '</div>';
            }
        } else {
            echo '<div class="yw-alert yw-alert--danger"><strong>' . _t('CONTACT_ACTION_LISTSUBSCRIPTION') . '</strong> : ' . _t('CONTACT_USER_NOT_LOGGED_IN') . '</div>';
        }
    }
}
