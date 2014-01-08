<?php
/*
recentlycommented.php
Copyright (c) 2002, Hendrik Mans <hendrik@mans.de>
Copyright 2002, 2003 David DELON
Copyright 2002, 2003, 2004, 2005 Charles NEPOTE
Copyright 2002 Patrick PAUL
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
// Which is the max number of pages to be shown ?
if ($max = $this->GetParameter("max"))
{
    if ($max=="last") $max=50; else $last = (int) $max;
}
else
{
    $max = 50;
}

// Show recently commented pages
if ($pages = $this->LoadRecentlyCommented($max))
{
	if ($this->GetParameter("max"))
	{
		foreach ($pages as $page)
		{
			// echo entry
			echo "(",$page["comment_time"],") <a href=\"",$this->href("", $page["tag"], "show_comments=1"),"#",$page["comment_tag"],"\">",$page["tag"],"</a> . . . . dernier commentaire par ",$this->Format($page["comment_user"]),"<br />\n" ;
		}
	}
	else
	{
		$curday='';
		foreach ($pages as $page)
		{
			// day header
			list($day, $time) = explode(" ", $page["comment_time"]);
			if ($day != $curday)
			{
				if ($curday) echo "<br />\n" ;
				echo "<b>$day&nbsp;:</b><br />\n" ;
				$curday = $day;
			}

			// echo entry
			echo "&nbsp;&nbsp;&nbsp;(",$time,") <a href=\"",$this->href("", $page["tag"], "show_comments=1"),"#",$page["comment_tag"],"\">",$page["tag"],"</a> . . . . dernier commentaire par ",$this->Format($page["comment_user"]),"<br />\n" ;
		}
	}
}
else
{
	echo "<i>"._t('NO_RECENT_COMMENTS_ON_PAGES').".</i>" ;
}

?>
