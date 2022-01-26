<script>
  //Fonction pour cocher toutes les cases.
  function cocherTout(etat)
  {
     var cases = document.querySelectorAll('input.selectpage');
     for(var i=0; i < cases.length; i++)
       if(cases[i].type == 'checkbox') cases[i].checked = etat;
  }
</script>
<?php
use YesWiki\Security\Controller\SecurityController;

/**
 * Copyright 2014 Rémi PESQUERS (rp.lefamillien@gmail.com)
 * Cette action à pour but de gérer massivement les droits sur les pages d'un wiki.
 * Les pages s'affichent et sont modifiées en fonction du squelette qu'elles utilisent (définis par l'utilisateur).
*/

//action réservée aux admins
if (!$this->UserIsAdmin()) {
    echo '<div class="alert alert-danger alert-error">' . _t('ACLS_RESERVED_FOR_ADMINS') . '.</div>';
    return ;
}

require_once 'tools/templates/libs/templates.functions.php';

$table = $this->config['table_prefix'];

if (isset($_POST['theme_modifier'])) {
    if (!isset($_POST['selectpage'])) {
        $error = _t('ACLS_NO_SELECTED_PAGE');
    } else {
        foreach ($_POST['selectpage'] as $page_cochee) {
            if ($_POST['typemaj'] == 'reinitialiser') {
                $this->SaveMetaDatas($page_cochee, array('theme' => null, 'style' => null, 'squelette' => null));
            } else {
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

if (isset($error)) {
    echo "<div class='alert alert-danger'>$error</div>";
}
$this->addJavascriptFile('tools/templates/libs/vendor/datatables/jquery.dataTables.min.js');
$this->addCSSFile('tools/templates/libs/vendor/datatables/dataTables.bootstrap.min.css');
echo '<form method="post" action="'.$this->href().'">';
?>
<p><?php echo _t('GERERTHEMES_HINT'); ?></p>
<div class="table-responsive">
<table class="table table-striped table-condensed gerer-theme">
<thead>
    <tr>
        <th class="prevent-sorting">
          <label class="check-all-container">
            <input type="checkbox" name="id" value="tous" onclick="cocherTout(this.checked)">
            <span></span>
          </label>
        </th>
        <th><div><b><?php echo _t('GERERTHEMES_PAGE'); ?></b></div></th>
        <th><div align="center"><b><?php echo _t('TEMPLATE_THEME'); ?></b></div></th>
        <th><div align="center"><b><?php echo _t('TEMPLATE_SQUELETTE'); ?></b></div></th>
        <th><div align="center"><b><?php echo _t('TEMPLATE_STYLE'); ?></b></div></th>
    </tr>
</thead>
<tbody>
<?php
for ($x = 0; $x < $num_page; $x++) {
    ?>
	<tr>
		<td>
      <label for="selectpage[<?php echo $page_et_themes[$x]['page']; ?>]">
        <input type="checkbox" name="selectpage[<?php echo $page_et_themes[$x]['page']; ?>]"
               value="<?php echo $page_et_themes[$x]['page']; ?>" class="selectpage"
               id="selectpage[<?php echo $page_et_themes[$x]['page']; ?>]">
        <span></span>
      </label>
    </td>
		<td><?php echo $this->Link($page_et_themes[$x]['page']); ?></td>
		<td><div align="center"><?php echo nl2br(str_replace(" ", "<br>", $page_et_themes[$x]['theme'])); ?></div></td>
		<td><div align="center"><?php echo nl2br(str_replace(" ", "<br>", $page_et_themes[$x]['squelette'])); ?></div></td>
		<td><div align="center"><?php echo nl2br(str_replace(" ", "<br>", $page_et_themes[$x]['style'])); ?></div></td>
	</tr>
<?php
}
?>
</tbody>
</table>
</div>

<p><b><?php echo _t('GERERTHEMES_ACTIONS'); ?></b></p>

<p class="type-modif-container">
  <label for="typemajdefault">
    <input type=radio name="typemaj" value="reinitialiser" id="typemajdefault" checked
           onClick="$('.edit-theme-container').slideUp()">
    <span><?php echo _t('GERERTHEMES_INIT_THEME_FOR_SELECTED_PAGES'); ?></span>
  </label>
  <label for="typemajajouter">
    <input type=radio name="typemaj" value="modifier" id="typemajajouter"
           onClick="$('.edit-theme-container').slideDown()">
    <span><?php echo _t('GERERTHEMES_MODIFY_THEME_FOR_SELECTED_PAGES'); ?></span>
  </label>
</p>

<div class="edit-theme-container" style="display:none">
  <?php echo theme_selector('post'); ?>
</div>

<p>
  <input
    name="theme_modifier" 
    type="submit" 
    value="Mettre &agrave; jour"
    class="btn btn-primary"
    onclick="this.form.action+='#gererthemes'; return true;"
    <?php if ($this->services->get(SecurityController::class)->isWikiHibernated()) {
    echo 'disabled data-toggle="tooltip" data-placement="bottom" title="'._t('WIKI_IN_HIBERNATION').'"';
} ?>
    />
</p>



<?php
    echo $this->FormClose();
?>
