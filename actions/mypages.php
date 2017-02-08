<?php
/*
$Id: mypages.php 293 2005-01-09 15:49:02Z nepote $
Copyright (c) 2003, Carlo Zottmann -- http://wakkawikki.com/CarloZottmann
Copyright 2003 David DELON
Copyright 2003 Jean Pascal MILCENT
Copyright 2004 Jean Christophe ANDRé
Copyright 2005 Charles NEPOTE
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

if ($user = $this->GetUser())
{
	echo "<b>"._t('LIST_OF_PAGES_WHERE_YOU_ARE_THE_OWNER').".</b><br /><br />\n" ;

	$my_pages_count = 0;
	$curChar = '';

	if ($pages = $this->LoadAllPages())
	{
		foreach ($pages as $page)
		{
			if ($this->GetUserName() == $page["owner"] && !preg_match("/^Comment/", $page["tag"])) {
				// XXX: strtoupper is locale dependent
				$firstChar = strtoupper($page["tag"][0]);
				if (!preg_match("/".WN_UPPER."/", $firstChar)) {
					$firstChar = "#";
				}

				if ($firstChar != $curChar) {
					if ($curChar) echo "<br />\n" ;
					echo "<b>$firstChar</b><br />\n" ;
					$curChar = $firstChar;
				}
	
				echo $this->ComposeLinkToPage($page["tag"]),"<br />\n" ;
				
				$my_pages_count++;
			}
		}
		
		if ($my_pages_count == 0)
		{
			echo "<i>"._t('YOU_DONT_OWN_ANY_PAGE').".</i>";
		}
	}
	else
	{
		echo "<i>"._t('NO_PAGE_FOUND').".</i>" ;
	}
}
else
{
	echo "<div class=\"alert alert-danger\">"._t('YOU_ARENT_LOGGED_IN')." : "._t('IMPOSSIBLE_TO_SHOW_YOUR_MODIFIED_PAGES').".</div>\n" ;
}

?>
