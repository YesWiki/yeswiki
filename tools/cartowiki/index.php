<?php
// index.php
// Administration de l'extension : initialisations (tables, fichier de configuration) , information etc. : toutes
// opérations réservées à l'administrateur technique de Wikini.

// Vérification de sécurité
if (!defined("TOOLS_MANAGER"))
{
        die ("acc&egrave;s direct interdit");
}


// +------------------------------------------------------------------------------------------------------+
// |                                           LISTE de FONCTIONS                                         |
// +------------------------------------------------------------------------------------------------------+


/**Fonction donnerUrlCourante() - Retourne la base de l'url courante.
*
* Cette fonction renvoie la base de l'url courante.
* Origine : fonction provenant du fichier header.php de Wikini version 0.4.1
* Licence : la même que celle figurant dans l'entête du fichier header.php de Wikini version 0.4.1
* ou le fichier install_defaut.inc.php de cette application.
* Auteurs : Hendrik MANS, David DELON, Patrick PAUL, Jean-Pascal MILCENT
*
* @return string l'url courante.
*/
function donnerUrlCourante()
{
    list($url, ) = explode('?', $_SERVER['REQUEST_URI']);
    return $url;
}
/**
 * Removes comment lines and splits up large sql files into individual queries
 *
 * Last revision: September 23, 2001 - gandon
 * Origine : fonction provenant de PhpMyAdmin version 2.6.0-pl1
 * Licence : GNU
 * Auteurs : voir le fichier Documentation.txt ou Documentation.html de PhpMyAdmin.
 *
 * @param   array    the splitted sql commands
 * @param   string   the sql commands
 * @param   integer  the MySQL release number (because certains php3 versions
 *                   can't get the value of a constant from within a function)
 *
 * @return  boolean  always true
 *
 * @access  public
 */
function PMA_splitSqlFile(&$ret, $sql, $release)
{
    // do not trim, see bug #1030644
    //$sql          = trim($sql);
    $sql          = rtrim($sql, "\n\r");
    $sql_len      = strlen($sql);
    $char         = '';
    $string_start = '';
    $in_string    = FALSE;
    $nothing      = TRUE;
    $time0        = time();

    for ($i = 0; $i < $sql_len; ++$i) {
        $char = $sql[$i];

        // We are in a string, check for not escaped end of strings except for
        // backquotes that can't be escaped
        if ($in_string) {
            for (;;) {
                $i         = strpos($sql, $string_start, $i);
                // No end of string found -> add the current substring to the
                // returned array
                if (!$i) {
                    $tab_info = retournerInfoRequete($sql);
                    $ret[] = array('query' => $sql, 'table_nom' => $tab_info['table_nom'], 'type' => $tab_info['type']);
                    return TRUE;
                }
                // Backquotes or no backslashes before quotes: it's indeed the
                // end of the string -> exit the loop
                else if ($string_start == '`' || $sql[$i-1] != '\\') {
                    $string_start      = '';
                    $in_string         = FALSE;
                    break;
                }
                // one or more Backslashes before the presumed end of string...
                else {
                    // ... first checks for escaped backslashes
                    $j                     = 2;
                    $escaped_backslash     = FALSE;
                    while ($i-$j > 0 && $sql[$i-$j] == '\\') {
                        $escaped_backslash = !$escaped_backslash;
                        $j++;
                    }
                    // ... if escaped backslashes: it's really the end of the
                    // string -> exit the loop
                    if ($escaped_backslash) {
                        $string_start  = '';
                        $in_string     = FALSE;
                        break;
                    }
                    // ... else loop
                    else {
                        $i++;
                    }
                } // end if...elseif...else
            } // end for
        } // end if (in string)
       
        // lets skip comments (/*, -- and #)
        else if (($char == '-' && $sql_len > $i + 2 && $sql[$i + 1] == '-' && $sql[$i + 2] <= ' ') || $char == '#' || ($char == '/' && $sql_len > $i + 1 && $sql[$i + 1] == '*')) {
            $i = strpos($sql, $char == '/' ? '*/' : "\n", $i);
            // didn't we hit end of string?
            if ($i === FALSE) {
                break;
            }
            if ($char == '/') $i++;
        }

        // We are not in a string, first check for delimiter...
        else if ($char == ';') {
            // if delimiter found, add the parsed part to the returned array
            $retour_sql = substr($sql, 0, $i);
            $tab_info = retournerInfoRequete($retour_sql);
            $ret[]      = array('query' => $retour_sql, 'empty' => $nothing, 'table_nom' => $tab_info['table_nom'], 'type' => $tab_info['type']);
            $nothing    = TRUE;
            $sql        = ltrim(substr($sql, min($i + 1, $sql_len)));
            $sql_len    = strlen($sql);
            if ($sql_len) {
                $i      = -1;
            } else {
                // The submited statement(s) end(s) here
                return TRUE;
            }
        } // end else if (is delimiter)

        // ... then check for start of a string,...
        else if (($char == '"') || ($char == '\'') || ($char == '`')) {
            $in_string    = TRUE;
            $nothing      = FALSE;
            $string_start = $char;
        } // end else if (is start of string)

        elseif ($nothing) {
            $nothing = FALSE;
        }

        // loic1: send a fake header each 30 sec. to bypass browser timeout
        $time1     = time();
        if ($time1 >= $time0 + 30) {
            $time0 = $time1;
            header('X-pmaPing: Pong');
        } // end if
    } // end for

    // add any rest to the returned array
    if (!empty($sql) && preg_match('@[^[:space:]]+@', $sql)) {
        $tab_info = retournerInfoRequete($sql);
        $ret[] = array('query' => $sql, 'empty' => $nothing, 'table_nom' => $tab_info['table_nom'], 'type' => $tab_info['type']);
    }

    return TRUE;
}

