<?php
//Inclusion de la classe de gestion de SOAP pour les appels distants
include_once dirname(__FILE__).'/../libs/nuSOAP/lib/nusoap.php';
include_once dirname(__FILE__).'/../configuration/HubbleParam.inc';


//récuperation des parametres

$nouvellefenetre = $this->GetParameter("nouvellefenetre");

$formatdate = $this->GetParameter("formatdate");

$template = $this->GetParameter("template");

$template = $this->GetParameter("template");
if (empty($template)) {
	$template = 'liste_basique.tpl.html';
}

$pagination = $this->GetParameter("pagination");

// afficher les erreurs du client SOAP
$DisplayErrors =true;

//Instanciation du Web Service
$Client = new soapclient($GLOBALS["HUBBLE_CLIENT"]["URL_WEBSERVICE"]);

//Récupération de la liste des thématique disponible dans Hubble
$TabThematiques = $Client->call('HubbleListeThematique');

if ($Client->getError() && $DisplayErrors)	echo '<h2>Error</h2><pre>' . $Client->getError() . '</pre>';




//Construction de la liste box des thematiques
$SelectThematiques = "<select name=\"HUBBLE_THEMATIQUE_ID\" onchange=\"document.forms['FrmHubbleClient'].action='?ACTION=THEMATIQUE';document.forms['FrmHubbleClient'].submit();\">";
$SelectThematiques .= "<option value=\"-1\">--- Thematique ---</option>";

for ($i=0;$i<count($TabThematiques);$i++)
{
        $SelectThematiques .= "<option value=\"".$TabThematiques[$i]["thematique_id"]."\" ".($_POST["HUBBLE_THEMATIQUE_ID"]==$TabThematiques[$i]["thematique_id"]?"selected":"").">".$TabThematiques[$i]["thematique_lib"]."</option>";
}
$SelectThematiques .= "</select>";
//Dans le cas ou une th.matique est renseign.e, on affiche la liste des sources pour cette th.matique
if (isset($_POST["HUBBLE_THEMATIQUE_ID"]) && $_POST["HUBBLE_THEMATIQUE_ID"]!=-1) {
        $TabSources = $Client->call('HubbleListeSourceParThematique',array('ListeThematiques'=>$_POST["HUBBLE_THEMATIQUE_ID"]));
}
else {
// Recherche de l'ensemble des sources disponibles
	$TabSources = $Client->call('HubbleListeSource');
}

if ($Client->getError() && $DisplayErrors)	echo '<h2>Error</h2><pre>' . $Client->getError() . '</pre>';


//Construction de la liste des cases à cocher pour les sources disponibles
$TabKey = array();
if (isset($_POST["HUBBLE_SOURCE_ID"])) $TabKey = array_flip($_POST["HUBBLE_SOURCE_ID"]);
for ($i=0;$i<count($TabSources);$i++)
{
	//Si la source est cochées, on la place dans la liste pour une recherche éventuelle et on la recoche
	if (array_key_exists($TabSources[$i]["source_id"],$TabKey))
	{
		$ListeSources .= $Separateur.$TabSources[$i]["source_id"];
		$Separateur=",";
		$Checked="checked";
	}
	else $Checked = "";
	$CheckboxSources .= "<div style=\"float:left;margin:0;padding:0;width:300px;\"><input name=\"HUBBLE_SOURCE_ID[]\" type=\"checkbox\" value=\"".$TabSources[$i]["source_id"]."\" ".$Checked."/>".$TabSources[$i]["source_lib"]."</div>";
}

