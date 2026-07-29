<?php

namespace YesWiki\Content\Handler;

use YesWiki\Core\YesWikiHandler;
use YesWiki\Kernel\Performable\RegisteredHandler;
use YesWiki\Kernel\Service\RuntimeConfig;

/**
 * `/PageName/sendmail` -- converted from the procedural handlers/page/sendmail.php by ticket 06.
 */
class SendmailHandler extends YesWikiHandler implements RegisteredHandler
{
    public static function performableName(): string
    {
        return 'sendmail';
    }

    public function run(): string
    {
        ob_start();
        try {
            $this->emit();
        } catch (\Throwable $t) {
            // handlers commonly end in exit()/redirect, which throw; keep what was already
            // printed and close the buffer either way (see ticket 06)
            $this->output .= (string)ob_get_clean();

            throw $t;
        }

        return (string)ob_get_clean();
    }

    private function emit(): void
    {
        // ticket 18: relocated from tools/contact/handlers/page/sendmail.php.
        // cron-triggered periodic mailing-list digest sender (?page/sendmail&key=...&period=...).

        // verify that passphrase was set, and that GET parameter key is egal to passphrase
        if (!empty($this->getService(RuntimeConfig::class)['contact_passphrase']) && isset($_GET['key']) && $_GET['key'] === $this->getService(RuntimeConfig::class)['contact_passphrase']) {
            echo 'Clé valide !<br>';
            include_once YESWIKI_SOURCE_DIR . '/src/contact.functions.php';
            if (isset($_GET['period']) && in_array($_GET['period'], ['day', 'week', 'month'], true)) {
                echo _t('CONTACT_SENDMAIL_INFO') . ' ' . htmlspecialchars($_GET['period']) . ' !<br>';
                $subject = (isset($_GET['subject'])) ? $_GET['subject'] : '';
                sendEmailsToSubscribers($_GET['period'], $subject);
            } else {
                echo _t('CONTACT_SENDMAIL_ERROR') . '<br>';
            }
        }
    }
}
