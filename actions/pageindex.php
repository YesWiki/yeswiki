<?php
/*
$Id: pageindex.php 280 2004-12-19 18:58:51Z progfou $
Copyright (c) 2003, Hendrik Mans <hendrik@mans.de>
Copyright 2003 David DELON
Copyright 2004 Didier Loiseau
Copyright 2004 Jean Christophe ANDRé
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
if ($pages = $this->LoadAll('SELECT tag FROM ' . $this->config["table_prefix"] . 'pages WHERE latest = \'Y\' AND comment_on=\'\' ORDER BY tag'))
{
	foreach ($pages as $page)
	{
		// XXX: strtoupper is locale dependent
		$firstChar = strtoupper($page['tag']{0});
		if (!preg_match('/'.WN_UPPER.'/', $firstChar)) {
			$firstChar = '#';
		}

		if (empty($curChar) || $firstChar != $curChar) {
			if (!empty($curChar)) echo "<br />\n" ;
			echo "<b>$firstChar</b><br />\n" ;
			$curChar = $firstChar;
		}

		echo $this->ComposeLinkToPage($page['tag'], '', '', false),"<br />\n" ;
	}
}
else
{
	echo '<i>'._t('NO_PAGE_FOUND').'.</i>' ;
}

?>
