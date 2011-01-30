<?php
require('../fpdf.php');

class PDF extends FPDF
{
//Colonne courante
var $col=0;
//Ordonnée du début des colonnes
var $y0;

function Header()
{
	//En-tête
	global $titre;

	$this->SetFont('Arial','B',15);
	$w=$this->GetStringWidth($titre)+6;
	$this->SetX((210-$w)/2);
	$this->SetDrawColor(0,80,180);
	$this->SetFillColor(230,230,0);
	$this->SetTextColor(220,50,50);
	$this->SetLineWidth(1);
	$this->Cell($w,9,$titre,1,1,'C',true);
	$this->Ln(10);
	//Sauvegarde de l'ordonnée
	$this->y0=$this->GetY();
}

function Footer()
{
	//Pied de page
	$this->SetY(-15);
	$this->SetFont('Arial','I',8);
	$this->SetTextColor(128);
	$this->Cell(0,10,'Page '.$this->PageNo(),0,0,'C');
}

function SetCol($col)
{
	//Positionnement sur une colonne
	$this->col=$col;
	$x=10+$col*65;
	$this->SetLeftMargin($x);
	$this->SetX($x);
}

function AcceptPageBreak()
{
	//Méthode autorisant ou non le saut de page automatique
	if($this->col<2)
	{
		//Passage à la colonne suivante
		$this->SetCol($this->col+1);
		//Ordonnée en haut
		$this->SetY($this->y0);
		//On reste sur la page
		return false;
	}
	else
	{
		//Retour en première colonne
		$this->SetCol(0);
		//Saut de page
		return true;
	}
}

function TitreChapitre($num,$lib)
{
	//Titre
	$this->SetFont('Arial','',12);
	$this->SetFillColor(200,220,255);
	$this->Cell(0,6,"Chapitre $num : $lib",0,1,'L',true);
	$this->Ln(4);
	//Sauvegarde de l'ordonnée
	$this->y0=$this->GetY();
}

function CorpsChapitre($fichier)
{
	//Lecture du fichier texte
	$f=fopen($fichier,'r');
	$txt=fread($f,filesize($fichier));
	fclose($f);
	//Police
	$this->SetFont('Times','',12);
	//Sortie du texte sur 6 cm de largeur
	$this->MultiCell(60,5,$txt);
	$this->Ln();
	//Mention
	$this->SetFont('','I');
	$this->Cell(0,5,'(fin de l\'extrait)');
	//Retour en première colonne
	$this->SetCol(0);
}

function AjouterChapitre($num,$titre,$fichier)
{
	//Ajout du chapitre
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
