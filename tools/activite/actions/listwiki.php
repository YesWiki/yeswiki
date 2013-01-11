<?php
/*
 * Action : {{listwiki}}
 * Liste les wiki installe depuis la racine du site
 * 
 */


if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}



if (!function_exists("resolveDocumentRoot")) {
	function resolveDocumentRoot() {
	    $current_script = dirname($_SERVER['SCRIPT_NAME']);
	    $current_path   = dirname($_SERVER['SCRIPT_FILENAME']);
	   
	    /* work out how many folders we are away from document_root
	       by working out how many folders deep we are from the url.
	       this isn't fool proof */
	    $adjust = explode("/", $current_script);
	    $adjust = count($adjust)-1;
	   
	    /* move up the path with ../ */
	    $traverse = str_repeat("../", $adjust);
	    $adjusted_path = sprintf("%s/%s", $current_path, $traverse);
	
	    /* real path expands the ../'s to the correct folder names */
	    return realpath($adjusted_path);   
	}
}


$dir_base=resolveDocumentRoot();
$url_base='http://' . $_SERVER['HTTP_HOST'];

if ($dossiers = scandir($dir_base)) {
	
		foreach ($dossiers as $dossier) {
                if (is_dir($dir_base."/".$dossier) && file_exists($dir_base."/".$dossier."/wakka.config.php")) {
						include($dir_base."/".$dossier."/wakka.config.php");
						echo $this->format("[[".$wakkaConfig['base_url']." ".$wakkaConfig['wakka_name']."]]")," ";
						echo $this->format("[[".$wakkaConfig['base_url']."DerniersChangementsRSS/xml (RSS)]]"),"<br />\n";
		
				}
	    }

	    // OPML : TODO generer fichier
	    
	    /*
		echo "<head>" , "\n";
		echo "<title>",$url_base,"</title>" , "\n";                                                                                                                                            
	    echo "</head>" , "\n";
		echo "<body>" , "\n";
	    echo "<outline title=\"",$url_base,"\" text=\"",$url_base,"\">", "\n";
		foreach ($dossiers as $dossier) {
                if (is_dir($dir_base."/".$dossier) && file_exists($dir_base."/".$dossier."/wakka.config.php")) {
						include($dir_base."/".$dossier."/wakka.config.php");
						echo "outline title=\"",$wakkaConfig['wakka_name'],"\" text=\"",$wakkaConfig['wakka_name'],"\" xmlUrl=\"",$wakkaConfig['base_url'],"\"DerniersChangementsRSS/xml\"/>","\n";
				}
	    }
    
 	    echo "</outline>", "\n";                                                                                                                                                                  
    	echo "</body>", "\n";                                                                                                                                                                      
		echo "</opml>";
		*/ 
		
} 
else {
	echo 'Aucun dossier dans ce répertoire.'; 
}



?>