/**
 * Reads (and decompresses) a (compressed) file into a string
 *
 * Origine : fonction provenant de PhpMyAdmin version 2.6.0-pl1
 * Licence : GNU
 * Auteurs : voir le fichier Documentation.txt ou Documentation.html de PhpMyAdmin.
 *
 * @param   string   the path to the file
 * @param   string   the MIME type of the file, if empty MIME type is autodetected
 *
 * @global  array    the phpMyAdmin configuration
 *
 * @return  string   the content of the file or
 *          boolean  FALSE in case of an error.
 */
 
function PMA_readFile($path, $mime = '', $dist=0)
{
    global $cfg;
    if (!$dist) {
	    if (!file_exists($path)) {
    	    return FALSE;
	    }
    }
    switch ($mime) {
        case '':
            return PMA_readFile($path, 'text/plain',$dist);
        case 'text/plain':
        	$content='';
            $file = @fopen($path, 'rb');
            if (!$file) {
                return FALSE;
            }
		    if (!$dist) {
        	    $content = fread($file, filesize($path));
		    }
		    else {
				while (!feof($file)) {
					$content .= fread($file, 8192);
				}
		    }
            fclose($file);
            break;
        default:
           return FALSE;
    }
    return $content;
}

function testerConfig(&$sortie, $texte, $test, $texte_erreur = '', $stop_erreur = 1, $erreur) {
    if ($erreur == 2) {
        return 2;
    }
    	buffer::str($texte.' ');
    if ($test) {
        	buffer::str('<span class="ok">&nbsp;OK&nbsp;</span><br />'."\n");
        return 0;
    } else {
        	buffer::str('<span class="failed">&nbsp;ECHEC&nbsp;</span>');
        if ($texte_erreur) {
            	buffer::str(' <span class="erreur">'.$texte_erreur.'</span>');
        }
        	buffer::str('<br />'."\n") ;
        if ($stop_erreur == 1) {
            return 2;
        } else {
            return 1;
        }
    }
}


