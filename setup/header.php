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

/**
 * Communique le resultat d'un test :
 * -- affiche OK si elle l'est
 * -- affiche un message d'erreur dans le cas contraire
 *
 * @param string $text Label du test
 * @param boolean $condition Résultat de la condition testée
 * @param string $errortext Message en cas d'erreur
 * @param string $stopOnError Si positionnée é 1 (par défaut), termine le
 *               script si la condition n'est pas vérifiée
 * @return int 0 si la condition est vraie et 1 si elle est fausse
 */
function test($text, $condition, $errorText = "", $stopOnError = 1)
{
    echo "$text ";
    if ($condition) {
        echo "<span class=\"ok\">"._t('OK')."</span><br />\n";
        return 0;
    } else {
        echo "<span class=\"failed\">"._t('FAIL')."</span>";
        if ($errorText) {
            echo ": ",$errorText;
        }
        echo "<br />\n";
        if ($stopOnError) {
            echo "<br />\n<div class=\"alert alert-danger alert-error\"><strong>"._t('END_OF_INSTALLATION_BECAUSE_OF_ERRORS').".</strong></div>\n";
            echo "<script>
                document.write('<div class=\"form-actions\"><a class=\"btn btn-large btn-primary revenir\" href=\"javascript:history.go(-1);\">"._t('GO_BACK')."</a></div>');
                </script>\n";
            echo "</body>\n</html>\n";
            exit;
        }
        return 1;
    }
}

function myLocation()
{
    list($url, ) = explode("?", $_SERVER["REQUEST_URI"]);
    return $url;
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
  <link href="tools/templates/presentation/styles/bootstrap.min.css" rel="stylesheet">
  <link href="tools/templates/presentation/styles/install.css" rel="stylesheet">
</head>

<body class="container">
