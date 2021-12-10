<?php
// verify that passphrase was set, and that GET parameter key is egal to passphrase
if (!empty($this->config['contact_passphrase']) && isset($_GET['key']) && $_GET['key'] === $this->config['contact_passphrase']) {
    echo 'Clé valide !<br>';
    require_once 'tools/contact/libs/contact.functions.php';
    if (isset($_GET['period']) && in_array($_GET['period'], ['day', 'week', 'month'], true)) {
        echo 'On envoie les mails pour la période '.htmlspecialchars($_GET['period']).' !<br>';
        $subject = (isset($_GET['subject'])) ? $_GET['subject'] : '';
        sendEmailsToSubscribers($_GET['period'], $subject);
    } else {
        echo 'La période n\'a pas été renseignée ou n\'a pas de valeur standard (month, week ou day).<br>';
    }
}
