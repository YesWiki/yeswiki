<?php
/*

Copyright 2004  Thierry BAZZANELLA

@license GNU GPL

@author Thierry BAZZANELLA
@author Nils Lindenberg (changes for wikka, marked with NL)

*/

//(NL) changed for wikka
define('FPDF_FONTPATH','tools/pdf/fonts/');
require_once('tools/pdf/lib/fpdf/fpdf.php');

// fonction hex2dec
// retourne un tableau associatif (clés : R,V,B) à
// partir d'un code html de couleur hexa (ex : #3FE5AA)
function hex2dec($couleur = "#000000"){
    $R = substr($couleur, 1, 2);
    $rouge = hexdec($R);
    $V = substr($couleur, 3, 2);
    $vert = hexdec($V);
    $B = substr($couleur, 5, 2);
    $bleu = hexdec($B);
    $tbl_couleur = array();
    $tbl_couleur['R']=$rouge;
    $tbl_couleur['V']=$vert;
    $tbl_couleur['B']=$bleu;
    return $tbl_couleur;
}

//conversion pixel -> millimètre en 72 dpi
function px2mm($px){
    return $px*25.4/72;
}

function txtentities($html){
    $trans = get_html_translation_table(HTML_ENTITIES);
    $trans = array_flip($trans);
    return strtr($html, $trans);
}

// Surcharge de la CLASSE FPDF :

