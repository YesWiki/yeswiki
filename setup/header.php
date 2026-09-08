<?php

// stuff

$charset = 'UTF-8';
if (!defined('YW_CHARSET')) {
    define('YW_CHARSET', $charset);
}
$availableBackups = availableBackups($wakkaConfig);
$setupTitle = empty($availableBackups) ? _t('INSTALLATION_OF_YESWIKI') : _t('RESTORATION_OF_YESWIKI');
header("Content-Type: text/html; charset=$charset");
ob_start();
?>
<!doctype html>
<html lang="<?php echo $GLOBALS['prefered_language']; ?>">

<head>
    <meta charset="<?php echo $charset; ?>">
    <title><?php echo $setupTitle; ?></title>
    <link href="<?php echo computeBaseUrl(true); ?>styles/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo computeBaseUrl(true); ?>styles/yeswiki-base.css" rel="stylesheet">
    <link href="<?php echo computeBaseUrl(true); ?>themes/margot/styles/light.css" rel="stylesheet">
</head>

<body>
    <div class="container" style="padding:1em 0;">
        <div class="well">
            <h1><span id="setup-title" data-install-title="<?php echo htmlspecialchars(_t('INSTALLATION_OF_YESWIKI'), ENT_COMPAT, $charset); ?>" data-restore-title="<?php echo htmlspecialchars(_t('RESTORATION_OF_YESWIKI'), ENT_COMPAT, $charset); ?>"><?php echo $setupTitle; ?></span>
                <?php
                $alert = '';
if ($wakkaConfig['yeswiki_version'] || $wakkaConfig['wakka_version'] || $wakkaConfig['wikini_version']) {
    if ($wakkaConfig['yeswiki_version']) {
        $prog = 'YesWiki';
        $config = $wakkaConfig['yeswiki_version'];
    } elseif ($wakkaConfig['wikini_version']) {
        $prog = 'Wikini';
        $config = $wakkaConfig['wikini_version'];
    } else {
        $prog = 'Wikini';
        $config = $wakkaConfig['wakka_version'];
    }
    $alert = '<div class="alert alert-info">' . _t('YOUR_SYSTEM') . ' ' . $prog . ' ' . _t('EXISTENT_SYSTEM_RECOGNISED_AS_VERSION') . ' ' . $config .
        '. ' . _t('YOU_ARE_UPDATING_YESWIKI_TO_VERSION') . ' ' . YESWIKI_VERSION .
        '. ' . _t('CHECK_YOUR_CONFIG_INFORMATION_BELOW') . ".</div>\n";
    $wiki = new Wiki($wakkaConfig);
} else {
    echo '<small>' . ucfirst(YESWIKI_VERSION) . ' ' . YESWIKI_RELEASE . '</small>' . "\n";
    $wiki = null;
}
?>
            </h1>
            <?php echo $alert; ?>
        </div>