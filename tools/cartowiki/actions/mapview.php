<?php
/*
mapview.php

Copyright 2005 David Delon, 

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

// ~~ Commune (departement) [commentaire] ~~

/*

TODO : parametre a externaliser (et à enfouir dans les commentaires de la photo ...)
TODO : revoir la notation des parametres
TODO : Documenter l'inclusion des parametres dans l'image , et proposer les deux options
TODO : chantier optimisation :
	cherchez toutes les villes dans une meme requete ...
TODO : zoom sur département ...
TODO : test centrage
TODO : parametre de desactivation du cache
TODO : revoir la notation des commentaires
TODO : centrage point sur la maille optionnel
TODO : Gerer un peu mieux la suppression des anciennes version : risque de collision si nom de page
debutant de la meme facon
TODO : perf refresh sur zoom
*/

// Forcage rafraichissement par adjonction de &refresh=1 à la requête :
//

// Initialisation
// TODO : revoir la gestion de l'affichage des communes non trouvées

if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}


unset($_SESSION['location']);

// TODO : revoir cette partie (teste ...)
// Mise à jour contenu a la volee si coordonnée utm passé en parametre et texte dans le post :

if ((isset($_GET['utm_x']) || isset($_GET['utm_y']) )) {

  if ($_POST['submit']=='OK') {
  	$spam=0;
  	preg_match_all("/[a-z]+:\/\/\S+/",$_POST['texte_utm'],$matches);
	if (count($matches [0])>1) {
		$spam=1;
	}
	preg_match_all("/[a-z]+:\/\/\S+/",$_GET['utm_x'],$matches);
	if (count($matches [0])>0) {
		$spam=1;
	}
	preg_match_all("/[a-z]+:\/\/\S+/",$_GET['utm_y'],$matches);
	if (count($matches [0])>0) {
		$spam=1;
	}
	
	if (!$spam) {
	  	$chaine="\n~~\"\"<!--".$_GET['utm_x']."-".$_GET['utm_y']."-31 -->\"\"[".$_POST['texte_utm']."]~~";
  		$this->page['body']=$this->page['body'].$chaine;
	  	$this->SavePage($this->getPageTag(),$this->page['body']);
	}
  }
  if (isset($_GET['map_x']) || isset($_GET['map_y'])) { 
	$this->Redirect($this->Href()."&refresh=1&map_x=".$_GET['map_x']."&map_y=".$_GET['map_y']);
  }
  else
  {
  	$this->Redirect($this->Href()."&refresh=1");
  }
}


if(!function_exists('transcribe')) {
	
function transcribe($texte) {
	
	$texte = strtr($texte,
        'ÀÁÂÃÄÅàáâãäåÒÓÔÕÖØòóôõöøÈÉÊËèéêëÇçÌÍÎÏìíîïÙÚÛÜùúûüÿÑñ-\'',
        'AAAAAAaaaaaaOOOOOOooooooEEEEeeeeCcIIIIiiiiUUUUuuuuyNn  ' ) ; 
    return($texte);
    
}
	
}


// Cache :
// Utilisation la version en cours uniquement :
// Si present : affichage
// Si absent : passage en mode buffer pour ecriture en fin de programme

$cachefile = 'tools/cartowiki/CACHE/'.$this->getPageTag().ereg_replace('[: ]', '_', $this->page['time']).$this->getPageTag().'.cache.txt';
if (($this->page['latest']=='Y')) {
	if ((!isset($_REQUEST['refresh']) || $_REQUEST['refresh']!=1)) {
		if (file_exists($cachefile) ) {
	    	include($cachefile);
    		echo "<!-- Cached copy, generated ".date('H:i', filemtime($cachefile))." -->\n";
    		return;
		}
	}
	ob_start(); //  Gestion du cache
}

// Les parametres sont dans le commentaire Jpeg de l'image, utiliser le programme jhead pour les initialiser
// Libraires de lecture des informations associées à l'image

include_once('tools/cartowiki/bib/metadata/'.'JPEG.php');

// Lecture Parametres de l'action :

// nom de la carte

$src_map = $this->GetParameter('srcmap');
if (!$src_map) {
	echo $this->Format('//Parametre srcmap absent//');
	exit;
}

$zoom_map = $this->GetParameter('zoommap');

