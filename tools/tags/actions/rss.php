<?php
/*
rss.php

Copyright 2009  Florian SCHMITT
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
$tags = $this->GetParameter('tags');
$class = $this->GetParameter("class");
if (empty($class)) $class='';

if ($this->GetMethod() != 'rss' && $this->GetMethod() != 'xml' && $this->GetMethod() != 'tagrss') //on affiche un lien dans la page si on n'est pas en xml
{
	echo '<a class="'.$class.' rss-icon" href="'.$this->Href('tagrss', $this->GetPageTag() ,'tags='.$tags).'" title="'._t('TAGS_RSS_FEED_FOR_NEW_PAGES_WITH_TAGS').' : '.$tags.'">
		</a>'."\n";
	return;
}
?>