/**Fonction retournerInfoRequete() - Retourne le type de requête sql et le nom de la table touchée.
*
* Cette fonction retourne un tableau associatif contenant en clé 'table_nom' le nom de la table touchée
* et en clé 'type' le type de requête (create, alter, insert, update...).
* Licence : la même que celle figurant dans l'entête de ce fichier
* Auteurs : Jean-Pascal MILCENT
*
* @author Jean-Pascal MILCENT <jpm@tela-botanica.org>
* @return string l'url courante.
*/
function retournerInfoRequete($sql)
{
    $requete = array();
    $resultat='';
    if (preg_match('/(?i:CREATE TABLE) +(.+) +\(/', $sql, $resultat)) {
        if (isset($resultat[1])) {
            $requete['table_nom'] = $resultat[1];
        }
        $requete['type'] = 'create';
    } else if (preg_match('/(?i:ALTER TABLE) +(.+) +/', $sql, $resultat)) {
        if (isset($resultat[1])) {
            $requete['table_nom'] = $resultat[1];
        }
        $requete['type'] = 'alter';
    } else if (preg_match('/(?i:INSERT INTO) +(.+) +(?i:\(|VALUES +\()/', $sql, $resultat)) {
        if (isset($resultat[1])) {
            $requete['table_nom'] = $resultat[1];
        }
        $requete['type'] = 'insert';
    } else if (preg_match('/(?i:UPDATE) +(.+) +(?i:SET)/', $sql, $resultat)) {
        if (isset($resultat[1])) {
            $requete['table_nom'] = $resultat[1];
        }
        $requete['type'] = 'update';
    } else if (preg_match('/(?i:DROP TABLE) +(.+) +/', $sql, $resultat)) {
       if (isset($resultat[1])) {
           $requete['table_nom'] = $resultat[1];
       }
       $requete['type'] = 'drop';
	}
    
    return $requete;
}


buffer::str(
'
<h1> Initialisation des données de localisation </h1>
'
);

buffer::str(
'
Etape 1/16 : Suppression table des localites : <a href="tools.php?p=cartowiki&action=suppression&file=drop.sql">Go !</a>&nbsp;
<br/>
Etape 2/16 : Creation table des localites : <a href="tools.php?p=cartowiki&action=creation&file=create.sql">Go !</a>&nbsp;
<br/>
Etape 3/16 : Insertion des localites&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;: <a href="tools.php?p=cartowiki&action=insertion&file=x0">Go !</a>&nbsp;
<br/>
Etape 4/16 : Insertion des localites&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;: <a href="tools.php?p=cartowiki&action=insertion&file=x1">Go !</a>&nbsp;
<br/>
Etape 5/16 : Insertion des localites&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;: <a href="tools.php?p=cartowiki&action=insertion&file=x2">Go !</a>&nbsp;
<br/>
Etape 6/16 : Insertion des localites&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;: <a href="tools.php?p=cartowiki&action=insertion&file=x3">Go !</a>&nbsp;
<br/>
Etape 7/16 : Insertion des localites&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;: <a href="tools.php?p=cartowiki&action=insertion&file=x4">Go !</a>&nbsp;
<br/>
Etape 8/16 : Insertion des localites&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;: <a href="tools.php?p=cartowiki&action=insertion&file=x5">Go !</a>&nbsp;
<br/>
Etape 9/16 : Insertion des localites&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;: <a href="tools.php?p=cartowiki&action=insertion&file=x6">Go !</a>&nbsp;
<br/>
Etape 10/16 : Insertion des localites&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;: <a href="tools.php?p=cartowiki&action=insertion&file=x7">Go !</a>&nbsp;
<br/>
Etape 11/16 : Insertion des localites&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;: <a href="tools.php?p=cartowiki&action=insertion&file=x8">Go !</a>&nbsp;
<br/>
Etape 12/16 : Insertion des localites&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;: <a href="tools.php?p=cartowiki&action=insertion&file=x9">Go !</a>&nbsp;
<br/>
Etape 13/16 : Insertion des localites&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;: <a href="tools.php?p=cartowiki&action=insertion&file=x10">Go !</a>&nbsp;
<br/>
Etape 14/16 : Insertion des localites&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;: <a href="tools.php?p=cartowiki&action=insertion&file=x11">Go !</a>&nbsp;
<br/>
Etape 15/16 : Insertion des localites&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;: <a href="tools.php?p=cartowiki&action=insertion&file=x12">Go !</a>&nbsp;
<br/>
Etape 16/16 : Insertion des localites&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;: <a href="tools.php?p=cartowiki&action=insertion&file=x13">Go !</a>&nbsp;
<br/>
<br/>
Mise à jour&nbsp;: <a href="tools.php?p=cartowiki&action=synchronisation">Go !</a>&nbsp;
'
);

// Utilisation d'un objet Wiki minimaliste pour acces à la base de donnée