$coord_visible = $this->GetParameter('coord');

// Couleur par defaut : vert

// Test valeurs de parametres historique pour compatibilité ascendante
$couleur = $this->GetParameter('color');
if (!$couleur) {
	$couleur = $this->GetParameter('pointcolor');
}

// Taille point par défaut : 10

$point_size=$this->GetParameter('pointsize');
if (!$point_size) {
	$point_size=10;
}

// parametre centrage point
$centrage=$this->GetParameter('pointcenter');
if ($centrage=='') {
	$centrage=1;
}


// Fin lecture Parametre de l'action


// Lecture commentaires embarqués dans la page

$comment_jpeg=get_jpeg_Comment(get_jpeg_header_data('tools/cartowiki/images/'.$src_map));
	
// Solution facile de lecture, mais difficile à maintenir : notamment la notation
parse_str($comment_jpeg);



// Fuseau 31T :
// Rappel : Pixel : O,0 en haut gauche

// X Coin inferieur gauche en Pixel
//$Px_echelle_X1['31T']=0;
$Px_echelle_X1['31T'] = $X131T;
// Y Coin inferieur gauche en Pixel
//$Px_echelle_Y1['31T']=539;
$Px_echelle_Y1['31T']=$Y131T;


// Pour calcul Resolution
// X Coin inferieur droit en Pixel
//$Px_echelle_X2['31T']=805;
$Px_echelle_X2['31T']=$X231T;
// Y Coin inferieur droit en Pixel
//$Px_echelle_Y2['31T']=539;
$Px_echelle_Y2['31T']=$Y231T;

// X Coin inferieur gauche en UTM
//$M_UTM_X1['31T']=300092;
$M_UTM_X1['31T']=$X131TUTM;
// Y Coin inferieur gauche en UTM
//$M_UTM_Y1['31T']=4536867;
$M_UTM_Y1['31T']=$Y131TUTM;

// Pour calcul "resolution"

// X Coin inferieur droit en UTM
//$M_UTM_X2['31T']=105371; //
$M_UTM_X2['31T']=$X231TUTM;
// Y Coin inferieur droit en UTM
//$M_UTM_Y2['31T']=5042332;
$M_UTM_Y2['31T']=$Y231TUTM;


// "Resolution"
$p['31T']=($Px_echelle_X2['31T'] - $Px_echelle_X1['31T']) / ($M_UTM_X2['31T'] - $M_UTM_X1['31T']);

// Fuseau 32T :

// Pixel : O,0 en haut gauche

// X Coin inferieur gauche en Pixel
//$Px_echelle_X1['32T']=483;
$Px_echelle_X1['32T']=$X132T;

// Y Coin inferieur gauche en Pixel
//$Px_echelle_Y1['32T']=536;
$Px_echelle_Y1['32T']=$Y132T;

// X Coin inferieur droit en Pixel
//$Px_echelle_X2['32T']=805;
$Px_echelle_X2['32T']=$X232T;
// Y Coin inferieur droit en Pixel
//$Px_echelle_Y2['32T']=536;
$Px_echelle_Y2['32T']=$Y232T;


// X Coin inferieur gauche en UTM
//$M_UTM_X1['32T']=247615;
$M_UTM_X1['32T']=$X132TUTM;
// Y Coin inferieur gauche en UTM
//$M_UTM_Y1['32T']=4540000;
$M_UTM_Y1['32T']=$Y132TUTM;

//$angle3132;

// "Resolution"
$p['32T']=($Px_echelle_X2['31T'] - $Px_echelle_X1['31T'] ) / ($M_UTM_X2['31T'] - $M_UTM_X1['31T']);
//

// Fuseau 30T :

// X Coin inferieur gauche en Pixel
//$Px_echelle_X1['30T']=483;
$Px_echelle_X1['30T']=$X130T;

// Y Coin inferieur gauche en Pixel
//$Px_echelle_Y1['30T']=536;
$Px_echelle_Y1['30T']=$Y130T;

// X Coin inferieur droit en Pixel
//$Px_echelle_X2['30T']=805;
$Px_echelle_X2['30T']=$X230T;
// Y Coin inferieur droit en Pixel
//$Px_echelle_Y2['30T']=536;
$Px_echelle_Y2['30T']=$Y230T;

