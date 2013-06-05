<?php
@session_start();
//--------------------------------------------------------------------------
// LANGUE

if (strtoupper($GLOBALS["spip_lang"])!="") 
	$_SESSION["LANGUE"] = strtoupper($GLOBALS["spip_lang"]);
else
	$_SESSION["LANGUE"] = "FR";


if ($_SESSION["SGBD_BASE"] == "")
{
	
	//CrÈation des chemins universels
	$_SESSION["PATH_ROOT"]= substr(__FILE__,0,strlen(__FILE__)- strlen("modules/Commun/Commun.inc"));
	$_SESSION["PATH_COMPOSANT"]	= $_SESSION["PATH_ROOT"]."modules/";

	//CrÈation des URL universelles
	$_SESSION["URL_ROOT"] = "http://".$_SERVER['HTTP_HOST'].substr($_SESSION["PATH_ROOT"], strlen($_SERVER['DOCUMENT_ROOT']), strlen($_SESSION["PATH_ROOT"])-strlen($_SERVER['DOCUMENT_ROOT'])) ;

	if (substr($_SESSION["URL_ROOT"], -1, 1) != "/") $_SESSION["URL_ROOT"] .= "/";
	
	$_SESSION["URL_COMPOSANT"]	= $_SESSION["URL_ROOT"]."modules/";
	
	$_SESSION["PATH_OUTILS"] = $_SESSION["PATH_COMPOSANT"]."Commun/";
}


//--------------------------------------------------------------------------
// Inclusion du fichier de gestion de la base de donnÈes
require_once($_SESSION["PATH_COMPOSANT"]."Commun/MySql.inc");

//USER SPIP
if (isset($GLOBALS["auteur_session"]["login"])) 
{ 
	$_SESSION["SPIP_USER_LOGIN"]		= $GLOBALS["auteur_session"]["login"];
	$_SESSION["SPIP_USER_EMAIL"]		= $GLOBALS["auteur_session"]["email"];
}

//DÈclenche l'Ècriture du Log dans /Commun/Log/Hubble.txt
$_SESSION["LOG_DEBUG"]	= false;

if ($_SESSION["SGBD_BASE"] == "")
{
	// Inclusion du fichier de paramÍtrage de la base 
	include($_SESSION["PATH_ROOT"]."ecrire/include/bd/inc_config_metier.php");
	//--------------------------------------------------------------------------
	// SGBD
	$_SESSION["SGBD_BASE"]				= $dbName; 
	$_SESSION["SGBD_SERVER"]			= $dbHost; 
	$_SESSION["SGBD_USER"]				= $dbLogin; 
	$_SESSION["SGBD_PASSWORD"]			= $dbPassword;
	
	$_SESSION["APPLICATION_NOM"]		= "Suite SIST";

	//--------------------------------------------------------------------------
	// MAIL
	$_SESSION["MAIL_EMAIL_WEBMASTER"]			= GetParametre("email_webmaster"); 
	//Definit si les mails sont envoyÈs ou non
	$_SESSION["EMAIL"] = true;
	//Adresse du server de mail
	$_SESSION["EMAIL_SERVER"] = "localhost";
	//type de serveur de mail (mail,sendmail ou smtp)
	$_SESSION["EMAIL_TYPE"] = "mail";
	//permet d'activer ou non l'identification par login/pwd sur le serveur de mail
	$_SESSION["EMAIL_ACTIVE_IDENTIFICATION"] = false;
	//login ‡ utiliser pour envoyer un mail
	$_SESSION["EMAIL_SERVER_LOGIN"] = "";
	//password ‡ utiliser pour envoyer un mail
	$_SESSION["EMAIL_SERVER_PWD"] = "";
	}// end if // Session dÈj‡ initialisÈe


//Fonction de chronomÈtrage		
function getmicrotime()
{ 
	list($usec, $sec) = explode(" ",microtime()); 
	return ((float)$usec + (float)$sec); 
} 

//Uniquement pour la compatibilitÈ framework.
function AjouteHEAD($Texte)
{
	return $Texte;
}

//Affiche un texte en fonction de son style
function AfficheTexte($Texte,$Style="verdana2")
{
 return "<FONT class=\"".$Style."\">".$Texte."</FONT>";
}

