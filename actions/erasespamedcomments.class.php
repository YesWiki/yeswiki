<?php
/**
 * Action permettant d'effacer facilement les spams de commentaires
 * (pour WikiNi 0.5 et sup�rieurs)
 * 
 * Cette action accepte les param�tres :
 * -- "max" permettant de limiter le nombre de commentaires affich�s
 * -- "logpage" permettant de sp�cifier la page o� sont enregistr�es
 *    les suppressions effectu�es
 * Exemple d'utilisation : {{erasespamedcomments max="50"}}
 * 
 * @version $Id: erasespamedcomments.class.php 859 2007-11-22 01:07:26Z nepote $
 * @author Charles N�pote <charles@nepote.org>
 * @author Didier�Loiseau <l.farquaad@gmail.com>
 * @copyright Copyright &copy; 2006, 2007 Charles N�pote
 * @license License GPL.
 * 
 * @todo
 * -- pour garantir une certaine transparence, option d'envoi par mail des contenus effac�s (?)
 *    (via une m�thode appel�e NotifyAdmin())
 * -- id�alement la derni�re page affiche les r�sultats mais ne renettoie
 *    pas les commentaires si elle est recharg�e
 * -- test pour savoir si quelque chose a bien �t� effac�
 * -- la pr�sentation (style, param�trage de limite du nombre de commentaires affich�s,
 *    param�trage de la longueur des contenus affich�s, etc.)
 * 
 * 
*/


// V�rification de s�curit�
if (!defined('WIKINI_VERSION'))
{
	die ('acc&egrave;s direct interdit');
}

class ActionErasespamedcomments extends WikiniAdminAction
{
	function PerformAction($args, $command)
	{
		$wiki = &$this->wiki;
		ob_start();
		echo	"\n<!-- == Action erasespamedcomments v 0.7 ============================= -->\n";

		// -- 2. Affichage du formulaire ---
		if(!isset($_POST['clean']))
		{
			$limit = isset($args['max']) && $args["max"] > 0 ? (int) $args["max"] : 0;
			if ($comments = $wiki->LoadRecentComments($limit))
			{
				// Formulaire listant les commentaires
				echo "<form method=\"post\" action=\"". $wiki->Href() . "\" name=\"selection\">\n";
				$curday = '';
				foreach ($comments as $comment)
				{
					// day header
					list($day, $time) = explode(" ", $comment["time"]);
					if ($day != $curday)
					{
						if ($curday)
						{
							echo "</ul>\n" ;
						}
						$erase_id = 'erasecommday_' . str_replace('-', '', $day);
						echo "<b>$day:</b> <a href=\"#\" onclick=\"return invert_selection('" . $erase_id . "')\">inverser</a> <br />\n" ;
						echo "<ul id=\"" . $erase_id . "\">\n";
						$curday = $day;
					}

					// echo entry
					echo
						"<li><input name=\"suppr[]\" value=\"" . $comment["tag"] . "\" type=\"checkbox\" /> [Suppr.!] ".
						$comment["tag"].
						" (",$comment["time"],") <code>".
						htmlspecialchars(substr($comment['body'], 0, 25), ENT_COMPAT, YW_CHARSET)."</code> ".
						"<a href=\"",$wiki->href("", $comment["comment_on"], "show_comments=1")."#".$comment["tag"]."\">".
						$comment["comment_on"],"</a> . . . . ".
						$wiki->Format($comment["user"]),"</li>\n" ;
				}
				echo "</ul>\n<input type=\"hidden\" name=\"clean\" value=\"yes\" />\n";
				echo "<button value=\"Valider\">Nettoyer >></button>\n";
				echo "</form>";
			}
			else
			{
				echo "<i>Pas de commentaires r&eacute;cents.</i>" ;
			}
		}


		// -- 3. Traitement du formulaire ---
		else if(isset($_POST['clean']))
		{
			$deletedPages = "";


			// -- 3.1 Si des pages ont �t� s�lectionn�es : effacement ---
			// On efface chaque �l�ment du tableau suppr[]
			// Pour chaque page s�lectionn�e
			if (!empty($_POST['suppr']))
			{
				foreach ($_POST['suppr'] as $page)
				{
					// Effacement de la page en utilisant la m�thode ad�quate
					// (si DeleteOrphanedPage ne convient pas, soit on cr��
					// une autre, soit on la modifie
					echo "Effacement de : " . $page . "<br />\n";
					$wiki->DeleteOrphanedPage($page);
					$deletedPages .= $page . ", ";
				}
				$deletedPages = trim($deletedPages, ", ");
				echo "<p><a href=\"".$wiki->Href()."\">Retour au formulaire.</a></p>";
			}

			// -- 3.2 Si aucune page n'a �t� s�lectionn� : message
			else
			{
				echo "<p>Aucun commentaire n'a �t� s�lectionn� pour �tre effac�.</p>";
				echo "<p><a href=\"".$wiki->Href()."\">Retour au formulaire.</a></p>";
			}

			// -- 3.3 �criture du journal des actions ---
			//        S'il y a eu des pages nettoy�es,
			//        on enregistre dans une page choisie qui a fait quoi
			if ($deletedPages)
			{
				// -- D�termine quelle est la page de log :
				//    -- pass�e en param�tre
				//    -- ou la page de log par d�faut
				$reportingPage = isset($args["logpage"]) ? $args["logpage"] : "";

				// -- Ajout de la ligne de log
				$wiki->LogAdministrativeAction($wiki->GetUserName(),
					"Commentaire(s) effac�(s)" .
					/*" [" .*/ /*$_POST['comment'] .*/ /* "]".*/
					"&nbsp;: " .
					"\"\"".
					$deletedPages .
					"\"\"".
					"\n", $reportingPage);
			}
		}
		return ob_get_clean();
	}
}

?>
