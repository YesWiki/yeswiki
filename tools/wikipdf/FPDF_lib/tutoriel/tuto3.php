<?php
require('../fpdf.php');

class PDF extends FPDF
{
function Header()
{
	global $titre;

	//Arial gras 15
	$this->SetFont('Arial','B',15);
	//Calcul de la largeur du titre et positionnement
	$w=$this->GetStringWidth($titre)+6;
	$this->SetX((210-$w)/2);
	//Couleurs du cadre, du fond et du texte
	$this->SetDrawColor(0,80,180);
	$this->SetFillColor(230,230,0);
	$this->SetTextColor(220,50,50);
	//Epaisseur du cadre (1 mm)
	$this->SetLineWidth(1);
	//Titre centré
	$this->Cell($w,9,$titre,1,1,'C',true);
	//Saut de ligne
	$this->Ln(10);
}

function Footer()
{
	//Positionnement à 1,5 cm du bas
	$this->SetY(-15);
	//Arial italique 8
	$this->SetFont('Arial','I',8);
	//Couleur du texte en gris
	$this->SetTextColor(128);
	//Numéro de page
	$this->Cell(0,10,'Page '.$this->PageNo(),0,0,'C');
}

function TitreChapitre($num,$lib)
{
	//Arial 12
	$this->SetFont('Arial','',12);
	//Couleur de fond
	$this->SetFillColor(200,220,255);
	//Titre
	$this->Cell(0,6,"Chapitre $num : $lib",0,1,'L',true);
	//Saut de ligne
	$this->Ln(4);
}

function CorpsChapitre($fichier)
{
	//Lecture du fichier texte
	$f=fopen($fichier,'r');
	$txt=fread($f,filesize($fichier));
	fclose($f);
	//Times 12
	$this->SetFont('Times','',12);
	//Sortie du texte justifié
	$this->MultiCell(0,5,$txt);
	//Saut de ligne
	$this->Ln();
	//Mention en italique
	$this->SetFont('','I');
	$this->Cell(0,5,'(fin de l\'extrait)');
}

function AjouterChapitre($num,$titre,$fichier)
{
	$this->AddPage();
	$this->TitreChapitre($num,$titre);
	$this->CorpsChapitre($fichier);
}
}

$pdf=new PDF();
$titre='Vingt mille lieues sous les mers';
$pdf->SetTitle($titre);
$pdf->SetAuthor('Jules Verne');
$pdf->AjouterChapitre(1,'UN ÉCUEIL FUYANT','20k_c1.txt');
$pdf->AjouterChapitre(2,'LE POUR ET LE CONTRE','20k_c2.txt');
$pdf->Output();
?>