class PDF extends FPDF
{
var $B;
var $I;
var $U;
var $HREF;
var $fontList;
var $issetfont;
var $issetcolor;

var $WIKIPAGE;

function PDF($orientation='P',$unit='mm',$format='A4')
{
    //Appel au constructeur parent
    $this->FPDF($orientation,$unit,$format);
    //Initialisation
    $this->B=0;
    $this->I=0;
    $this->U=0;
    $this->HREF='';
    $this->fontlist=array("arial","times","courier","helvetica","symbol");
    $this->issetfont=false;
    $this->issetcolor=false;
}

function setWikiPage($page) {

    $this->WIKIPAGE=$page;

}
function getWikiPage() {

    return $this->WIKIPAGE;

}

function WriteHTML($html)
{
    //Parseur HTML
    
    $html=str_replace("\n",' ',$html); //remplace retour à la ligne par un espace
    $html=strip_tags($html,'<h1><h2><h3><h4><h5><h6><b><u><i><a><img><p><br><strong><em><font><tr><blockquote>'); //supprime tous les tags sauf ceux reconnus
    //$html=strip_tags($html,'<a><img><p><br><strong><em><font><tr><blockquote>'); //supprime tous les tags sauf ceux reconnus
    $a=preg_split('/<(.*)>/U',$html,-1,PREG_SPLIT_DELIM_CAPTURE); //éclate la chaîne avec les balises
    foreach($a as $i=>$e)
    {
        if($i%2==0)
        {
            //Texte
            if($this->HREF)
                $this->PutLink($this->HREF,$e);
            else
                $this->Write(5,stripslashes(txtentities($e)));
        }
        else
        {
            //Balise
            if($e{0}=='/')
                $this->CloseTag(strtoupper(substr($e,1)));
            else
            {
                //Extraction des attributs
                $a2=explode(' ',$e);
                $tag=strtoupper(array_shift($a2));
                $attr=array();
                foreach($a2 as $v)
                    if(ereg('^([^=]*)=["\']?([^"\']*)["\']?$',$v,$a3))
                        $attr[strtoupper($a3[1])]=$a3[2];
                $this->OpenTag($tag,$attr);
            }
        }
    }
}

function OpenTag($tag,$attr) //Balise ouvrante
{
    switch($tag){
    	case 'H1':
    		$this->SetFontSize(22);
    		$this->Ln(20);
    		break;
    	case 'H2':
    		$this->SetFontSize(16);
    		$this->Ln(10);
    		break;
    	case 'H3':
    		$this->SetFontSize(14);
    		$this->Ln(5);
    		break;
    	case 'H4':
    		$this->SetFontSize(13);
    		break;
    	case 'H5':
    		$this->SetFontSize(12);
    		break;
    	case 'H6':
    		$this->SetFontSize(11);
    		break;
        case 'STRONG':
            $this->SetStyle('B',true);
            break;
        case 'EM':
            $this->SetStyle('I',true);
            break;
        case 'B':
        case 'I':
        case 'U':
            $this->SetStyle($tag,true);
            break;
        case 'A':
            $this->HREF=$attr['HREF'];
            break;
        case 'IMG':
            if(isset($attr['SRC']) and (isset($attr['WIDTH']) or isset($attr['HEIGHT']))) {
                if(!isset($attr['WIDTH']))
                    $attr['WIDTH'] = 0;
                if(!isset($attr['HEIGHT']))
                    $attr['HEIGHT'] = 0;
                $this->Image($attr['SRC'], $this->GetX(), $this->GetY(), px2mm($attr['WIDTH']), px2mm($attr['HEIGHT']));
            }
            break;
        case 'TR':
        case 'BLOCKQUOTE':
        case 'BR':
            $this->Ln(5);
            break;
        case 'P':
            $this->Ln(10);
            break;
        case 'FONT':
            if (isset($attr['COLOR']) and $attr['COLOR']!='') {
                $coul=hex2dec($attr['COLOR']);
                $this->SetTextColor($coul['R'],$coul['V'],$coul['B']);
                $this->issetcolor=true;
            }
            if (isset($attr['FACE']) and in_array(strtolower($attr['FACE']), $this->fontlist)) {
                $this->SetFont(strtolower($attr['FACE']));
                $this->issetfont=true;
            }
            break;
    }
}

function CloseTag($tag) //Balise fermante
{
    if($tag=='STRONG')
        $tag='B';
    if($tag=='EM')
        $tag='I';
    if($tag=='H1'){
       	$this->SetFontSize(10);
    	$this->Ln(20);
    }
    if($tag=='H2'){
       	$this->SetFontSize(10);
    	$this->Ln(10);
    }
    if($tag=='H3'){
       	$this->SetFontSize(10);
    	$this->Ln(10);
    }
    if($tag=='H4'){
       	$this->SetFontSize(10);
    	$this->Ln(5);
    }
    if($tag=='H5'){
       	$this->SetFontSize(10);
    	$this->Ln(5);
    }
    if($tag=='H6'){
       	$this->SetFontSize(10);
    	$this->Ln(5);
    }
    if($tag=='B' or $tag=='I' or $tag=='U')
        $this->SetStyle($tag,false);
    if($tag=='A')
        $this->HREF='';
    if($tag=='FONT'){
        if ($this->issetcolor==true) {
            $this->SetTextColor(0);
        }
        if ($this->issetfont) {
            $this->SetFont('arial');
            $this->issetfont=false;
        }
    }
}

function SetStyle($tag,$enable)
{
    //Modifie le style et sélectionne la police correspondante
    $this->$tag+=($enable ? 1 : -1);
    $style='';
    foreach(array('B','I','U') as $s)
        if($this->$s>0)
            $style.=$s;
    $this->SetFont('',$style);
}

function PutLink($URL,$txt)
{
    //Place un hyperlien
    $this->SetTextColor(0,0,255);
    $this->SetStyle('U',true);
    $this->Write(5,$txt,$URL);
    $this->SetStyle('U',false);
    $this->SetTextColor(0);
}

//En-tête
function Header()
{
    //Positionnement à 0,1 cm du haut
    $this->SetY(1);
    //Police Arial italique 8
    $this->SetFont('Arial','I',8);
    //Numéro de page
    $this->Cell(0,10,$this->getWikiPage().' -   Page '.$this->PageNo().'/{nb}',0,0,'C');
    $this->SetY(14);
    $this->SetFont('Arial','',10);

}

//Pied de page
function Footer()
{
    //Positionnement à 1,5 cm du bas
    $this->SetY(-15);
    //Police Arial italique 8
    $this->SetFont('Arial','I',8);
    //Numéro de page
    $this->Cell(0,10,'SupAgro Florac - Page '.$this->PageNo().'/{nb}',0,0,'C');
}

//Chargement des données
function LoadData($file)
{
    //Lecture des lignes du fichier
    $lines=file($file);
    $data=array();
    foreach($lines as $line)
        $data[]=explode(';',chop($line));
    return $data;
}

//Tableau simple
function BasicTable($header,$data)
{
    //En-tête
    foreach($header as $col)
        $this->Cell(40,7,$col,1);
    $this->Ln();
    //Données
    foreach($data as $row)
    {
        foreach($row as $col)
            $this->Cell(40,6,$col,1);
        $this->Ln();
    }
}

//Tableau amélioré
function ImprovedTable($header,$data)
{
    //Largeurs des colonnes
    $w=array(40,35,45,40);
    //En-tête
    for($i=0;$i<count($header);$i++)
        $this->Cell($w[$i],7,$header[$i],1,0,'C');
    $this->Ln();
    //Données
    foreach($data as $row)
    {
        $this->Cell($w[0],6,$row[0],'LR');
        $this->Cell($w[1],6,$row[1],'LR');
        $this->Cell($w[2],6,number_format($row[2],0,',',' '),'LR',0,'R');
        $this->Cell($w[3],6,number_format($row[3],0,',',' '),'LR',0,'R');
        $this->Ln();
    }
    //Trait de terminaison
    $this->Cell(array_sum($w),0,'','T');
}

//Tableau coloré
function FancyTable($header,$data)
{
    //Couleurs, épaisseur du trait et police grasse
    $this->SetFillColor(255,255,0);
    $this->SetTextColor(255);
    $this->SetDrawColor(128,0,0);
    $this->SetLineWidth(.3);
    $this->SetFont('','B');
    //En-tête
    $w=array(40,35,45,40);
    for($i=0;$i<count($header);$i++)
        $this->Cell($w[$i],7,$header[$i],1,0,'C',1);
    $this->Ln();
    //Restauration des couleurs et de la police
    $this->SetFillColor(224,235,255);
    $this->SetTextColor(0);
    $this->SetFont('');
    //Données
    $fill=0;
    foreach($data as $row)
    {
        $this->Cell($w[0],6,$row[0],'LR',0,'L',$fill);
        $this->Cell($w[1],6,$row[1],'LR',0,'L',$fill);
        $this->Cell($w[2],6,number_format($row[2],0,',',' '),'LR',0,'R',$fill);
        $this->Cell($w[3],6,number_format($row[3],0,',',' '),'LR',0,'R',$fill);
        $this->Ln();
        $fill=!$fill;
    }
    $this->Cell(array_sum($w),0,'','T');
}
}

//NL) does not work with wikka
//vérification de sécurité
if (!eregi("wakka.php", $_SERVER['PHP_SELF'])) {
    die ("acc&egrave;s direct interdit");
}


if ($this->HasAccess("read"))
{
            //Instanciation de la classe dérivée
            $pdf=new PDF();
            $pdf->AliasNbPages();
            $pdf->SetMargins(10, 10, 10);
            $pdf->setWikiPage ($this->GetPageTag());
            $pdf->AddPage();
            $pdf->WriteHTML($this->Format($this->page["body"], "wakka"));
            $pdf->Output();
        //}

    //}
}
else
{
    return;
}
?> 