// X Coin inferieur gauche en UTM
//$M_UTM_X1['30T']=247615;
$M_UTM_X1['30T']=$X130TUTM;
// Y Coin inferieur gauche en UTM
//$M_UTM_Y1['30T']=4540000;
$M_UTM_Y1['30T']=$Y130TUTM;

// angle
//$a=356.0; // (-4 degre)
//$angle3031;

// "Resolution"
$p['30T']=($Px_echelle_X2['31T'] - $Px_echelle_X1['31T'] ) / ($M_UTM_X2['31T'] - $M_UTM_X1['31T']);


// Nom du fichier image en sortie

if ($this->page['latest']=='N') {
	$dest_map = 'revision.'.$this->getPageTag().'.jpg';
}
else {
	if ((( isset($_GET['map_x']) || isset($_GET['map_y'])) && $zoom_map)) {
		$dest_map = $this->getPageTag().ereg_replace('[: ]', '_', time()).$this->getPageTag().'.jpg';
	}
	else {
		$dest_map = $this->getPageTag().ereg_replace('[: ]', '_', $this->page['time']).$this->getPageTag().'.jpg';
	}
}


$img = imagecreatefromjpeg('tools/cartowiki/images/'.$src_map);

switch ($couleur) {
		case 'green':
		   $fill = imagecolorallocate($img, 0, 255, 0);
		   break;
		case 'red':
		   $fill = imagecolorallocate($img, 255, 0, 0);
		   break;
		case 'blue':
		   $fill = imagecolorallocate($img, 0, 0, 255);
		   break;
		case 'black':
		   $fill = imagecolorallocate($img, 0, 0, 0);
		   break;
		default:
		   $fill = imagecolorallocate($img, 0, 255, 0);
}

echo "<a name=\"topmap\"></a>";

// Lecture des localités :
// Ordre de recherche
// 1 : Correspondance exacte : localite + departement
// 2 : Correspondance exacte : localite
// 3 : Correspondance approchée : localite sans le departement si présent
// 4 : Correspondance approchée : soundex sur la ville
// 5 : (On pourrait faire un super soundex  ici ...)


