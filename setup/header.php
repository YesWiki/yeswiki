<?php

// stuff
if (!defined('WIKINI_VERSION')) {
    die("acc&egrave;s direct interdit");
}

$charset = 'UTF-8';
if (!defined('YW_CHARSET')) {
    define('YW_CHARSET', $charset);
}
header("Content-Type: text/html; charset=$charset");
ob_start();
?>
<!doctype html>
<html lang="<?php echo $GLOBALS['prefered_language']; ?>">

<head>
    <meta charset="<?php echo $charset; ?>">
    <title><?php echo _t('INSTALLATION_OF_YESWIKI'); ?></title>
    <link href="<?php echo computeBaseUrl(true); ?>styles/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo computeBaseUrl(true); ?>styles/yeswiki-base.css" rel="stylesheet">
    <link href="<?php echo computeBaseUrl(true); ?>themes/margot/styles/light.css" rel="stylesheet">
</head>

<body>
  <div class="container" style="padding:1em 0;">
  <div class="well">
    <h1><?php echo _t('INSTALLATION_OF_YESWIKI'); ?>
        <?php echo '<small>'.ucfirst(YESWIKI_VERSION).' '.YESWIKI_RELEASE.'</small>'."\n"; ?>
    </h1>
  </div>
