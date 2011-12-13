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
if (!defined('WIKINI_VERSION'))
{
	die ("acc&egrave;s direct interdit");
}

/**
 * Communique le résultat d'un test :
 * -- affiche OK si elle l'est
 * -- affiche un message d'erreur dans le cas contraire
 * 
 * @param string $text Label du test
 * @param boolean $condition Résultat de la condition testée
 * @param string $errortext Message en cas d'erreur
 * @param string $stopOnError Si positionnée à 1 (par défaut), termine le
 *               script si la condition n'est pas vérifiée 
 * @return int 0 si la condition est vraie et 1 si elle est fausse
 */
function test($text, $condition, $errorText = "", $stopOnError = 1)
{
	echo "$text ";
	if ($condition)
	{
		echo "<span class=\"ok\">OK</span><br />\n";
		return 0;
	}
	else
	{
		echo "<span class=\"failed\">ECHEC</span>";
		if ($errorText) echo ": ",$errorText;
		echo "<br />\n";
		if ($stopOnError)
		{
			echo "Fin de l'installation.<br />\n";
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

$charset='iso-8859-1';
header("Content-Type: text/html; charset=$charset");
ob_start();
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
   "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<html xmlns="http://www.w3.org/1999/xhtml" lang="fr" xml:lang="fr">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=<?php echo $charset; ?>"/>
  <title>Installation de WikiNi</title>
  <style type="text/css">
    P, BODY, TD, LI, INPUT, SELECT, TEXTAREA { font-family: Verdana; font-size: 13px; }
    INPUT { color: #880000; }
    .ok { color: #008800; font-weight: bold; }
    .failed { color: #880000; font-weight: bold; }
    A { color: #0000FF; }
  </style>
</head>

<body>