//Gestion de la recherche
if ($_GET["ACTION"]=="RECHERCHER")
{
	//Un critère a-t-il été saisi ?
	if ($_POST["HUBBLE_CRITERES"]!= "")
	{
		//Au moins une source a-t-elle été cochée ?
		if (count($_POST["HUBBLE_SOURCE_ID"])>0)
		{

			if (!isset($_REQUEST["HUBBLE_RECHERCHE_ID"]))
			{	
				//Parametrage de l'appel distant avec la liste des source sollicitées et les critères de recherche
				$param = array('ListeSources'=>$ListeSources, 'Criteres'=>$_POST["HUBBLE_CRITERES"],null,null);

				//Lancement de la recherche HUBBLE
				$results = $Client->call('HubbleRecherche',$param);			
				if ($Client->getError() && $DisplayErrors)	echo '<h2>Error</h2><pre>' . $Client->getError() . '</pre>';
			}
			else 
			{	
				//Parametrage de l'appel distant avec la liste des source sollicitées et les critères de recherche
				$param = array('Identifiant'=>$_REQUEST["HUBBLE_RECHERCHE_ID"],null,null);

				//Lancement de la recherche HUBBLE
				$results = $Client->call('HubbleRafraichit',$param);			
				if ($Client->getError() && $DisplayErrors)	echo '<h2>Error</h2><pre>' . $Client->getError() . '</pre>';
			}


			//Statut des sources  : pas encore repondu : TIMEWAIT
			$param = array('Identifiant'=>$results[0]);
			$TabInfosSources = $Client->call("HubbleInfosSources",$param);

			$open=0; // Des sources non parvenu : afficher le bouton rafraichir.	
			foreach($TabInfosSources as $statutSources) {
				if  ($statutSources['OPEN']) {
					$open=1;
					break;
				}

			}

			if ($results[1]==0) {	
				echo  "<h3>R&eacute;sultats de la recherche : 0 r&eacute;sultat trouv&eacute;</h3></br>";
			}
			//print_r($results[2]);


			define('MAGPIE_OUTPUT_ENCODING', 'UTF-8');
			define('MAGPIE_DIR', 'tools/federe/libs/');
			define('MAGPIE_CACHE_ON', false);
			require_once(MAGPIE_DIR.'rss_fetch.inc');

			//pour cacher les erreurs Warning de Magpie
			error_reporting(E_ERROR);

			//on vérifie si il existe un dossier pour le cache et si on a les droits d'écriture dessus
			if (file_exists('cache')) {
				if (!is_writable('cache')) {
					$Recherche = "<p class=\"erreur\">Le r&eacute;pertoire \"cache\" n\'a pas les droits d\'acc&egrave;s en &eacute;criture</p>";
				}
			} else {
				$Recherche ="<p class=\"erreur\">Il faut cr&eacute;er un r&eacute;pertoire \"cache\" dans le r&eacute;pertoire principal du wikini.</p>";
			}


			$feed = new MagpieRSS($results[2]);

			if ($feed) {									
				// Gestion du nombre de pages syndiquees
				$i = 0;
				$nb_item = count($feed->items);
				foreach ($feed->items as $item) {					


					foreach($item as $k=>$v) { 
						$item[$k]=fix_latin($v); 

					}   


					$i++;
					$aso_page = array();
					if ($item['link']!='') {
						// Gestion de l'url du site
						$aso_page['url_site'] = htmlentities($feed->channel['link'], ENT_QUOTES, 'UTF-8');
						// Ouverture du lien dans une nouvelle fenetre
						$aso_page['ext'] = $nouvellefenetre;
						//url de l'article	
						$aso_page['url'] = htmlentities($item['link'], ENT_QUOTES, 'UTF-8');
						//titre de l'article						
						$aso_page['titre'] = html_entity_decode(htmlentities($item['title'], ENT_QUOTES, 'UTF-8'), ENT_QUOTES);
						//description de l'article
						$aso_page['description'] = html_entity_decode(htmlentities($item['description'], ENT_QUOTES, 'UTF-8'), ENT_QUOTES);					
						//gestion de la date de publication, selon le flux, elle se trouve parsee à des endroits differents 
						if ($item['pubdate']) {
							$aso_page['datestamp'] = strtotime($item['pubdate']);
						} elseif ($item['dc']['date']) {							
							//en php5 on peut convertir les formats de dates exotiques plus facilement
							if (PHP_VERSION>=5) {
								$aso_page['datestamp'] = strtotime($item['dc']['date']);
							} else {
								$aso_page['datestamp'] = parse_w3cdtf($item['dc']['date']);
							}
						} elseif ($item['issued']) {
							//en php5 on peut convertir les formats de dates exotiques plus facilement
							if (PHP_VERSION>=5) {
								$aso_page['datestamp'] = strtotime($item['issued']);
							} else {
								$aso_page['datestamp'] = parse_w3cdtf($item['issued']);
							}							
						} else {
							$aso_page['datestamp'] = time();
						}							
						if ($formatdate!='') {
							switch ($formatdate) {							
								case 'jm' :
									$aso_page['date'] = strftime('%d.%m', $aso_page['datestamp']);
									break;
								case 'jma' :
									$aso_page['date'] = strftime('%d.%m.%Y', $aso_page['datestamp']);
									break;
								case 'jmh' :
									$aso_page['date'] = strftime('%d.%m %H:%M', $aso_page['datestamp']);
									break;
								case 'jmah' :
									$aso_page['date'] = strftime('%d.%m.%Y %H:%M', $aso_page['datestamp']);
									break;
								default :
									$aso_page['date'] = '';
							}
						}												

						$aso_page['category']=htmlentities($item['category'], ENT_QUOTES, 'UTF-8');
						$aso_page['source']=htmlentities($item['source'], ENT_QUOTES, 'UTF-8');
						$aso_page['author']=htmlentities($item['author'], ENT_QUOTES, 'UTF-8');
						$aso_page['user_tag1']=htmlentities($item['user_tag1'], ENT_QUOTES, 'UTF-8');
						$aso_page['summary']=htmlentities($item['summary'], ENT_QUOTES, 'UTF-8');


						$aso_page['datestamp']=$aso_page['datestamp']+$i;  // Car tout est genere a la meme date
						$syndication['pages'][$aso_page['datestamp']] = $aso_page;
					}
				}
			} else {
				echo '<p class="erreur">Erreur '.magpie_error().'</p>'."\n";        			    
			}			

			// Trie des pages par date
			krsort($syndication['pages']);

			// Gestion des squelettes
			include($template);

			include_once('tools/federe/libs/squelettephp.class.php');
			$squelcomment = new SquelettePhp('tools/federe/presentation/squelettes/'.$template);
			//			$fiches=array('fiches'=>$syndication['pages']);

			//on récupère le nombre d'entrées avant pagination
			if (!empty($pagination)) {
				$fiches['info_res'] = 'Il y a ';
				$nb_result = count($syndication['pages']);
				if ($nb_result<=1) {
					$fiches['info_res'] .= $nb_result.' fiche.'."\n";
				} else {
					$fiches['info_res'] .= $nb_result.' fiches.'."\n";
				}

				// Mise en place du Pager
				require_once 'Pager/Pager.php';
				$params = array(
						'mode'       => 'Jumping',
						'perPage'    => $pagination,
						'delta'      => 12,
						'httpMethod' => 'POST',
						'extraVars' => array_merge($_POST, $_GET),
						'itemData'   => $syndication['pages'],
					       );
				$pager = & Pager::factory($params);
				$syndication['pages'] = $pager->getPageData();
				$fiches['pager_links'] = '<div class="bazar_numero">'.$pager->links.'</div>'."\n";
			} else {
				$fiches['info_res'] = '';
				$fiches['pager_links'] = '';
			}

			$Recherche.= "&nbsp;<a href=\"#\" onclick=\"document.forms['FrmHubbleClient'].action='".$this->Href()."&ACTION=RECHERCHER&HUBBLE_RECHERCHE_ID=".$results[0]."';document.forms['FrmHubbleClient'].submit();\">Poursuivre la recherche ...</a>"; 

			if ($open) {
				$fiches['rafraichir']= "&nbsp;<a href=\"#\" onclick=\"document.forms['FrmHubbleClient'].action='".$this->Href()."&ACTION=RECHERCHER&HUBBLE_RECHERCHE_ID=".$results[0]."';document.forms['FrmHubbleClient'].submit();\">Poursuivre la recherche ...</a>"; 
			}
			$fiches['fiches'] = array();
			foreach ($syndication['pages'] as $fiche) {
				$fiches['fiches'][] = $fiche;

			}

			$squelcomment->set($fiches);

			$searchresult = $squelcomment->analyser();


		}
		else $Recherche = "<p class=\"error_box\">Aucune sources n'a &eacute;t&eacute; s&eacute;lectionn&eacute;e pour la recherche</p>";
	}
	else $Recherche = "<p class=\"error_box\">Aucun crit&egrave;re n'a &eacute;t&eacute; saisi pour la recherche</p>";
}