$wiki=new Wiki($wakkaConfig);


if (!empty($_REQUEST['action'])) {
	
	switch ($_REQUEST['action']) {


		case 'suppression':
			$sql_contenu = PMA_readFile('tools/cartowiki/locations/'.$_REQUEST['file']);
			$tab_requete_sql = array();
			PMA_splitSqlFile($tab_requete_sql, $sql_contenu, '');
			
			foreach ($tab_requete_sql as $value) {
			    $table_nom = '';
			    if (!empty($value['table_nom'])) {
					$table_nom = $value['table_nom'];
			    }
			    $requete_type = '';
			    if (!empty($value['type'])) {
					$requete_type = $value['type'];
			    }
			    if ($requete_type == 'drop') {
					mysql_query($value['query'], $wiki->dblink);
			    }
			}
				
			break;
			


	
		case 'creation':
			$sql_contenu = PMA_readFile('tools/cartowiki/locations/'.$_REQUEST['file']);
			$tab_requete_sql = array();
			PMA_splitSqlFile($tab_requete_sql, $sql_contenu, '');
			
			foreach ($tab_requete_sql as $value) {
			    $table_nom = '';
			    if (!empty($value['table_nom'])) {
					$table_nom = $value['table_nom'];
			    }
			    $requete_type = '';
			    if (!empty($value['type'])) {
					$requete_type = $value['type'];
			    }
			    if ($requete_type == 'create') {
					mysql_query($value['query'], $wiki->dblink);
			    }
			}
				
			break;
			
		case 'insertion':
			$sql_contenu = PMA_readFile('tools/cartowiki/locations/'.$_REQUEST['file']);
			$tab_requete_sql = array();
			PMA_splitSqlFile($tab_requete_sql, $sql_contenu, '');
			
			foreach ($tab_requete_sql as $value) {
			    $table_nom = '';
			    if (!empty($value['table_nom'])) {
					$table_nom = $value['table_nom'];
			    }
			    $requete_type = '';
			    if (!empty($value['type'])) {
					$requete_type = $value['type'];
			    }
			    if ($requete_type == 'insert') {
					 mysql_query($value['query'], $wiki->dblink);
			    }
			}	
			break;
			
		case 'synchronisation':
			// Recherche derniere date mise à jour
			 $last = $wiki->LoadSingle("select max(update_date) as update_date from locations");
			 $sql_contenu = PMA_readFile("http://www.onem-france.org/saga/synchro.php?date=".urlencode($last['update_date']),'',1);
			 $tab_requete_sql = array();
			PMA_splitSqlFile($tab_requete_sql, $sql_contenu, '');
			
			foreach ($tab_requete_sql as $value) {
			    $table_nom = '';
			    if (!empty($value['table_nom'])) {
					$table_nom = $value['table_nom'];
			    }
			    $requete_type = '';
			    if (!empty($value['type'])) {
					$requete_type = $value['type'];
			    }
			    if ($requete_type == 'insert') {
					 mysql_query($value['query'], $wiki->dblink);
			    }
			}	
			 
			break;
			
		default:
			break;	
		
	}
	
}

$tab_requete_sql = array();

PMA_splitSqlFile($tab_requete_sql, $sql_contenu, '');
foreach ($tab_requete_sql as $value) {
	break;
    $table_nom = '';
    if (!empty($value['table_nom'])) {
	$table_nom = $value['table_nom'];
    }
    $requete_type = '';
    if (!empty($value['type'])) {
	$requete_type = $value['type'];
    }
    if ($requete_type == 'create') {
	$erreur = testerConfig( $sortie_verif, 'Création table '.$table_nom.'...', @mysql_query($value['query'], $dblink), 
				'Déjà créée ?', 0, $erreur);
    } else if ($requete_type == 'alter') {
	$erreur = testerConfig( $sortie_verif, 'Modification structure table '.$table_nom.'...', @mysql_query($value['query'], $dblink), 
				'Déjà modifiée ?', 0, $erreur);
    } else if ($requete_type == 'insert') {
    	continue;
	//$erreur = testerConfig( $sortie_verif, 'Insertion table '.$table_nom.'...', @mysql_query($value['query'], $dblink), 
		//		'Données déjà présente ?', 0, $erreur);
    }
}



?>