function BuildQueryContext($querystring, $uri=null, $avoid=null) 
{
   $result = '';
   $beginning = '';
   $ending = '';
   
   //Si l'URL passÈe en paramËtre est nulle on prend celle par dÈfaut
   if (is_null($uri)) 
   {//Piece together uri string
       $beginning = $_SERVER['PHP_SELF'];
       $ending = ( isset($_SERVER['QUERY_STRING']) ) ? $_SERVER['QUERY_STRING'] : '';
   } 
   //sinon on analyse l'URL passÈe en paramËtre pour extraire la querystring
   else 
   {
       $qstart = strpos($uri, '?');
       if ($qstart === false) {
           $beginning = $uri; //$ending is '' anyway
       } else {
           $beginning = substr($uri, 0, $qstart);
           $ending = substr($uri, $qstart);
       }
   }
   
   //Beginning correspond ‡ la partie de l'URL situÈe avant le ?
   //Ending correspond ‡ la querystring.
   
   //S'il existe dÈj‡ une querystring ‡ traiter
   if (strlen($ending) > 0) {
       $vals = array();
       $ending = str_replace('?','', $ending);
       
       //Mise en tableau de la querystring courante
       parse_str($ending, $tabUri);
       //Mise en tableau de la querystring ‡ fusionner
       parse_str($querystring, $tabQuery);

	   //Traitement du mot-clefs ACTION pour ne garder les actions que du module courant
	   $posAction = strpos($querystring,"ACTION");
	   //Dans le cas o˘ une action a ÈtÈ trouvÈe dans les ÈlÈments ‡ fusionner on va enlever les actions
	   //qui ne correspondent pas au module courant
	   if ($posAction !== false) 
	   {
	   		//RÈcupÈration du nom du module courant
	   		$posEgal = strpos($querystring,"=",$posAction);
	   		$nomModule = substr($querystring,$posAction+6,$posEgal-($posAction+6));
	   		
	   		//parcours des entrees de la querystring existante pour en supprimer les actions qui ne font pas parties du module courant.
	   		$tabKeyUri = array_keys($tabUri);
			for ($i=0;$i<count($tabKeyUri);$i++)
			{
				if (strpos($tabKeyUri[$i],"ACTION")!== false && $tabKeyUri[$i] != "ACTION".$nomModule)
				unset ($tabUri[$tabKeyUri[$i]]);
			}		
	   }
       //Fusion de la querystring courante et de la nouvelle
	   $tabTemp = array_merge($tabUri,$tabQuery);
	   
	   
	   //traitement des entrÈes ‡ supprimer
	   if (!is_null($avoid)) 
	   { 
	   		//Mise en tableau des entrÈes ‡ supprimer
	   		$tabAvoid = explode(",",$avoid);
	   		//RÈcupÈration des clefs de la futures Querystring
	   		$tabKey = array_keys($tabTemp);
	   		
	   		//Pour chaque clef on vÈrifie qu'elle n'existe pas dans les entrÈes ‡ supprimer
	   		for ($i=0;$i<count($tabKey);$i++)
	   		{
	   		   //parcours des clefs ‡ supprimer
	   		   for ($j=0;$j<count($tabAvoid);$j++)
	   		   {
	   		   		//la clef a ÈtÈ trouvÈ on arrÍte l'itÈration
	   		   		if ($tabAvoid[$j]==$tabKey[$i]) break;
	   		   }
	   		   //Si le for n'a pas ÈtÈ jusqu'au bout, la clef doit Ítre supprimÈe
	   		   if ($j>=count($tabAvoid))
	   		   {
	   		   	 $tabFinal[$tabKey[$i]]=$tabTemp[$tabKey[$i]];
	   		   }
	   		}
	   }
	   else 
	   {
	   	 $tabFinal = $tabTemp;
	   }
		 
       $ending = '';
       $count = 0;
       //Parcours du tableau final et construction de la nouvelle url
       foreach($tabFinal as $k => $v) {
           if ($count > 0) { $ending .= '&'; }
           else { $count++; }
           
           //Dans le cas ou la valeur est un tableau, il faut Èclater les clefs
           if (is_array($v))
           {
           		for ($i=0;$i<count($v);$i++)
           		{
           			$ending .= $separateur.$k."[]=".@urlencode($v[$i]);
           			$separateur="&";
           		}
           }
           else 
	           $ending .= $k."=".@urlencode($v);
       }
   } else {
       $ending = $querystring;
   }
   
   $result = $beginning . '?' . $ending;
   
   return $result;
} 

