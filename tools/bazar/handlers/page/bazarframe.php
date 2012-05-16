<?php
/*
bazarframe.php

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

// Vérification de sécurité
if (!defined("WIKINI_VERSION"))
{
	die ("acc&egrave;s direct interdit");
}

//header('Content-type: text/html; charset=UTF-8');

if ($HasAccessRead=$this->HasAccess("read"))
{
	if ($this->page)
	{
		//javascript pour gerer les liens (ouvrir vers l'extérieur) dans les iframes
		$scripts_iframe = '<script type="text/javascript">
		$(document).ready(function () {
			$("body").css({
							\'background-color\' : \'transparent\',
							\'background-image\' : \'none\',
							\'text-align\' : \'left\',
							\'width\' : \'auto\',
							\'min-width\' : \'auto\',
						});
			
			$("a[href^=\'http://\']:not(a[href$=\'/slide_show\'])").click(function() {
				if (window.location != window.parent.location)
				{
					if (!($(this).hasClass("bouton_annuler")))
					{
						window.open($(this).attr("href"));
						return false;
					}
				}
			});			
		});
		</script>';		
		$head = explode('<body',$this->Header());
		
		echo $head[0].'<body>';

		// display page
		echo $this->Format('{{bazar'.
		($_GET['vue'] ? ' vue="'.$_GET['vue'].'"' : '').
		($_GET['action'] ? ' action="'.$_GET['action'].'"' : '').
		($_GET['voirmenu'] ? ' voirmenu="'.$_GET['voirmenu'].'"' : ' voirmenu="0"').
		($_GET['id_typeannonce'] ? ' id_typeannonce="'.$_GET['id_typeannonce'].'"' : '').
		'"}}');
		
		$footer = explode('<script type="text/javascript"src="tools/templates/libs/',$this->Footer());
		$footer = '<script type="text/javascript"src="tools/templates/libs/'.$footer[1];
		$footer = str_replace('</body>', $scripts_iframe.'</body>', $footer);
		
		echo $footer;
	}
}
?>
