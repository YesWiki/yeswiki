<!--
Copyright 2014 Rémi PESQUERS (rp.lefamillien@gmail.com)
Cette action à pour but de gérer massivement les droits sur les pages d'un wiki.
Les pages s'affichent et sont modifiées en fonction du squelette qu'elles utilisent (définis par l'utilisateur).
-->

<a name="gererthemes"></a>

<?php
//action réservée aux admins
if (! $this->UserIsAdmin()) {
    echo '<div class="alert alert-danger alert-error"><strong>Erreur action {{gererdroits..}}</strong> : cette action est r&eacute;serv&eacute;e aux admins</div>';
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

    function recup_meta($page) { //Récupère les droits de la page désignée en argument et renvoie un tableau

        $metas = $GLOBALS['wiki']->GetMetaDatas($page);

        return array('page' => $page,
            'theme' => $metas['theme'],
            'squelette' => $metas['squelette'],
            'style' => $metas['style'],
        );
    }

    function add_change_theme_js()
    {
        // AJOUT DU JAVASCRIPT QUI PERMET DE CHANGER DYNAMIQUEMENT DE TEMPLATES
        $js = '<script>

	(function($) {
		$("#changetheme").on("change", function(){

		if ($(this).attr("id") === "changetheme") {

			// On change le theme dynamiquement
			var val = $(this).val();
			// pour vider la liste
			var squelette = $("#changesquelette")[0];
			squelette.options.length=0
			for (var i=0; i<tab1[val].length; i++){
				o = new Option(tab1[val][i],tab1[val][i]);
				squelette.options[squelette.options.length] = o;
			}
			var style = $("#changestyle")[0];
			style.options.length=0
			for (var i=0; i<tab2[val].length; i++){
				o = new Option(tab2[val][i],tab2[val][i]);
				style.options[style.options.length]=o;
			}
		}

	});
	})(jQuery);
    '
        ;
        $js .= '</script>' . "\n";

        return $js;
    }

    function theme_selector()
    {

    	if( ! isset($formclass) )
    		$formclass = '' ;

        $id = 'select_theme';

        $selecteur = '		<form class="' . $formclass . '" id="' . $id . '">' . "\n";

        //on cherche tous les dossiers du repertoire themes et des sous dossier styles et squelettes, et on les range dans le tableau $wakkaConfig['templates']
        $repertoire_initial = 'tools' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'themes';
        $GLOBALS['wiki']->config['templates'] = search_template_files($repertoire_initial);

        //s'il y a un repertoire themes a la racine, on va aussi chercher les templates dedans
        if (is_dir('themes')) {
            $repertoire_racine = 'themes';
            $GLOBALS['wiki']->config['templates'] = array_merge($GLOBALS['wiki']->config['templates'], search_template_files($repertoire_racine));
            if (is_array($GLOBALS['wiki']->config['templates'])) {
                ksort($GLOBALS['wiki']->config['templates']);
            }

        }

        $selecteur .= '			<div class="control-group form-group">' . "\n" .
        '				<label class="control-label col-lg-4">' . _t('TEMPLATE_THEME') . '</label>' . "\n" .
            '				<div class="controls col-lg-7">' . "\n" .
            '					<select class="form-control" id="changetheme" name="theme_select">' . "\n";
        foreach (array_keys($GLOBALS['wiki']->config['templates']) as $key => $value) {
            $selecteur .= '						<option value="' . $value . '">' . $value . '</option>' . "\n";
        }
        $selecteur .= '					</select>' . "\n" . '				</div>' . "\n" . '			</div>' . "\n";

        $selecteur .=
        '			<div class="control-group form-group">' . "\n" .
        '				<label class="control-label col-lg-4">' . _t('TEMPLATE_SQUELETTE') . '</label>' . "\n" .
            '				<div class="controls col-lg-7">' . "\n" .
            '					<select class="form-control" id="changesquelette" name="squelette_select">' . "\n";
        ksort($GLOBALS['wiki']->config['templates'][$GLOBALS['wiki']->config['favorite_theme']]['squelette']);
        foreach ($GLOBALS['wiki']->config['templates'][$GLOBALS['wiki']->config['favorite_theme']]['squelette'] as $key => $value) {
            $selecteur .= '						<option value="' . $key . '">' . $value . '</option>' . "\n";
        }
        $selecteur .= '					</select>' . "\n" . '				</div>' . "\n" . '			</div>' . "\n";

        ksort($GLOBALS['wiki']->config['templates'][$GLOBALS['wiki']->config['favorite_theme']]['style']);
        $selecteur .=
        '			<div class="control-group form-group">' . "\n" .
        '				<label class="control-label col-lg-4">' . _t('TEMPLATE_STYLE') . '</label>' . "\n" .
            '				<div class="controls col-lg-7">' . "\n" .
            '					<select class="form-control" id="changestyle" name="style_select">' . "\n";
        foreach ($GLOBALS['wiki']->config['templates'][$GLOBALS['wiki']->config['favorite_theme']]['style'] as $key => $value) {
            $selecteur .= '						<option value="' . $key . '">' . $value . '</option>' . "\n";
        }
        $selecteur .= '					</select>' . "\n" . '				</div>' . "\n" . '				</div>' . "\n";

        // $selecteur .=     '</form>'."\n";

        $GLOBALS['js'] = ((isset($GLOBALS['js'])) ? $GLOBALS['js'] : '') . add_templates_list_js() . "\n";
        $GLOBALS['js'] = ((isset($GLOBALS['js'])) ? $GLOBALS['js'] : '') . add_change_theme_js() . "\n";

        return $selecteur;
    }

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