function AfficheTitreComposant($Texte,$Param="")
{
  if ($Param != "") $Texte = ChaineParam($Texte,$Param);
	return "<TABLE BORDER=\"0\ CELLPADDING=\"0\" CELLSPACING=\"0\" WIDTH=\"100%\"><TR bgcolor=\"#EEEECC\"><TD><FONT color=\"##000000\" face=\"Verdana\" style=\"font-size:14px; font-weight:bold;\">".$Texte."</FONT></TD></TR></TABLE><BR>";
}

function AfficheErreurComposant($Texte,$Param="")
{
  if ($Param != "") $Texte = ChaineParam($Texte,$Param);
	return "<TABLE BORDER=\"0\" CELLPADDING=\"0\" CELLSPACING=\"0\"><TR><TD><IMG SRC=\"".$_SESSION["URL_COMPOSANT"]."Hubble/Template/Images/Picto-Erreur.gif\" HSPACE=\"10\"></TD><TD><FONT color=\"#990000\" face=\"Arial\" style=\"font-size:11px\">".$Texte."</FONT></TD></TR></TABLE><BR>";
}

function AfficheAlerteComposant($Texte,$Param="")
{
  if ($Param != "") $Texte = ChaineParam($Texte,$Param);
	return "<TABLE BORDER=\"0\ CELLPADDING=\"0\" CELLSPACING=\"0\"><TR><TD><IMG SRC=\"".$_SESSION["URL_COMPOSANT"]."Hubble/Template/Images/Picto-Alerte.gif\" HSPACE=\"10\"></TD><TD><FONT color=\"#BF963C\" face=\"Arial\" style=\"font-size:11px\">".$Texte."</FONT></TD></TR></TABLE><BR>";
}



function AfficheMessageComposant($Texte,$Param=NULL)
{
  $Texte = ChaineParam($Texte,$Param);
  return "<TABLE BORDER=\"0\ CELLPADDING=\"0\" CELLSPACING=\"0\"><TR><TD><IMG SRC=\"".$_SESSION["URL_COMPOSANT"]."Hubble/Template/Images/Picto-Message.gif\" HSPACE=\"10\"></TD><TD><FONT color=\"##000099\" face=\"Arial\" style=\"font-size:11px\">".$Texte."</FONT></TD></TR></TABLE><BR>";
}



//Remplace les %% d'une chaine par les paramËtres passÈs ‡ la fonction sous forme de chaine : "....##....##..."
function ChaineParam($Template,$Param = NULL,$Split="%%")
{			
	   // Convertion du $Param
	   if (is_null($Param)) $Param = "";
	   else if(is_array($Param)) $Param = implode($Param, "##");

       if ($Param != "")
       {

                $TabTemplate = split($Split,$Template);
                $TabParam = split('##',$Param);
                $LgParam = count($TabParam);

                for ($i=0;$i<count($TabTemplate);$i++)
                {
                   if ($i < $LgParam)
                   {
                       $Chaine .= $TabTemplate[$i].$TabParam[$i];
                   }
                   else
                   {
                       $Chaine .= $TabTemplate[$i];
                   }
                }

                return $Chaine;
       }
       else
       {
           return str_replace($Split,"",$Template);
       }
}	
//Remplace les caractËres quotÈs lors de la rÈception d'une MLE par url ou post (BS pour Back Slash)
Function GereBS( $Str )
{
 $Str = Str_Replace( "\\'", "'", $Str );
 $Str = Str_Replace( "\\&", "&", $Str );
 $Str = Str_Replace( "\\\"", "\"", $Str );
 $Str = Str_Replace( "\\\\", "\\", $Str );
 return trim( $Str );
}	
//Remplace les caractËres Back Slash et les quotes par &quot;
Function GereBSQOut( $Str )
{
 $Str = Str_Replace( "\"", "&quot;",GereBS($Str));
Return $Str;
}				
//Remplace les caractËres Back Slash et &quot; par "
Function GereBSQIn( $Str )
{
 $Str = Str_Replace( "&quot;","\"",GereBS($Str));
Return $Str;
}
Function GereQOut( $Str )
{
 $Str = Str_Replace( "\"", "&quot;",$Str);
Return $Str;
}				
//Remplace les caractËres Back Slash et &quot; par "
Function GereQIn( $Str )
{
 $Str = Str_Replace( "&quot;","\"",$Str);
Return $Str;
}
//Remplace les retours chariots par des espaces
Function GereRetourChariot( $Str )
{
	$Str = str_replace(array("\r", "\n"), array(' ', ' '), $Str);
	return $Str;	
}

