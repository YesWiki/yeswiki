<?
/*cette fonction indique que la page devra etre interpr�t�e comme une image PNG*/
     header ("Content-type: image/png");

/*on cr�e une image de 150 pixels de large sur 15 de haut*/
     $image = imagecreate(100,15);
     
     /*Ici, on r�cup�re dans la variable $pc le pourcentage que l'on veut afficher la page est appel�e par compteur.php?pc=[un nombre entre 0 et 100]*/
	if(isset($_GET['pc']))
	{
		$pc=$_GET['pc'];
	}
     
     /* pour une image de 150 px, la partie � remplire en pourcentage fait 148px... on calcule la longueur � remplir en pixels */
     $x=($pc*98)/100;
     
     /*d�finition des couleurs... l'image est automatiquement remplie avec la premi�re couleur que vous d�finissez. Ici on aura un fond blanc */
     $blanc=imagecolorallocate($image, 255, 255, 255);
     $noir=imagecolorallocate($image, 0, 0, 0);
     $bleu=imagecolorallocate($image, 170, 204, 238);
     
	/*on fait un petit cadre noir sur le pourtour de l'image*/
     imagerectangle($image, 0, 0, 99, 14, $noir);

     /*dessin du remplissage en fonction de $x : on dessine un rectangle de $x pixels de large rempli en bleu*/
     imageFilledRectangle($image, 1, 1, $x, 13, $bleu);

     /*on place le texte au milieu : [$pc %]...*/
     imagestring($image, 3, 42, 1, $pc."%", $noir);

	/*Pour finir, on g�n�re l'image en png */
     imagepng($image);
?>