if (preg_match_all('/~~(.*)~~/',$this->page['body'],$locations)){
	$i=0;
	foreach ($locations[1] as $location){
		// extraction commentaire, si present
		preg_match('/\[(.*)\]/',$location,$comments);
		$comment=$comments[1];
		if ($comment) {
			// On enleve le commentaire, c'est plus simple pour la suite (c'est bof hein)
			$location=preg_replace('/\[(.*)\]/', '', $location);
		}
		// UTM en parametre
		preg_match('/([0-9][0-9]*)-([0-9][0-9]*)-([0-9][0-9]*)/',$location,$elements);
		if ($elements[1]) {
			$utm['x_utm'] = $elements[1];
			$utm['y_utm'] = $elements[2];
			$utm['sector']= $elements[3].'T';
			$utm['name']='';
			$utm['maj_name']='';
			$pad = str_repeat ('0' ,(7 - strlen( $utm['x_utm'])));
			$utm['x_utm'] = $pad.$utm['x_utm'];
			$pad = str_repeat ('0' ,(7 - strlen( $utm['y_utm'])));
			$utm['y_utm'] = $pad.$utm['y_utm'];
		}
		else {
			// La ville et le departement ont ete passe en parametre
			preg_match('/(.*)\((.*)\)/',$location,$elements);
			if ($elements[1]) {
				$name=strtoupper(transcribe($elements[1]));
				$code=$elements[2];
				$utm=$this->LoadSingle("select * from locations where maj_name = '".mysql_escape_string($name)."' and code = '".mysql_escape_string($code)."' limit 1");
			}
			else {
				// Seule la ville a ete passe en parametre
				preg_match('/(.*)/',$location,$elements);
				$name=strtoupper(transcribe($elements[1]));
				$utm=$this->LoadSingle("select * from locations where maj_name = '".mysql_escape_string($name)."' limit 1");
			}
		}
		if (!$utm) {
			// On a rien trouvé : nouvelles tentatives
			// Ville seule
			$utm=$this->LoadSingle("select * from locations where maj_name = '".mysql_escape_string($name)."' limit 1");

			// Toujours rien ?
			// Ville soundex
/*			if (!$utm) {
				$utm=$this->LoadSingle("select * from locations where soundex(maj_name) = soundex('".mysql_escape_string($name)."') limit 1");
				 //on stocke ce qu'on a trouver avec le  soundex    pour l'afficher
				if ($utm) {
					$_SESSION['location'][$this->GetPageTag()] [$i]='AF';
					$_SESSION['location_message'][$this->GetPageTag()][$i]=$utm['name'].' '.$utm['code'];
				}
			}
			else {*/
			
			if ($utm) {
				// on stocke ce qu'on a trouve sans le departement pour l'afficher
				$_SESSION['location'][$this->GetPageTag()] [$i]='AF';
				$_SESSION['location_message'][$this->GetPageTag()] [$i]=$utm['name'].' '.$utm['code'];
			}
		}

		// C'est trouvé !

		if ($utm) {

			if ($centrage) {
				// On centre le point au milieu de la maille 10x10 par defaut ...


				$pad = str_repeat ('0' ,(7 - strlen( $utm['x_utm'])));
				$utm['x_utm'] = $pad.$utm['x_utm'];

				$pad = str_repeat ('0' ,(7 - strlen( $utm['y_utm'])));
				$utm['y_utm'] = $pad.$utm['y_utm'];

				$utm['x_utm']=substr($utm['x_utm'] ,0,3);
				$utm['x_utm'] =$utm['x_utm'].'5000';

				$utm['y_utm']=substr($utm['y_utm'] ,0,3);
				$utm['y_utm'] =$utm['y_utm'].'5000';
			}


			// Fuseau 31 T
			if ($utm['sector']=='31T') {
				$x=(($utm['x_utm'] - $M_UTM_X1['31T']) * $p['31T'] ) + $Px_echelle_X1['31T'];
				$y=$Px_echelle_Y2['31T']-(($utm['y_utm'] - $M_UTM_Y1['31T']) * $p['31T'] );
			}
			else {

				// Fuseau 32 T : une rotation + translation est appliquée
				if ($utm['sector']=='32T') {
					$cosa = cos(deg2rad($angle3132));
					$sina = sin(deg2rad($angle3132));

					$xp = (($utm['x_utm'] - $M_UTM_X1['32T']) * $cosa) + (($utm['y_utm']- $M_UTM_Y1['32T']) * $sina);
					$yp = (-($utm['x_utm'] - $M_UTM_X1['32T'])* $sina) + (($utm['y_utm'] - $M_UTM_Y1['32T'])* $cosa);
					$x=($xp * $p['32T'] ) + $Px_echelle_X1['32T'];
					$y=$Px_echelle_Y2['32T']-($yp * $p['32T'] );

				}

				else {
					// Fuseau 30 T : une rotation + translation est appliquée
					if ($utm['sector']=='30T') {

						$cosa = cos(deg2rad($angle3031));
						$sina = sin(deg2rad($angle3031));

						$xp = (($utm['x_utm'] - $M_UTM_X1['30T']) * $cosa) + (($utm['y_utm']- $M_UTM_Y1['30T']) * $sina);
						$yp = (-($utm['x_utm'] - $M_UTM_X1['30T'])* $sina) + (($utm['y_utm'] - $M_UTM_Y1['30T'])* $cosa);
						$x=($xp * $p['30T'] ) + $Px_echelle_X1['30T'];
						$y=$Px_echelle_Y2['30T']-($yp * $p['30T'] );

					}
				}
			}


			$x=round($x);
			$y=round($y);

		
			$name=strtoupper(transcribe($utm['maj_name']));
			
			if (isset($name) && ($name!='')) {
				$comment=' : '.$comment;
			}
			
			// Le commentaire commence par un lien forcé : On lit la premiere image de la page wiki
			
			$imagewiki='';
			$url='';
			
			if (preg_match("/.*\[\[(\S*)(\s+(.+))?\]\].*/U", $comment, $matches)) {
				list (, $url, $texte_url) = $matches;
				if ($url) {
					$html = file_get_contents($this->href("",$url));
					preg_match('/<img src="(.*)"/U', $html, $matches);
					$imagewiki=$matches[1];
				}
				// nettoyage
				$comment=preg_replace('/\[\[(.*)\]\]/', $texte_url, $comment);
			}
			
			// On stocke les commentaires pour affichage dans les tooltips
			
			$link="<a href=\"#MAP_".$i."\">".$name.$comment."</a>";
			
			if ($imagewiki) {
				$link="<a href=\"".$this->href("",$url)."\"><img src=\"".$imagewiki."\"/></a><br>".$link;
			}

			// Commentaire deja présent ? : on ajoute à la suite
			if ($text[$x.'|'.$y]) {
				$link=$text[$x.'|'.$y]=$text[$x.'|'.$y].'<br>'.$link;
			}
			// Nouveau commentaire
			else {
				$text[$x.'|'.$y]=$link;
			}

		}
		// Pas trouvé : on stocke la ligne en session pour transmission au formatter qui affichera le message d'erreur.

		else {
			$_SESSION['location'][$this->GetPageTag()] [$i]='NF';
		}
		$i++;
	}

	
	// revision : pas de gestion de cache : on produit une image.

	if ($this->page['latest']=='N') {
		imageinterlace($img,1);
		imagejpeg($img, 'tools/cartowiki/CACHE/'.$dest_map,95);
		imagedestroy($img);
	}

 	// Zoom : pas de gestion de cache : on produit une image.
	
	if ((isset($_GET['map_x']) || isset($_GET['map_y'])) && ($zoom_map)) {
		
		// Fichier double taille 
		$filename = 'tools/cartowiki/images/'.$zoom_map;
	
		// nouvelle dimension 
		list($width, $height) = getimagesize($filename);
		
		$new_width=$width/2;
		$new_height=$height/2;
		
		// recentrage
		$map_x=$_GET['map_x']*2;
		$map_y=$_GET['map_y']*2;
			
		$map_x = $map_x - ($new_width/2);
		if (($map_x + $new_width)> $width) { $map_x = $width - $new_width;};
		if ($map_x < 0) $map_x=0;
		$map_y = $map_y - ($new_height/2);
		if (($map_y + $new_height) > $height) { $map_y = $height- $new_height ;};
		if ($map_y < 0) $map_y=0;
		
		// 	Resample
		$image_p = imagecreatetruecolor($new_width, $new_height);  
		$image = imagecreatefromjpeg($filename);
	
		imagecopyresampled($image_p, $image, 0, 0, $map_x, $map_y,  $new_width,$new_height, $new_width, $new_height);
	
		// Output
	
		$usemap='';
		foreach ($text as $coord => $maptext ) {
			list($x,$y)=explode('|',$coord);
			$x=($x*2)-$map_x;
			$y=($y*2)-$map_y;
			//imagearc($img, $x, $y, 10, 10, 0, 360, $green);
			// Gd2, idealement il faudrait tester la disponibilite de la fonction et se rabbatre sur imagearc sinon
			imagefilledellipse($image_p, $x, $y, $point_size, $point_size, $fill);
			// pas de double quote dans le texte
			$maptext=preg_replace("/'/", "\'", $maptext);
			$maptext=preg_replace("/\"/", "\'", $maptext);
			$usemap=$usemap."<area shape=\"circle\" alt=\"\" coords=\"".$x.",".$y.",5\" onmouseover=\"this.T_BGCOLOR='#E6FFFB';this.T_OFFSETX=2;this.T_OFFSETY=2;this.T_STICKY=1;return escape('".$maptext."')\" href=\"#\"/>";
	
		}
		
		imageinterlace($image_p,1);
		imagejpeg($image_p, 'tools/cartowiki/CACHE/'.$dest_map,95);		
		imagedestroy($image_p);
		
			?>
	<form id='form_xy' onsubmit='return false'>
		<table>
			<tr>
			<td><input type="radio" name="outil" value="zoom"   id="zoom" onclick="changeOutil()" />zoom avant/arrière<br/></td>
          	<td><input type="radio" name="outil" value="point"  id="point" onclick="changeOutil()" />saisie d'un point<br/></td>
			</tr>
		</table>
	<?php		
		
		
				
		echo "<div id =\"cartowikimap\" style=\"position:relative;\">";
	 	echo "<img src=\"".('tools/cartowiki/CACHE/'.$dest_map)."\" style=\"border:none; cursor:crosshair\" alt=\"\" usemap=\"#themap\" ";
	 	echo "<map name=\"themap\" id=\"themap\">";
		echo $usemap;
		echo "</map>";
		
	?>
		<table>	
		<tr>
			<td><input type='text' class='txt' id='x'></td>
			<td><input type='text' class='txt' id='y'></td>
			<td><input type='text' class='txt' id='x_utm'></td>
			<td><input type='text' class='txt' id='y_utm'></td>
			<td><input type='hidden' class='txt' id='valeur_outil'></td>
		<tr>
		</table>
	</form>
	
	<?php
		echo "</div>";
		echo "<script language=\"JavaScript\" type=\"text/javascript\" src=\"tools/cartowiki/bib/tooltip/wz_tooltip.js\"></script>";
		echo "<script type=\"text/javascript\" src=\"tools/cartowiki/bib/x/x_core.js\"></script>";
		echo "<script type=\"text/javascript\" src=\"tools/cartowiki/bib/x/x_event.js\"></script>";
		echo "<script type=\"text/javascript\" src=\"tools/cartowiki/bib/domtt/domLib.js\"></script>";
		echo "<script type=\"text/javascript\" src=\"tools/cartowiki/bib/domtt/domTT.js\"></script>";
		
		
	} 
 	
 	else {
		$usemap='';
		foreach ($text as $coord => $maptext ) {
			list($x,$y)=explode('|',$coord);
			imagefilledellipse($img, $x, $y, $point_size, $point_size, $fill);
			// pas de double quote dans le texte
			$maptext=preg_replace("/'/", "\'", $maptext);
			$maptext=preg_replace("/\"/", "\'", $maptext);
			$usemap=$usemap."<area shape=\"circle\" alt=\"\" coords=\"".$x.",".$y.",5\" onmouseover=\"this.T_BGCOLOR='#E6FFFB';this.T_OFFSETX=2;this.T_OFFSETY=2;this.T_STICKY=1;return escape('".$maptext."')\" href=\"#\"/>";
	
		}
		if ($zoom_map!="")   {
		?>
	<form id='form_xy' onsubmit='return false'>
		<table>
			<tr>
			<td><input type="radio" name="outil" value="zoom"   id="zoom" onclick="changeOutil()" />zoom avant/arrière<br/></td>
          	<td><input type="radio" name="outil" value="point"  id="point" onclick="changeOutil()" />saisie d'un point<br/></td>
			</tr>
		</table>
	<?php		
		}

		if ($coord_visible!="")  {
		?>
	<form id='form_xy' onsubmit='return false'>
			<table>
			<tr>
			<td><input type="hidden" name="outil" value="zoom"   id="zoom" onclick="changeOutil()" /></td>
          	<td><input type="hidden" name="outil" value="point"  id="point" onclick="changeOutil()" /></td>
			</tr>
		</table>
	
	<?php		
		}

		
		echo "<div id =\"cartowikimap\" style=\"position:relative;\">";
		echo "<img src=\"".('tools/cartowiki/CACHE/'.$dest_map)."\" style=\"border:none; cursor:crosshair\" alt=\"\" usemap=\"#themap\"></img><br />\n";
		echo "<map name=\"themap\" id=\"themap\">";
		echo $usemap;
		echo "</map>";
		echo "</div>";
				
	?>
		<table>	
		<tr>
			<td><input type='hidden' class='txt' id='x'></td>
			<td><input type='hidden' class='txt' id='y'></td>
			<td><input type='text' class='txt' id='x_utm'></td>
			<td><input type='text' class='txt' id='y_utm'></td>
			<td><input type='hidden' class='txt' id='valeur_outil'></td>
		<tr>
		</table>

	</form>
	
	<?php
		
		echo "<script language=\"JavaScript\" type=\"text/javascript\" src=\"tools/cartowiki/bib/tooltip/wz_tooltip.js\"></script>";
		echo "<script type=\"text/javascript\" src=\"tools/cartowiki/bib/x/x_core.js\"></script>";
		echo "<script type=\"text/javascript\" src=\"tools/cartowiki/bib/x/x_event.js\"></script>";
		echo "<script type=\"text/javascript\" src=\"tools/cartowiki/bib/domtt/domLib.js\"></script>";
		echo "<script type=\"text/javascript\" src=\"tools/cartowiki/bib/domtt/domTT.js\"></script>";
		
						
 	}

	?>

<script type="text/javascript">


window.onload = function()
{

   var outil = xGetElementById('form_xy').elements;   
   if (get_cookie('outil')!="") {
   		outil['valeur_outil'].value = get_cookie('outil');
   		outil[get_cookie('outil')].checked = true;
   }
   else {
   		outil['valeur_outil'].value = 'zoom';
   		outil['zoom'].checked = true;
		setoutil('zoom');
   }
   if (window.winOnLoad) window.winOnLoad();

}

window.onunload = function()
{
    if (window.winOnUnload) window.winOnUnload();
}

function get_cookie(Name) {
	var search = Name + "="
	var returnvalue = "";
	if (document.cookie.length > 0) {
		offset = document.cookie.indexOf(search)
		// if cookie exists
		if (offset != -1) {
			offset += search.length
			// set index of beginning of value
			end = document.cookie.indexOf(";", offset);
			// set index of end of cookie value
			if (end == -1) end = document.cookie.length;
			returnvalue=unescape(document.cookie.substring(offset, end))
		}
	}
	return returnvalue;
}

function setoutil(outil_selectionne){
	document.cookie="outil="+outil_selectionne;
}


function changeOutil() {
	var outil = xGetElementById('form_xy').elements;
	if (outil['point'].checked) {
		outil['valeur_outil'].value = 'point';
		setoutil('point');
	}
	else {
		outil['valeur_outil'].value = 'zoom';
		setoutil('zoom');
	}
}


function winOnLoad()
{
  xAddEventListener('cartowikimap', 'mousemove', onMousemove, false);
  xAddEventListener('cartowikimap', 'click', onMouseclick, false);
  
}
function onMousemove(evt)
{
  var e = new xEvent(evt);
  var fe = xGetElementById('form_xy').elements;
  <?php
  // Normal
  	if (!isset($_GET['map_x']) && !isset($_GET['map_y'])) {
  		echo "x_utm = ((e.offsetX - ".$Px_echelle_X1['31T'].") / ".$p['31T'].") + ".$M_UTM_X1['31T'].";";
   		echo "y_utm = ((e.offsetY - ".$Px_echelle_Y2['31T'].") / -".$p['31T'].") + ".$M_UTM_Y1['31T'].";";
  	}
  // Zoom in (correction coordonnees)
  	else {
  		echo "x_utm = ((((e.offsetX + ".$map_x.")/2) - ".$Px_echelle_X1['31T'].") / ".$p['31T'].") + ".$M_UTM_X1['31T'].";";
   		echo "y_utm = ((((e.offsetY + ".$map_y.")/2) - ".$Px_echelle_Y2['31T'].") / -".$p['31T'].") + ".$M_UTM_Y1['31T'].";";
  	}
  ?>
  fe['x'].value = e.offsetX;
  fe['x_utm'].value = Math.round(x_utm);
  fe['y'].value = e.offsetY;
  fe['y_utm'].value = Math.round(y_utm);
  return true;
}

function onMouseclick(evt)
{
  var e = new xEvent(evt);
  var fe = xGetElementById('form_xy').elements;
  <?php
    // Normal
  	if (!isset($_GET['map_x']) && !isset($_GET['map_y'])) {
  		echo "x_utm = ((e.offsetX - ".$Px_echelle_X1['31T'].") / ".$p['31T'].") + ".$M_UTM_X1['31T'].";";
   		echo "y_utm = ((e.offsetY - ".$Px_echelle_Y2['31T'].") / -".$p['31T'].") + ".$M_UTM_Y1['31T'].";";
  	}
  	// Zoom in (correction coordonnees)
  	else {
  		echo "x_utm = ((((e.offsetX + ".$map_x.")/2) - ".$Px_echelle_X1['31T'].") / ".$p['31T'].") + ".$M_UTM_X1['31T'].";";
   		echo "y_utm = ((((e.offsetY + ".$map_y.")/2) - ".$Px_echelle_Y2['31T'].") / -".$p['31T'].") + ".$M_UTM_Y1['31T'].";";
  	}   	
  ?>
  fe['x'].value = e.offsetX;
  x_utm=Math.round(x_utm);
  fe['x_utm'].value = x_utm;
  fe['y'].value = e.offsetY;
  y_utm=Math.round(y_utm);
  fe['y_utm'].value = y_utm;
  if (fe['point'].checked)  {
  <?php
  		if (!isset($_GET['map_x']) && !isset($_GET['map_y'])) {
			echo "domTT_activate(this, evt, 'content', '<form method=\"post\" action=\"".$this->href()."&refresh=1&utm_x='+x_utm+'&utm_y='+y_utm+'\"><div style=\"background-color:white;\"><table><tr><td>Description :</td></tr><tr><td><input type=\"text\" name=\"texte_utm\"></td><td><input type=\"submit\" name=\"submit\" value=\"OK\"><input type=\"submit\" name=\"cancel\" value=\"Annuler\"></td></tr></table></form></div>', 'type', 'sticky' );";
  		}
  		else {
  			echo "domTT_activate(this, evt, 'content', '<form method=\"post\" action=\"".$this->href()."&refresh=1&utm_x='+x_utm+'&utm_y='+y_utm+'&map_x='+".$_GET['map_x']."+'&map_y='+".$_GET['map_y']."+'\"><div style=\"background-color:white;\"><table><tr><td>Description :</td></tr><tr><td><input type=\"text\" name=\"texte_utm\"></td><td><input type=\"submit\" name=\"submit\" value=\"OK\"><input type=\"submit\" name=\"cancel\" value=\"Annuler\"></td></tr></table></form></div>', 'type', 'sticky' );";
  		}
  		
	// }
  ?>
  }
  else {
  <?php
	 	// Zoom-out
	 	 if ((isset($_GET['map_x']) || isset($_GET['map_y'])) && ($zoom_map)) {
			echo "window.location.assign ('".$this->href()."&refresh=1')";
	  	}
	  	// Zoom-in
	 	else {
	 	 	echo "window.location.assign ('".$this->href()."&refresh=1&map_x='+e.offsetX+'&map_y='+e.offsetY);";
	 	}
	 	//echo "domTT_activate(this, evt, 'content', '<form method=\"post\" action=\"".$this->href()."&refresh=1&utm_x='+x_utm+'&utm_y='+y_utm+'\"><div style=\"background-color:white;\"><table><tr><td>Description :</td></tr><tr><td><input type=\"text\" name=\"texte_utm\"></td><td><input type=\"submit\" name=\"submit\" value=\"OK\"></td></tr></table></form></div>', 'type', 'sticky' )";
	 //}
	 
  ?>
  }
  return true;
}
</script>



	<?php
 	
}