//COnstruit une ListeBox
Function ConstruitSelect($NameId,$Params,$TabOptions,$IndexValue,$IndexLibelle,$DefaultValue="",$PremierLibelle="")
{
   $Chaine = "<SELECT NAME=\"$NameId\" ID=\"$NameId\" $Params>";
   if ($PremierLibelle!="")
   {
     $Chaine .= "<OPTION Value=\"-1\">$PremierLibelle</OPTION>";
   }
   
   for($i=0;$i<count($TabOptions);$i++)
    {
				//Traitement ou la valeur par dÈfaut est une liste de valeur multiple
				if (is_array($DefaultValue))
			{
			 	 for ($j=0;$j<count($DefaultValue);$j++)
				 {
				 		 if ($TabOptions[$i][$IndexValue] == $DefaultValue[$j]) break;
				 }
				 if ($j<count($DefaultValue))
				 {
									$Chaine .= "<OPTION Value=\"".$TabOptions[$i][$IndexValue]."\" selected>".$TabOptions[$i][$IndexLibelle]."</OPTION>";
				 }
				 else
				 {
                          $Chaine .= "<OPTION Value=\"".$TabOptions[$i][$IndexValue]."\">".$TabOptions[$i][$IndexLibelle]."</OPTION>";
				 }
			}
			else
			{ 
                 if ($TabOptions[$i][$IndexValue] == $DefaultValue)
                 {
       					 					$Chaine .= "<OPTION Value=\"".$TabOptions[$i][$IndexValue]."\" selected>".$TabOptions[$i][$IndexLibelle]."</OPTION>";
                 }
                 else
                 {
                          $Chaine .= "<OPTION Value=\"".$TabOptions[$i][$IndexValue]."\">".$TabOptions[$i][$IndexLibelle]."</OPTION>";
                 }
			}
    }
   $Chaine .= "</SELECT>";
   return $Chaine;
}


function GetParametre($Field)
{
  	//Recherche du parametre dans la base
    $COLUMN = array("valeur");
    $TABLE = "spip_meta";
    $WHERE = "nom='".CHAINE_MYSQL($Field)."'";

	$Recordset = SELECT_TABLE($_SESSION["SGBD_BASE"],$TABLE,$COLUMN,$WHERE);
			
	return $Recordset[0][0];
}

function afficherMailto($Email, $Link=false, $Libelle='', $Subject='', $Body='') {

	$IdFonctionJs = rand(11111, 99999);
	$ExplodeMail = explode("@", $Email);
	$Login = $ExplodeMail[0];
	list ($Domaine, $Extension) = explode(".", $ExplodeMail[1]);
	$Arobace = "@";
	
	$Javascript ="
	<script language=\"javascript\">
	var Email_$IdFonctionJs = '$Login' + '$Arobace' + '$Domaine' + '.' + '$Extension';
	";
	
	//--- Construction du lien de mail -------------------------------
	
	// Pas de lien : mail affichÈ en texte simple	
	if (!$Link) {
		$HtmlEmail = "Email_$IdFonctionJs";
	}
	// Si ya un lien, on le compose
	else {
		
		$Html = "<a href=\"mailto:'+ Email_$IdFonctionJs";
		
		$Html .= "+'?subject=".$Subject;
		// Soi les 2 sont remplis et on les concatËne
		if ($Body !='') {
			$Html .= "&body=".$Body;
		}
		
		$Html .="\" >";
		$Html .= ($Libelle !='') ? $Libelle : $Email;
		$Html .= "</a>";
		//$Javascript .= "var Html_$IdFonctionJs = '$Html'";		
		
	}
	//-----------------------------------------------------------------
	
	$Javascript .= "
	var Html_$IdFonctionJs = '$Html';
	document.write(Html_$IdFonctionJs);
	</script>
	";
	
	return str_replace(array("\n", "\r", "\t"), array("","",""), $Javascript);
}


function EcritLog($Fichier,$Log)
{
	if ($_SESSION['LOG_DEBUG'])
	{
		//Ecriture du fichier dans le dossier des caches					
	  	$out = fopen($_SESSION["PATH_COMPOSANT"]."Commun/Log/".$Fichier, "a+");
		fwrite($out, "\n".date("Y-m-d H:i:s")." - ".$Log);
		//flock($out,3);
		fclose($out);
	}
}


