<!--
Copyright 2014 Rémi PESQUERS (rp.lefamillien@gmail.com)

Cette action à pour but de gérer massivement les droits sur les pages d'un wiki.

Les pages s'affichent et sont modifiées en fonction du squelette qu'elles utilisent (définis par l'utilisateur).
-->
<script>
	//Fonction pour cocher toutes les cases.
	function cocherTout(etat)
	{
	   var cases = document.getElementsByTagName('input');   // Récupération de tous les input
	   for(var i=1; i<cases.length; i++)
		 if(cases[i].type == 'checkbox')    //Vérification si c'est une checkbox
			 {cases[i].checked = etat;} //Cochée ou non en fonction de l'état
	}

</script>
<?php
$btnclass = $this->GetParameter('btnclass');

//action réservée aux admins
if ($this->UserIsAdmin()) {
    include_once 'tools/templates/libs/templates.functions.php';

    $table = $this->config["table_prefix"];
 
    //Récupère les droits de la page désignée en argument et renvoie un tableau
    function recup_droits($page)
    {
        $table = $GLOBALS['wiki']->config["table_prefix"];

        $requete_lire = "SELECT * FROM " . $table . "acls WHERE page_tag='"
        . $page . "' AND privilege='read' ORDER BY " . $table . "acls.page_tag ASC";
        $requete_ecrire = "SELECT * FROM " . $table . "acls WHERE page_tag='"
        . $page . "' AND privilege='write' ORDER BY " . $table . "acls.page_tag ASC";
        $requete_comment = "SELECT * FROM " . $table . "acls WHERE page_tag='"
        . $page . "' AND privilege='comment' ORDER BY " . $table . "acls.page_tag ASC";

        $droits_lire = mysqli_fetch_array(mysqli_query($requete_lire));
        $droits_ecrire = mysqli_fetch_array(mysqli_query($requete_ecrire));
        $droits_comment = mysqli_fetch_array(mysqli_query($requete_comment));

        return array('page' => $page,
            'droits_lire' => $droits_lire["list"],
            'droits_ecrire' => $droits_ecrire["list"],
            'droits_comment' => $droits_comment["list"],
        );
    }

    //Modification de droits
    if (isset($_POST["modifier"])) {
        if (!isset($_POST["selectpage"])) {
            $this->SetMessage("Aucune page n'a &eacute;t&eacute; s&eacute;lectionn&eacute;e.");
        } else {
            if ((!isset($_POST['modiflire'])) && (!isset($_POST['modifecrire'])) && !(isset($_POST['modifcomment']))) {
                $this->SetMessage("Vous n'avez pas s&eacute;lectionn&eacute; de droits &agrave; modifier.");
            } else {
                $this->SetMessage("Droit modifi&eacute;s avec succ&egrave;s");
                foreach ($_POST['selectpage'] as $page_cochee) {
                    if ($_POST['typemaj'] == "ajouter") {
                        $newlire = "concat(list,' " . $_POST['newlire'] . "')";
                        $newecrire = "concat(list,' " . $_POST['newecrire'] . "')";
                        $newcomment = "concat(list,' " . $_POST['newcomment'] . "')";
                    }
                    if ($_POST['typemaj'] == "remplacer") {
                        $newlire = "'" . $_POST['newlire'] . "'";
                        $newecrire = "'" . $_POST['newecrire'] . "'";
                        $newcomment = "'" . $_POST['newcomment'] . "'";
                    }
                    if (isset($_POST['modiflire'])) {
                        $this->Query("UPDATE " . $table . "acls SET list=" . $newlire
                            . " WHERE privilege='read' and page_tag='" . $page_cochee . "'");
                    }

                    if (isset($_POST['modifecrire'])) {
                        $this->Query("UPDATE " . $table . "acls SET list=" . $newecrire
                            . " WHERE privilege='write' and page_tag='" . $page_cochee . "'");
                    }

                    if (isset($_POST['modifcomment'])) {
                        $this->Query("UPDATE " . $table . "acls SET list="
                            . $newcomment . " WHERE privilege='comment' and page_tag='" . $page_cochee . "'");
                    }

                }
            }
        }
    }

    //Récupération de la liste des pages
    $liste_pages = $this->Query("SELECT * FROM " . $table . "pages WHERE latest='Y' ORDER BY "
        . $table . "pages.tag ASC");

    echo '<form method="post" action="' . $this->href() . '" class="form-inline">';
    ?>



<?php
$num_page = 0;
while ($tab_liste_pages = mysqli_fetch_array($liste_pages)) {
    $page_et_droits[$num_page] = recup_droits($tab_liste_pages["tag"]);
    $num_page++;
}
?>
<div class="alert alert-info"><?php echo $num_page;?> pages trouv&eacute;es </div>
	<table class="table table-striped table-condensed">
		<tr>
			<td><input type="checkbox" name="id" value="tous" onClick="cocherTout(this.checked)"></td>
			<td><div><b>Page</b></div></td>
			<td><div align="center"><b>Lecture</b></div></td>
			<td><div align="center"><b>Ecriture</b></div></td>
			<td><div align="center"><b>Commentaires</b></div></td>
		</tr>
<?php for ($x = 0; $x < $num_page; $x++) : ?>
		<tr>
			<td><input type="checkbox" name="selectpage[]" value="<?php echo $page_et_droits[$x]['page'];?>"></td>
			<td><?php echo $this->Link($page_et_droits[$x]['page']);?></td>
			<td><div align="center">
            <?php echo nl2br(str_replace(" ", "<br>", $page_et_droits[$x]['droits_lire']));?>
            </div></td>
			<td><div align="center">
            <?php echo nl2br(str_replace(" ", "<br>", $page_et_droits[$x]['droits_ecrire']));?>
            </div></td>
			<td><div align="center">
            <?php echo nl2br(str_replace(" ", "<br>", $page_et_droits[$x]['droits_comment']));?>
            </div></td>
		</tr>

<?php endfor; ?>
</table>
	<br><br><b>Modifier les droits</b></br>
	<i>Seuls les pages et les droits coch&eacute;s seront modifi&eacute;s</i>
	<table class="table">
		<tr cellpadding="3">
			<td><input type="checkbox" name="modiflire" value="modiflire"> Lecture</td>
			<td><input type="checkbox" name="modifecrire" value="modifecrire"> Ecriture</td>
			<td><input type="checkbox" name="modifcomment" value="modifcomment"> Commentaires</td>
		</tr>
		<tr>
			<td>
            <textarea name="newlire" rows=4 cols=10 >
            <?php
            if (isset($_POST["newlire"])) {
                echo $_POST["newlire"];
            }
            ?>
            </textarea>
            </td>
			<td>
            <textarea name="newecrire" rows=4 cols=10 >
            <?php
            if (isset($_POST["newecrire"])) {
                echo $_POST["newecrire"];
            }
            ?>
            </textarea>
            </td>
			<td><textarea name="newcomment" rows=4 cols=10 >
            <?php
            if (isset($_POST["newcomment"])) {
                echo $_POST["newcomment"];
            }
            ?>
            </textarea>
            </td>
		</tr>
	</table>
	<br>
	<input type=radio name="typemaj" value="ajouter" checked>
    <b>Ajouter</b> (Les nouveaux droits seront ajout&eacute;s aux actuels)<br>
	<input type=radio name="typemaj" value="remplacer"><b>Remplacer</b> (Les droits actuels seront supprim&eacute;s)<br>
	<br><input name="modifier" class="btn <?php
    if ($btnclass != '') {
        echo ' ' . $btnclass;
    }
    ?>" value="Mettre &agrave; jour" type="submit">

<?php
echo $this->FormClose();
} else {
    echo '<div class="alert alert-danger alert-error">
    <strong>Erreur action {{gererdroits..}}</strong> : cette action est r&eacute;serv&eacute;e aux admins
    </div>';
}
?>