// Affichage image origine
else {
	echo "<img src=\"".'tools/cartowiki/images/'.$src_map."\" style=\"border:none; cursor:crosshair\" alt=\"\"</img><br />\n";
	echo "</map>";
}

echo "<br>";
echo "<a href=\"".$this->Href()."&refresh=1\">*</a>";

// Fin gestion du cache


// Utilisation pour la derniere page uniquement ou pour du refresh

if (($this->page['latest']=='Y') || (($this->page['latest']=='Y') && isset($_REQUEST['refresh']) && $_REQUEST['refresh']==1)) {
	
	if (!isset($_GET['map_x']) && !isset($_GET['map_y'])) {
	
//		echo "<a href=\"".$this->Href()."&refresh=1\">*</a>";
	
		// Generation image cache
	
	    // Suppresion texte en cache
	    foreach(glob('tools/cartowiki/CACHE/'.$this->getPageTag().'*'.$this->getPageTag().'.cache.txt') as $fn) {
	           unlink($fn);
	    }
	    // Suppresion image en cache
	    foreach(glob('tools/cartowiki/CACHE/'.$this->getPageTag().'*'.$this->getPageTag().'.jpg') as $fn) {
	           unlink($fn);
	    }
	
		imageinterlace($img,1);
		imagejpeg($img, 'tools/cartowiki/CACHE/'.$dest_map,95);
		imagedestroy($img);
	
		// Generation texte cache
	
		$fp = fopen($cachefile, 'w');
		$mapview_output = ob_get_contents();
		fwrite($fp, $mapview_output);
		fclose($fp);
		ob_end_clean();
		echo $mapview_output;
	}

}


?>
