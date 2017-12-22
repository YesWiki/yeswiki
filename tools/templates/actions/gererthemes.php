<!--
Copyright 2014 Rémi PESQUERS (rp.lefamillien@gmail.com)
Cette action à pour but de gérer massivement les droits sur les pages d'un wiki.
Les pages s'affichent et sont modifiées en fonction du squelette qu'elles utilisent (définis par l'utilisateur).
-->

<a name="gererthemes"></a>

<?php
//action réservée aux admins
if (! $this->UserIsAdmin()) {
    echo '<div class="alert alert-danger alert-error"><strong>Erreur action {{gererthemes..}}</strong> : cette action est r&eacute;serv&eacute;e aux admins</div>';
    return ;
}
?>

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

    include_once 'tools/templates/libs/templates.functions.php';

    $table = $GLOBALS['wiki']->config['table_prefix'];

    if( isset($_POST['theme_modifier']) )
    {
        if (!isset($_POST['selectpage']))
        {
            $this->SetMessage('Aucune page n\'a &eacute;t&eacute; s&eacute;lectionn&eacute;e.');
        }
        else
        {
            foreach( $_POST['selectpage'] as $page_cochee )
            {
                if( isset($_POST['theme_reset']) )
                {
                    $this->SaveMetaDatas($page_cochee, array('theme' => null, 'style' => null, 'squelette' => null));
                }
                else
                {
                    $this->SaveMetaDatas($page_cochee, array('theme' => $_POST['theme_select'], 'style' => $_POST['style_select'], 'squelette' => $_POST['squelette_select']));
                }
            }
        }
    }

    //Récupération de la liste des pages
    $liste_pages = $this->Query("SELECT * FROM " . $table . "pages WHERE latest='Y' ORDER BY " . $table . "pages.tag ASC");

    $num_page = 0;
    while ($tab_liste_pages = mysqli_fetch_array($liste_pages)) {
        $page_et_themes[$num_page] = recup_meta($tab_liste_pages['tag']);
        $num_page++;
    }

?>

<p class="alert alert-info"><?php echo $num_page;?> pages trouv&eacute;es </p>

<?php

echo $this->FormOpen();
echo theme_selector();

?>

<div class="clearfix"></div>
<div class="checkbox">
  <label>
	<input type="checkbox" value="1" name="theme_reset" />
	Utiliser thème par défaut
	<span class="help-block">(effacer données de thème et style dans la base de données).</span>
  </label>
</div>


	<table class="table table-striped table-condensed">
		<tr>
			<td><input type="checkbox" name="id" value="tous" onClick="cocherTout(this.checked)"></td>
			<td><div><b>Page</b></div></td>
			<td><div align="center"><b>Theme</b></div></td>
			<td><div align="center"><b>Squelette</b></div></td>
			<td><div align="center"><b>Style</b></div></td>
		</tr>
<?php
for ($x = 0; $x < $num_page; $x++) {
?>
	<tr>
		<td><input type="checkbox" name="selectpage[]" value="<?php echo $page_et_themes[$x]['page'];?>"></td>
		<td><?php echo $this->Link($page_et_themes[$x]['page']);?></td>
		<td><div align="center"><?php echo nl2br(str_replace(" ", "<br>", $page_et_themes[$x]['theme']));?></div></td>
		<td><div align="center"><?php echo nl2br(str_replace(" ", "<br>", $page_et_themes[$x]['squelette']));?></div></td>
		<td><div align="center"><?php echo nl2br(str_replace(" ", "<br>", $page_et_themes[$x]['style']));?></div></td>
	</tr>

<?php
}
?>

</table>

<p>
	<input name="theme_modifier" type="submit"
		value="Mettre &agrave; jour"
		class="btn <?php echo (isset($btnclass) ? ' '.$btnclass : '') ?>"
		onclick="this.form.action+='#gererthemes'; return true;" />
</p>

<?php
    echo $this->FormClose();
?>