function init_byte_map(){
	$byte_map = array();
	for($x=128;$x<256;++$x){
		$byte_map[chr($x)]=utf8_encode(chr($x));
	}
	$cp1252_map=array(
			"\x80"=>"\xE2\x82\xAC",    // EURO SIGN
			"\x82" => "\xE2\x80\x9A",  // SINGLE LOW-9 QUOTATION MARK
			"\x83" => "\xC6\x92",      // LATIN SMALL LETTER F WITH HOOK
			"\x84" => "\xE2\x80\x9E",  // DOUBLE LOW-9 QUOTATION MARK
			"\x85" => "\xE2\x80\xA6",  // HORIZONTAL ELLIPSIS
			"\x86" => "\xE2\x80\xA0",  // DAGGER
			"\x87" => "\xE2\x80\xA1",  // DOUBLE DAGGER
			"\x88" => "\xCB\x86",      // MODIFIER LETTER CIRCUMFLEX ACCENT
			"\x89" => "\xE2\x80\xB0",  // PER MILLE SIGN
			"\x8A" => "\xC5\xA0",      // LATIN CAPITAL LETTER S WITH CARON
			"\x8B" => "\xE2\x80\xB9",  // SINGLE LEFT-POINTING ANGLE QUOTATION MARK
			"\x8C" => "\xC5\x92",      // LATIN CAPITAL LIGATURE OE
			"\x8E" => "\xC5\xBD",      // LATIN CAPITAL LETTER Z WITH CARON
			"\x91" => "\xE2\x80\x98",  // LEFT SINGLE QUOTATION MARK
			"\x92" => "\xE2\x80\x99",  // RIGHT SINGLE QUOTATION MARK
			"\x93" => "\xE2\x80\x9C",  // LEFT DOUBLE QUOTATION MARK
			"\x94" => "\xE2\x80\x9D",  // RIGHT DOUBLE QUOTATION MARK
			"\x95" => "\xE2\x80\xA2",  // BULLET
			"\x96" => "\xE2\x80\x93",  // EN DASH
			"\x97" => "\xE2\x80\x94",  // EM DASH
			"\x98" => "\xCB\x9C",      // SMALL TILDE
			"\x99" => "\xE2\x84\xA2",  // TRADE MARK SIGN
			"\x9A" => "\xC5\xA1",      // LATIN SMALL LETTER S WITH CARON
			"\x9B" => "\xE2\x80\xBA",  // SINGLE RIGHT-POINTING ANGLE QUOTATION MARK
			"\x9C" => "\xC5\x93",      // LATIN SMALL LIGATURE OE
			"\x9E" => "\xC5\xBE",      // LATIN SMALL LETTER Z WITH CARON
			"\x9F" => "\xC5\xB8"       // LATIN CAPITAL LETTER Y WITH DIAERESIS
				);
	foreach($cp1252_map as $k=>$v){
		$byte_map[$k]=$v;
	}

	return $byte_map;
}


