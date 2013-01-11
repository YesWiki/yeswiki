<?php
/*
resetvotes.php

Copyright 2010  Florian Schmitt <florian@outils-reseaux.org>
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

// on crée un id de session pour savoir qui vote, valabel 24h, mais cela est transparent pour l'utilisateur car caché
$sessionCookieExpireTime=24*60*60;
session_set_cookie_params($sessionCookieExpireTime);
$id = session_id();
if(empty($id)) {
	session_start();
	$id = session_id();
}

if (isset($GLOBALS["HTTP_RAW_POST_DATA"])) {
	// Get the data
	$imageData=$GLOBALS['HTTP_RAW_POST_DATA'];

	// Remove the headers (data:,) part.  
	// A real application should use them according to needs such as to check image type
	$filteredData=substr($imageData, strpos($imageData, ",")+1);

	// Need to decode before saving since the data we received is already base64 encoded
	$unencodedData=base64_decode($filteredData);

	//echo "unencodedData".$unencodedData;

	// Save file.  This example uses a hard coded filename for testing, 
	// but a real application can specify filename in POST variable
	$fichierpng = 'cache/vote_'.date("d-m-Y_H-i-s").'_'.md5($id).'.png';
	$fp = fopen( $fichierpng, 'w+b' );
	fwrite( $fp, $unencodedData);
	fclose( $fp );
	echo str_replace('wakka.php?wiki=','',$this->config['base_url']).$fichierpng;
}
?>

