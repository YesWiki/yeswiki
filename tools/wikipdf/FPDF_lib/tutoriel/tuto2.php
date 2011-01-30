<?php
require('../fpdf.php');

class PDF extends FPDF
{
//En-tête
function Header()
{
	//Logo
	$this->Image('logo_pb.png',10,8,33);
	//Police Arial gras 15
	$this->SetFont('Arial','B',15);
	//Décalage à droite
	$this->Cell(80);
	//Titre
	$this->Cell(30,10,'Titre',1,0,'C');
	//Saut de ligne
	$this->Ln(20);
}

//Pied de page
function Footer()
{
	//Positionnement à 1,5 cm du bas
	$this->SetY(-15);
	//Police Arial italique 8
	$this->SetFont('Arial','I',8);
	//Numéro de page
	$this->Cell(0,10,'Page '.$this->PageNo().'/{nb}',0,0,'C');
}
}

//Instanciation de la classe dérivée
$pdf=new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Times','',12);
for($i=1;$i<=40;$i++)
	$pdf->Cell(0,10,'Impression de la ligne numéro '.$i,0,1);
$pdf->Output();
?>
