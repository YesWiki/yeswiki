<?php
/*
header.php
Copyright (c) 2002, Hendrik Mans <hendrik@mans.de>
Copyright 2002, 2003 David DELON
Copyright 2002  Patrick PAUL
Copyright 2006 Charles NEPOTE
All rights reserved.
Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions
are met:
1. Redistributions of source code must retain the above copyright
notice, this list of conditions and the following disclaimer.
2. Redistributions in binary form must reproduce the above copyright
notice, this list of conditions and the following disclaimer in the
documentation and/or other materials provided with the distribution.
3. The name of the author may not be used to endorse or promote products
derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE AUTHOR ``AS IS'' AND ANY EXPRESS OR
IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES
OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT,
INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/
// stuff
if (!defined('WIKINI_VERSION')) {
    die("acc&egrave;s direct interdit");
}

$charset='UTF-8';
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
  <link href="<?php echo computeBaseUrl(true); ?>themes/margot/styles/margot.css" rel="stylesheet">
</head>

<body>
  <div class="container" style="padding:1em 0;">
  <div class="well">
    <h1><?php echo _t('INSTALLATION_OF_YESWIKI'); ?>
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
            $alert = '<div class="alert alert-info">'._t('YOUR_SYSTEM').' '.$prog.' '._t('EXISTENT_SYSTEM_RECOGNISED_AS_VERSION').' '.$config.
                '. '._t('YOU_ARE_UPDATING_YESWIKI_TO_VERSION').' '.YESWIKI_VERSION.
                '. '._t('CHECK_YOUR_CONFIG_INFORMATION_BELOW').".</div>\n";
            $wiki = new Wiki($wakkaConfig);
        } else {
            echo '<small>'.ucfirst(YESWIKI_VERSION).' '.YESWIKI_RELEASE.'</small>'."\n";
            $wiki = null;
        }
        ?>
    </h1>
    <?php echo $alert; ?>
  </div>
