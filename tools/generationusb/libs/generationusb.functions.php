<?php

function read_all_files($root = '.'){
	$files  = array('files'=>array(), 'dirs'=>array());
	$directories  = array();
	$last_letter  = $root[strlen($root)-1];
	$root  = ($last_letter == '\\' || $last_letter == '/') ? $root : $root.DIRECTORY_SEPARATOR;

	$directories[]  = $root;

	while (sizeof($directories)) {
	$dir  = array_pop($directories);
	if ($handle = opendir($dir)) {
	  while (false !== ($file = readdir($handle))) {
		// TODO: voir s'il faut traiter les .htaccess .htpasswd et revoir les tests sur les . et ..
		if ($file == '.' || $file == '..' || $file == 'CVS' || substr($file,0,1)=='.') {
		  continue;
		}
		$file  = $dir.$file;
		if (is_dir($file)) {
		  $directory_path = $file.DIRECTORY_SEPARATOR;
		  array_push($directories, $directory_path);
		  $files['dirs'][]  = $directory_path;
		} elseif (is_file($file)) {
		  $files['files'][]  = $file;
		}
	  }
	  closedir($handle);
	}
	}

	return $files;
} 


/* backup the db OR just a table */
function backup_tables($host,$user,$pass,$name,$tables = '*')
{
	$return = '';
	$link = mysql_connect($host,$user,$pass);
	mysql_select_db($name,$link);
	
	//get all of the tables
	if($tables == '*')
	{
		$tables = array();
		$result = mysql_query('SHOW TABLES');
		while($row = mysql_fetch_row($result))
		{
			$tables[] = $row[0];
		}
	}
	else
	{
		$tables = is_array($tables) ? $tables : explode(',',$tables);
	}
	
	//cycle through
	foreach($tables as $table)
	{
		$result = mysql_query('SELECT * FROM '.$table);
		$num_fields = mysql_num_fields($result);
		$return.= 'DROP TABLE IF EXISTS '.$table.';';
		$row2 = mysql_fetch_row(mysql_query('SHOW CREATE TABLE '.$table));
		$return.= "\n\n".$row2[1].";\n\n";
		
		for ($i = 0; $i < $num_fields; $i++) 
		{
			while($row = mysql_fetch_row($result))
			{
				$return.= 'INSERT INTO '.$table.' VALUES(';
				for($j=0; $j<$num_fields; $j++) 
				{
					$row[$j] = addslashes($row[$j]);
					$row[$j] = ereg_replace("\n","\\n",$row[$j]);
					if (isset($row[$j])) { $return.= '"'.$row[$j].'"' ; } else { $return.= '""'; }
					if ($j<($num_fields-1)) { $return.= ','; }
				}
				$return.= ");\n";
			}
		}
		$return.="\n\n\n";
	}
	
	//return file content
	return $return;
}

function checkportabledb($host,$user,$pass,$name)
{
	$fichier_sql = 'portable-mysql-dump.sql';
	if (file_exists($fichier_sql)) {
		$link = mysql_connect($host,$user,$pass);
		mysql_select_db($name,$link);
		// Des champs textes sont multilignes, d'ou la boucle sur INSERT, marqueur de fin de la requete precedente.
		if ($tables = file($fichier_sql))  {
			foreach ($tables AS $ligne) {
				if ($ligne[0] != "-" && $ligne[0] != "") {
					$req .= $ligne;
					//Permet de repérer quand il faut envoyer l'ordre SQL...
					$test = explode(";", $ligne);
					if (sizeof($test) > 1) {
						$finRequete = true;
					}
				}
				if ($finRequete) {
					$resultat = mysql_query($req);			
					$req = "";
					$finRequete = false;
				}
			}
		}
		unlink("portable-mysql-dump.sql");
	}
	return;
}

?>
