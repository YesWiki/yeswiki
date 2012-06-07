<?php
/*
qrcode.php
Copyright 2011  Francois Labastie
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
error_reporting(E_ALL);

// Lecture des parametres de l'action
$text = $this->GetParameter('text');
$correction = $this->GetParameter('correction');
if(empty($correction)){
	$correction = QR_CORRECTION;
}
// si pas de texte, on affiche une erreur
if (empty($text)) {
	echo ("<div class=\"error_box\">ERREUR action qrcode : pas de texte saisi (parametre text=\"\" manquant).</div>");
}
else { 
	include_once 'tools/qrcode/libs/qrlib.php';
	
	$cache_image = 'cache'.DIRECTORY_SEPARATOR.'qrcode-'.$this->getPageTag().'-'.md5($text).'.png';
	QRcode::png($text, $cache_image, $correction, 4, 2);
	echo '<img src="'.$cache_image.'" alt="'.$text.'" />'."\n";
}

?>
