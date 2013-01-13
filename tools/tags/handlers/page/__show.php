<?php
/*
$Id: show__.php,v 1.1 2011-12-19 09:51:10 mrflos Exp $
Copyright (c) 2002, Florian Schmitt <florian@outils-reseaux.org>
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

// Verification de securite
if (!defined("WIKINI_VERSION"))
{
	die ("acc&egrave;s direct interdit");
}
$tag = $this->GetPageTag();

// on ouvre les commentaires si la configuration generale ou de la page le demande
$pageouverte = $this->GetTripleValue($tag,'http://outils-reseaux.org/_vocabulary/comments', '', '');

$GLOBALS["open_comments"][$tag] = ((COMMENTAIRES_OUVERTS_PAR_DEFAUT && $pageouverte!='0' ) || (!COMMENTAIRES_OUVERTS_PAR_DEFAUT && $pageouverte=='1')) && $this->page["comment_on"] == '';

$_SESSION["show_comments"][$tag] = false;
unset($_REQUEST["show_comments"]);

?>
