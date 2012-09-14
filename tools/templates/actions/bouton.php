<?php
if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}

if (!class_exists('Bouton')) {
	Class Bouton {
	var $alt;
	var $hauteur;
	var $largeur;
	var $nom;
	
	function Bouton($alt, $image, $police, $taille_police, $hex_bgc, $x, $y, $degre, $cache, $theme){
		
		// on cherche le repertoire ou se trouve les boutons
		if (is_dir('themes/'.$theme.'/images/')) { 
			$rep = 'themes/'.$theme.'/images/';
		} else {
			$rep = 'tools/templates/themes/'.$theme.'/images/'; 
		}
		$this->alt = $alt;
		$this->nom = 'cache/bouton_'.str_replace('.png', '', $image).'_'.$this->formater($alt).'.png';
		if(is_file($this->nom) && !empty($cache))	// Si le bouton existe deja et qu'on utilise pas de cache, on renvoie les dimensions
			{
			$taille=getimagesize($this->nom);
			$this->largeur = $taille[0];
			$this->hauteur = $taille[1];
			}
		else // Sinon on va le creer
			{
			// Utilisation des ressources graphiques
			$fond=$rep.$image;
			$taille_fond=getimagesize($fond);
			$img = ImageCreateFromPng ($fond);
	
			// Parametres du bouton
			$this->hauteur = $taille_fond[1];
			$this->largeur = $taille_fond[0];
			
			// On formate la couleur comme il faut
			$hex_bgc = str_replace('#', '', $hex_bgc);
			
			switch (strlen($hex_bgc))
			{
				case 6:
					$red = hexdec(substr($hex_bgc, 0, 2));
					$green = hexdec(substr($hex_bgc, 2, 2));
					$blue = hexdec(substr($hex_bgc, 4, 2));
					break;
					
				case 3:
					$red = substr($hex_bgc, 0, 1);
					$green = substr($hex_bgc, 1, 1);
					$blue = substr($hex_bgc, 2, 1);
					$red = hexdec($red . $red);
					$green = hexdec($green . $green);
					$blue = hexdec($blue . $blue);
					break;
					
				default:
					//	mauvaises valeurs, on met en noir
					$red = 0;
					$green = 0;
					$blue = 0;
			}
			$couleur = ImageColorAllocate ($img, $red, $green, $blue);  
	
			// Texte
			imagettftext($img,$taille_police,$degre,$x,$y,$couleur,$rep.$police,' '.stripslashes(trim($this->alt)));
			imagealphablending($img, true);
			imagesavealpha($img, true);
			
			//Creation du bouton de type btn_[motif]_[alt].png
			imagepng ($img,$this->nom);
			}
		}

	function formater($txt)
		{
	    $a = "àáâãäåòóôõöøèéêëçìíîïùúûüÿñ'@!?;:/ ";
		$b = "aaaaaaooooooeeeeciiiiuuuuyn________";
		return (strtolower(strtr($txt, $a, $b)));
		}
	} //fin classe bouton
}


//parametres wikini

// texte genere a l'interieur du bouton
$texte = $this->GetParameter('texte');
if (empty($texte))
{
        die ('<div class="error">Action bouton : param&ecirc;tre texte obligatoire.</div>');
}

// image utilisee comme fond du bouton
$image = $this->GetParameter('image');
if (empty($image))
{
        die ('<div class="error">Action bouton : param&ecirc;tre image obligatoire.</div>');
}

// image utilisee au survol du bouton
$image = $this->GetParameter('image');
if (empty($image))
{
        die ('<div class="error">Action bouton : param&ecirc;tre image obligatoire.</div>');
}

// police de caractere utilisee
$font = $this->GetParameter('font');
if (empty($font))
{
        die ('<div class="error">Action bouton : param&ecirc;tre font obligatoire.</div>');
}

$url = $this->GetParameter('url');
$class = $this->GetParameter('class');
$cache = $this->GetParameter('cache');

$x = $this->GetParameter('x');
if (empty($x))
{
        $x=0;
}

$y = $this->GetParameter('y');
if (empty($y))
{
        $y=20;
}

$size = $this->GetParameter('size');
if (empty($taille_police))
{
        $taille_police=11;
}

$degre = $this->GetParameter('degre');
if (empty($degre))
{
        $degre=0;
}

$hex_bgc = $this->GetParameter('couleur');
if (empty($hex_bgc))
{
        $hex_bgc='000000';
}


//creation de l'objet
$bouton = new Bouton($texte, $image, $font, $size, $hex_bgc, $x, $y, $degre, $cache, $this->config['favorite_theme'] );
$res = '<img src="'.$bouton->nom.'" height="'.$bouton->hauteur.'" width="'.$bouton->largeur.'" alt="'.$bouton->alt.'" class="bouton_image '.$class.'" />';

if (!empty($url))
{
	if ($this->IsWikiName($url)) {
		$htmllink = '<a href="'.$this->href('', $url).'" class="bouton_link';
		$htmllink .= ($url == $_GET['wiki']) ? ' actif':'';
		$res = $htmllink.'" id="lien_bouton_'.$url.'">'.$res.'</a>';
	} else {
		$htmllink = '<a href="'.$url.'" class="bouton_link';
		$htmllink .= ($url == $this->config["base_url"].$_GET['wiki']) ? ' actif':'';
		$res = $htmllink.'">'.$res.'</a>';
	}
}

echo $res;
?>