function supprimerAccents($chaine) {
		return( strtoupper(strtr( $chaine,
"¿¡¬√ƒ≈‡·‚„‰Â“”‘’÷ÿÚÛÙıˆ¯»… ÀËÈÍÎ«ÁÃÕŒœÏÌÓÔŸ⁄€‹˘˙˚¸ˇ—Ò",
"AAAAAAaaaaaaOOOOOOooooooEEEEeeeeCcIIIIiiiiUUUUuuuuyNn" ) ));
}


//Supprime une clef d'un tableau ou
//Supprime un tableau de clef d'un tableau
//$key peut Ítre un index de clefs ‡ supprimer ou un tableau d'index de clefs ‡ supprimer
function ArrayRemove( $array, $key )
{
   	$i = 0;
   	$output = array();
   	for($j=0;$j<count($array);$j++)
   	{
   		if (is_array($key))
   		{
   			if (!in_array($j,$key))
   			{
   				$output[$i++] = $array[$j];
   			}
   		}
   		else
   		{
	   		if ( $j != $key )
	   		{
	   			$output[$i++] = $array[$j];
	  		}
   		}
  	}
  	return $output;
}
	
 function getIdHomepageFR() {
 	
 	$MotCle = "HOMEPAGE_FR";
 	$TableMots = "spip_mots";
 	$TableLiaison = "spip_mots_rubriques";
 	$IdRubriqueFR = "";
 	
	// rÈcupÈration de l'ID du mot clÈ HOMEPAGE_FR
	$where = $TableMots.".titre='".$MotCle."'";
	$resultFR = SELECT_TABLE($_SESSION['SGBD_BASE'], "spip_mots", array("id_mot"), $where);
	$IdMotCleFR = $resultFR[0]['id_mot'];
	 
	// rÈcupÈration de l'ID de la rubrique associÈe au mot clÈ HOMEPAGE_FR
	$where = $TableLiaison.".id_mot=".$IdMotCleFR;
	$resultFR = SELECT_TABLE($_SESSION['SGBD_BASE'], "spip_mots_rubriques", array("id_rubrique"), $where);
	$IdRubriqueFR = $resultFR[0]['id_rubrique'];
	return (($IdRubriqueFR!="") ? $IdRubriqueFR : 46 );
 }
 
//Tronque une chaine ‡ la longueur passÈe en parametre.
//Tronque sur un espace en nom au milieu d'un mot
//Si $End est renseignÈe, le rajoute ‡ la fin de la chaine ex: $End="..." 
function TruncateString($String,$Length,$End="")
{
 	if (strlen($String)>$Length)
	{
  	$String = substr($String,0,$Length);
          $pos = strrpos($String, " ");
          if ($pos === false)
  	{
  		 return $String.$End;
  	}
  	else
  	{
  	 	$String = substr($String,0,$pos);
  		return $String.$End;
  	}
	}
	else
	{
	  return $String;
	}				
}
 
function genereRandomChaine($Longueur)
{
	$pw = ''; //intialize to be blank
	for($i=0;$i<$Longueur;$i++)
	{
		switch(rand(1,3))
		{
			case 1: $pw.=chr(rand(48,57));  break; //0-9
			case 2: $pw.=chr(rand(65,90));  break; //A-Z
			case 3: $pw.=chr(rand(97,122)); break; //a-z
		}
	}
	return $pw;
}

 function getIdHomepageEN() {
 	
 	$MotCle = "HOMEPAGE_EN";
 	$TableMots = "spip_mots";
 	$TableLiaison = "spip_mots_rubriques";
 	$IdRubriqueEN = "";
 	
	// rÈcupÈration de l'ID du mot clÈ HOMEPAGE_EN
	$where = $TableMots.".titre='".$MotCle."'";
	$resultEN = SELECT_TABLE($_SESSION['SGBD_BASE'], "spip_mots", array("id_mot"), $where);
	$IdMotCleEN = $resultEN[0]['id_mot'];
	
	// rÈcupÈration de l'ID de la rubrique associÈe au mot clÈ HOMEPAGE_EN
	$where = $TableLiaison.".id_mot=".$IdMotCleEN;
	$resultEN = SELECT_TABLE($_SESSION['SGBD_BASE'], "spip_mots_rubriques", array("id_rubrique"), $where);
	$IdRubriqueEN = $resultEN[0]['id_rubrique'];
	return (($IdRubriqueEN!="") ? $IdRubriqueEN : 47 );
}	


?>
