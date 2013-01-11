<?php
/*
mapview.php

Copyright 2007  David DELON
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

//vérification de sécurité

if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}


if ($this->HasAccess("read"))
{
	if (!$this->page)
	{
		return;
	}
	else
	{
		list(,$endq)=split('&',$_SERVER[QUERY_STRING]);
		list($val,$template)=split('=',$endq);
		
		$template = $this->LoadPage($template);

				
		$ereg=preg_quote($template['body']);
		$ereg=preg_replace("/%.*%/U","(.*)",$ereg);

		
		preg_match_all("/".$ereg."/",$this->page["body"],$matches,PREG_SET_ORDER);

		$csv_output='';
		foreach ($matches as $match) {
			$lin='';
			for ($i=1;$i<strlen($match);$i++) {	
				$lin .= ($match[$i]).",";	
			}
			$lin=preg_replace('/,$/','',$lin);
			$csv_output .= $lin."\n";
			
		}
		
		header("Content-type: application/vnd.ms-excel");
		header("Content-disposition: attachment; filename=".$this->tag."_". date("Ymd").".csv");
		print $csv_output;
		exit;
		
	}
}
else
{
	return;
}
?>