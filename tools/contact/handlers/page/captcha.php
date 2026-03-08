<?php

use AltchaOrg\Altcha\Hasher\Hasher;
use AltchaOrg\Altcha\Altcha;

$altchaHMACKey = $GLOBALS['wiki']->config['captchhmacKey'];

try {
    $hasher = new Hasher();
    $altcha = new Altcha($altchaHMACKey, $hasher);
    $challenge = $altcha->createChallenge();
    echo json_encode($challenge);
} catch (Exception $e) {
    echo 'Failed to create challenge (HMACKey=' . $altchaHMACKey . ') : ' . $e->getMessage();
}
