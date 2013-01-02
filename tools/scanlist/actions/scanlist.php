<?php
/*
scanlist.php
*/

// Vérification de sécurité
if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}

require_once('tools/scanlist/configuration/config.inc.php');

function ListDir($dir){
   if($checkDir = opendir($dir)){
       while($file = readdir($checkDir)){
           if($file != "." && $file != ".."){
               $listDir[] = $file;
           }
       }
       if ($listDir) sort($listDir);
       return $listDir;
   }
   return array();
}

$cachefile = 'tools/scanlist/CACHE/scanlist.txt';
if ((!isset($_REQUEST['refresh']) || $_REQUEST['refresh']!=1)) {
	if (file_exists($cachefile) ) {
    	include($cachefile);
   		echo "<!-- Cached copy, generated ".date('H:i', filemtime($cachefile))." -->\n";
   		return;
	}
}

ob_start(); //  Gestion du cache

foreach ($unite_sauvegarde as $unite) {
	list ($rep_unite,$herbier) = $unite; 
	// Unite de sauvegarde 
	if ($unites = ListDir($rep_unite)) {
   	  	foreach ($unites as $file_unite) {
      		 if ($file_unite==$herbier) {
	      		$rep_herbier=$rep_unite.$file_unite;
   	  		 // Code herbier 
				if ($herbiers = ListDir($rep_herbier)) {
			   	    foreach ($herbiers as $file_herbier) {	
			      		 if (is_dir($rep_herbier.DIRECTORY_SEPARATOR.$file_herbier)) {
				      		$rep_classement=$rep_herbier.DIRECTORY_SEPARATOR.$file_herbier;
				      		$min=intval($file_herbier);
				      		$max=$min+999;
							echo $this->Format("===".$file_unite." de ".$min." à ".$max.'""<br>""'."===");
			   	  		 // Scan
							if ($scans = ListDir($rep_classement)) {
								foreach ($scans as $file_scan) {
						      		if (is_file($rep_classement.DIRECTORY_SEPARATOR.$file_scan)) {
							      		$rep_scan=$rep_classement.DIRECTORY_SEPARATOR;
							      		$file_scan_tif=ereg_replace('\.bz2','',$file_scan);
							      		$file_scan_rep=ereg_replace('\.tif\.bz2','',$file_scan);
							      		echo $this->Format('[['.URL_SCAN.DIRECTORY_SEPARATOR.$file_scan_rep.' '.$file_scan_rep.']]'); 
							      		if (!file_exists($rep_unite.'CACHE'.DIRECTORY_SEPARATOR.$file_unite.DIRECTORY_SEPARATOR.$file_herbier.DIRECTORY_SEPARATOR.$file_scan_rep.DIRECTORY_SEPARATOR.'ImageProperties.xml')) {
							      			echo $this->Format('""&nbsp;""Zoom absent !');
							      		}
							      		echo $this->Format('""<br>""'); 
						      		}
						   	  	}
							}
			      		}
			   	  	}
				}
      		}
   	  	}
	}
}

echo "<br>";
echo "<a href=\"".$this->Href()."&refresh=1\">*</a>";
	
$fp = fopen($cachefile, 'w');
$scanlist_output = ob_get_contents();
fwrite($fp, $scanlist_output);
fclose($fp);
ob_end_clean();
echo $scanlist_output;

?>
