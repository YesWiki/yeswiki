<?php

use YesWiki\Contact\Service\MailSubscriptions;

if (!empty($this->config['contact_passphrase']) && isset($_GET['key']) && $_GET['key'] === $this->config['contact_passphrase']) {
    echo 'Clé valide !<br>';
    if (isset($_GET['period']) && in_array($_GET['period'], MailSubscriptions::PERIODS, true)) {
        echo _t('CONTACT_SENDMAIL_INFO') . ' ' . htmlspecialchars($_GET['period']) . ' !<br>';
        $this->services->get(MailSubscriptions::class)->send($_GET['period'], $_GET['subject'] ?? '');
    } else {
        echo _t('CONTACT_SENDMAIL_ERROR') . '<br>';
    }
}