function fix_latin($instr){

	$byte_map = init_byte_map();

	$ascii_char='[\x00-\x7F]';
	$cont_byte='[\x80-\xBF]';
	$utf8_2='[\xC0-\xDF]'.$cont_byte;
	$utf8_3='[\xE0-\xEF]'.$cont_byte.'{2}';
	$utf8_4='[\xF0-\xF7]'.$cont_byte.'{3}';
	$utf8_5='[\xF8-\xFB]'.$cont_byte.'{4}';

	$nibble_good_chars = "@^($ascii_char+|$utf8_2|$utf8_3|$utf8_4|$utf8_5)(.*)$@s";

	if(mb_check_encoding($instr,'UTF-8'))return $instr; // no need for the rest if it's all valid UTF-8 already
	$outstr='';
	$char='';
	$rest='';
	while((strlen($instr))>0){
		if(1==@preg_match($nibble_good_chars,$instr,$match)){
			$char=$match[1];
			$rest=$match[2];
			$outstr.=$char;
		}elseif(1==@preg_match('@^(.)(.*)$@s',$instr,$match)){
			$char=$match[1];
			$rest=$match[2];
			$outstr.=$byte_map[$char];
		}
		$instr=$rest;
	}
	return $outstr;
}


?>
<html>
<head>
<title>Client Hubble en WebService</title>
<link type="text/css" rel="stylesheet" href="HubbleClient.css">
</head>
<body>
<div id="hubbleclient">
<form name="FrmHubbleClient" action="<?=$this->Href()?>&ACTION=RECHERCHER" method="POST">

<?=$SelectThematiques;?>
</br>
<h3> Liste des sources :</h3>
<div id="sources">
		<?=$CheckboxSources?>
		<div class="clear"></div>
		<a href="#" onclick="$('#sources :checkbox').attr('checked','checked');return false;">Cocher toutes les sources</a>
</div>
<br/>
<input type="text" name="HUBBLE_CRITERES" value="<?=$_POST["HUBBLE_CRITERES"]?>">&nbsp;<input type="submit"" value="Rechercher">&nbsp;<?=$Recherche?><br/>

</form>
</div>
<?=$searchresult;?>
</body>
</html>
