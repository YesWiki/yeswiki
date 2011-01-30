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
	
	function Bouton($alt, $police, $taille_police, $hex_bgc, $x, $y, $degre, $cache, $theme){
		$rep = 'tools'.DIRECTORY_SEPARATOR.'templates'.DIRECTORY_SEPARATOR.'themes'.DIRECTORY_SEPARATOR.$theme.DIRECTORY_SEPARATOR.'images'.DIRECTORY_SEPARATOR; //repertoire ou se trouve les boutons
	
		$this->alt = $alt;
		$this->nom = $rep.'bouton_'.$this->formater($alt).'.png';
		if(is_file($this->nom) && !empty($cache))	// Si le bouton existe dÃ©ja et qu'on utilise pas de cache, on renvoie les dimensions
			{
			$taille=getimagesize($this->nom);
			$this->largeur = $taille[0];
			$this->hauteur = $taille[1];
			}
		else // Sinon on va le crÃ©er
			{
			// Utilisation des ressources graphiques
			$fond=$rep.'bouton.png';
			$taille_fond=getimagesize($fond);
			$img_fond = ImageCreateFromPng ($fond);
	
			// ParamÃªtres du bouton
			$this->hauteur = $taille_fond[1];
			$this->largeur = $taille_fond[0];
			
			// CrÃ©ation de l'image vierge
			$img = imageCreate($this->largeur,$this->hauteur);
			//	Does it start with a hash? If so then strip it
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
					//	mauvaises valeurs, on met en blanc
					$red = 255;
					$green = 255;
					$blue = 255;
			}
			$couleur = ImageColorAllocate ($img, $red, $green, $blue);  
	
			// ElÃ©mente graphiques du bouton
			@imageCopyMerge($img, $img_fond, 0, 0, 0, 0, $this->largeur, $this->hauteur, 100);
	
			// Texte
			imagettftext($img,$taille_police,$degre,$x,$y,$couleur,$rep.$police,' '.stripslashes(trim($this->alt)));
	
			//CrÃ©ation du bouton de type btn_[motif]_[alt].png
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
$texte = $this->GetParameter('texte');
if (empty($texte))
{
        die ('<span class="error">Action bouton : param&ecirc;tre texte obligatoire.</span>');
}
$url = $this->GetParameter('url');
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
$taille_police = $this->GetParameter('taille');
if (empty($taille_police))
{
        $taille_police=11;
}
$degre = $this->GetParameter('degre');
if (empty($degre))
{
        $degre=0;
}
$police = $this->GetParameter('police');
if (empty($police))
{
        $police='holstein.ttf';
}
$hex_bgc = $this->GetParameter('couleur');
if (empty($hex_bgc))
{
        $hex_bgc='FFFFFF';
}


//creation de l'objet
$bouton = new Bouton($texte, $police, $taille_police, $hex_bgc, $x, $y, $degre, $cache, $this->config['favorite_theme'] );
$res = '<img class="img_bouton" src="'.$bouton->nom.'" height="'.$bouton->hauteur.'" width="'.$bouton->largeur.'" alt="'.$bouton->alt.'" />';

if (!empty($url))
{
	if ($this->IsWikiName($url)) {
		$res = '<a href="'.$this->href('', $url).'" class="lien_bouton" id="lien_bouton_'.$url.'">'.$res.'</a>';
	} else {
		$res = '<a href="'.$url.'" class="lien_bouton">'.$res.'</a>';
	}
}

echo $res;
?>