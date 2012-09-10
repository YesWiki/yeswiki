<?php
/*
pointimage.php

2006 Laurent Marseault (idea) & David Delon (code) 

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

// TODO : utiliser le meme js pour les formulaires que pour les tooltip (supprimer wz_tooltip ....) 
// TODO : regler le pb de cache des images : il faut effacer ...


if ((isset($_GET['image_x']) || isset($_GET['image_y']) )) {

  if ($_POST['submit']=='Sauver') {
  	
  	$spam=0;
  	preg_match_all("/[a-z]+:\/\/\S+/",$_POST['image_texte'],$matches);
	if (count($matches [0])>1) {
		$spam=1;
	}
	preg_match_all("/[a-z]+:\/\/\S+/",$_POST['couleur_xy'],$matches);
	if (count($matches [0])>1) {
		$spam=1;
	}
	
  	if (!$spam) {
	  	$chaine="\n~~\"\"<!--".$_GET['image_x']."-".$_GET['image_y']."-->\"\"[".$_POST['image_texte']."]~~";
	  	if ($_POST['couleur_xy']) {
		  	$chaine="\n~~\"\"<!--".$_GET['image_x']."-".$_GET['image_y']."-".$_POST['couleur_xy']."-->\"\"[".$_POST['image_texte']."]~~";
			preg_match('/([0-9][0-9]*)-([0-9][0-9]*)-*([a-zA-Z]*)/',$location,$elements);
	  	}
		$donneesbody = $this->LoadSingle("select * from ".$this->config["table_prefix"]."pages where tag = '".mysql_real_escape_string($this->GetPageTag().'PointImageDonnees')."'and latest = 'Y' limit 1");
	  	$this->SavePage($this->GetPageTag().'PointImageDonnees',$donneesbody['body'].$chaine);
  		$this->Redirect($this->Href());
  	}
  }
  else {
  	$this->Redirect($this->Href());
  }
}


// Lecture Parametres de l'action :

// nom de la carte

$src_map = $this->GetParameter('srcmap');
if (!$src_map) {
	echo $this->Format('//Parametre srcmap absent//');
	exit;
}

if (!file_exists('images/'.$src_map)) {
	echo $this->Format('//Image non trouv?e//');
}
	
// Couleur par defaut : vert

// Test valeurs de parametres historique pour compatibilit? ascendante
$couleur = $this->GetParameter('color');
if (!$couleur) {
	$couleur = $this->GetParameter('pointcolor');
}

// Taille point par d?faut : 10

$point_size=$this->GetParameter('pointsize');
if (!$point_size) {
	$point_size=10;
}


// Fin lecture Parametre de l'action


// Nom du fichier image en sortie


$dest_map = $this->getPageTag().time().$this->getPageTag().'.jpg';



$img = imagecreatefromjpeg('images/'.$src_map);


if (!function_exists("prepare_fill")) {
	
	function prepare_fill($couleur,$img) {
	
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
		return $fill;
	}
}

$fill_param=prepare_fill($couleur,$img);

echo "<a name=\"topmap\"></a>";


$donneesbody = $this->LoadSingle("select * from ".$this->config["table_prefix"]."pages where tag = '".mysql_real_escape_string($this->GetPageTag().'PointImageDonnees')."'and latest = 'Y' limit 1");

 
// Recherche Points
 	preg_match_all('/~~(.*)~~/msU',$donneesbody['body'],$locations);
	$i=0;
	foreach ($locations[1] as $location){
		// extraction commentaire, si present
		preg_match('/\[(.*)\]/msU',$location,$comments);
		$comment=$comments[1];
		if ($comment) {
			// On enleve le commentaire, c'est plus simple pour la suite (c'est bof hein)
			$location=preg_replace('/\[(.*)\]/msU', '', $location);
		}
		// X, Y + couleur en parametre
		preg_match('/([0-9][0-9]*)-([0-9][0-9]*)-*([a-zA-Z]*)/',$location,$elements);
		
		if ($elements[1]) {
			$position['x_image']   = $elements[1];
			$position['y_image']   = $elements[2];
			$position['color_x_y'] = $elements[3];
		}

		if ($position) {


			$x=$position['x_image'];
			$y=$position['y_image'];
			if ($position['color_x_y']) {
				$color_x_y=$position['color_x_y'];
			}
			else {
				$color_x_y=$couleur;
			}
			
			$x=round($x);
			$y=round($y);

			// Commentaire deja pr?sent ? : on ajoute ? la suite
			if ($text[$x.'|'.$y.'|'.$color_x_y]) {
				$comment=$text[$x.'|'.$y.'|'.$color_x_y]=$text[$x.'|'.$y.'|'.$color_x_y].'<br>'.$comment;
			}
			// Nouveau commentaire
			else {
				$text[$x.'|'.$y.'|'.$color_x_y]=$comment;
			}

		}
		$i++;
	}

// Preparation image map 
 	
		$usemap='';
		
		if (isset($text)) {
			
			foreach ($text as $coord => $maptext ) {
				list($x,$y,$color_x_y)=explode('|',$coord);
				
				if (!isset ($color_x_y) || ($color_x_y == $couleur)) {
					$fill=$fill_param;
				}
				else {
					$fill=prepare_fill($color_x_y,$img);
				}
				imagefilledellipse($img, $x, $y, $point_size, $point_size, $fill);
				// pas de double quote dans le texte
				$maptext=$this->Format($maptext);
				// pas de double quote dans le texte
				$maptext=preg_replace("/'/", "\'", $maptext);
				$maptext=preg_replace("/\"/", "\'", $maptext);
				$maptext=preg_replace("/\n/", "", $maptext);
				$maptext=str_replace("\r", "", $maptext);
				$usemap=$usemap."<area shape=\"circle\" alt=\"\" coords=\"".$x.",".$y.",5\" onmouseover=\"this.T_BGCOLOR='#E6FFFB';this.T_OFFSETX=2;this.T_OFFSETY=2;this.T_STICKY=1;return escape('".$maptext."')\" href=\"#\"/>";
		
			}
		}
		
		?>
		
		 <form id='form_xy' onsubmit='return false'>   
		  
		<?php
		
		echo "<div id =\"pointimagewikimap\" style=\"position:relative;\">";
		echo "<img src=\"".('tools/pointimagewiki/CACHE/'.$dest_map)."\" style=\"border:none; cursor:crosshair\" alt=\"\" usemap=\"#themap\"></img><br />\n";
		echo "<map name=\"themap\" id=\"themap\">";
		echo $usemap;
		echo "</map>";
		echo "</div>";
				
	?>
			<input type='hidden' class='txt' id='x'>
			<input type='hidden' class='txt' id='y'>
			<input type='hidden' class='txt' id='x_image'>
			<input type='hidden' class='txt' id='y_image'>

	</form>
	
	<?php
		
		echo "<br>";                                                                                                                                                           
		echo "<a href=\"".$this->Href()."PointImageDonnees\">*</a>";                                                                                                                  
                                                        
		
		echo "<script language=\"JavaScript\" type=\"text/javascript\" src=\"tools/pointimagewiki/bib/tooltip/wz_tooltip.js\"></script>";
		echo "<script type=\"text/javascript\" src=\"tools/pointimagewiki/bib/x/x_core.js\"></script>";
		echo "<script type=\"text/javascript\" src=\"tools/pointimagewiki/bib/x/x_event.js\"></script>";
		echo "<script type=\"text/javascript\" src=\"tools/pointimagewiki/bib/domtt/domLib.js\"></script>";
		echo "<script type=\"text/javascript\" src=\"tools/pointimagewiki/bib/domtt/domTT.js\"></script>";
		
						

	?>

<script type="text/javascript">


window.onload = function()
{

   if (window.winOnLoad) window.winOnLoad();

}

window.onunload = function()
{
    if (window.winOnUnload) window.winOnUnload();
}


function winOnLoad()
{
  xAddEventListener('pointimagewikimap', 'mousemove', onMousemove, false);
  xAddEventListener('pointimagewikimap', 'click', onMouseclick, false);
  
}
function onMousemove(evt)
{
  var e = new xEvent(evt);
  var fe = xGetElementById('form_xy').elements;
  <?php
  		echo "x_image = e.offsetX; ";
   		echo "y_image =e.offsetY; ";
  ?>
  fe['x'].value = e.offsetX;
  fe['x_image'].value = Math.round(x_image);
  fe['y'].value = e.offsetY;
  fe['y_image'].value = Math.round(y_image);
  return true;
}

function onMouseclick(evt)
{
  var e = new xEvent(evt);
  var fe = xGetElementById('form_xy').elements;
  <?php
  		echo "x_image = e.offsetX; ";
   		echo "y_image =e.offsetY; ";
  ?>
  fe['x'].value = e.offsetX;
  x_image=Math.round(x_image);
  fe['x_image'].value = x_image;
  fe['y'].value = e.offsetY;
  y_image=Math.round(y_image);
  fe['y_image'].value = y_image;
  <?php
	echo "domTT_activate(this, evt, 'content', '<form method=\"post\" action=\"".$this->href()."&image_x='+x_image+'&image_y='+y_image+'\"><div style=\"background-color:white;\"><table><tr><td>Commentaire :</td></tr><tr><td><textarea name=\"image_texte\" cols=\"30\" rows=\"10\" wrap=\"soft\"   ></textarea></td></tr><tr><td><input type=\"submit\" name=\"submit\" value=\"Sauver\"><input type=\"submit\" name=\"cancel\" value=\"Annulation\"></td></tr></table>" . "<input type=\"radio\" name=\"couleur_xy\" value=\"green\" value CHECKED> Vert : Garrigue <input type=\"radio\" name=\"couleur_xy\" value=\"blue\"> Bleu : G?ographie  <input type=\"radio\" name=\"couleur_xy\" value=\"red\"> Rouge : Questions" .
		"</form></div>', 'type', 'sticky' );";
  		
  ?>
  return true;
}
</script>



<?php

    // Suppresion image en cache
    foreach(glob('tools/pointimagewiki/CACHE/'.$this->getPageTag().'*'.$this->getPageTag().'.jpg') as $fn) {
          unlink($fn);
    }

 	
	imageinterlace($img,1);
	imagejpeg($img, 'tools/pointimagewiki/CACHE/'.$dest_map,95);
	imagedestroy($img);

	echo $mapview_output;


?>
