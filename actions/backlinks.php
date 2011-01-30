<?php

/*
backlinks.php

Copyright 2002  Patrick PAUL
Copyright 2003  David DELON
Copyright 2003  Charles NEPOTE

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/


if ($page = trim($this->GetParameter('page')))
{
	$title = 'Pages ayant un lien vers '.$this->ComposeLinkToPage($page)."&nbsp;: <br />\n";
}
else
{
	$page = $this->getPageTag();
	$title = "Pages ayant un lien vers la page courante&nbsp;: <br />\n";
}

$pages = $this->LoadPagesLinkingTo($page);

if ($pages)
{
	echo $title;
	$exclude = explode(';', $this->GetParameter('exclude'));
	foreach($exclude as $key => $exclusion){
		$exclude[$key] = trim($exclusion);
	}
	
	foreach($pages as $page){
		if(!in_array($page['tag'], $exclude)){
			echo $this->ComposeLinkToPage($page['tag'], '', '', false), "<br />\n";
		}
	}
}
else
{
	echo '<i>Aucune page n\'a de lien vers ', $this->ComposeLinkToPage($page), '.</i>';
}
?>