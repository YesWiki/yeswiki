<!--
Copyright 2014 Rémi PESQUERS (rp.lefamillien@gmail.com)

Cette action à pour but de gérer massivement les droits sur les pages d'un wiki.

Les pages s'affichent et sont modifiées en fonction du squelette qu'elles utilisent (définis par l'utilisateur).
-->
<script>
  //Fonction pour cocher toutes les cases.
  function cocherTout(etat)
  {
     var cases = document.querySelectorAll('input.selectpage');
     for(var i=0; i < cases.length; i++)
       if(cases[i].type == 'checkbox') cases[i].checked = etat;
  }
  // function to relaod page with filter parameter
  function reloadGererDroits(elem){
      var value = $(elem).val();
      let urlwindow = window.location.toString();
      let urlSplitted = urlwindow.split("?");
      let baseUrl = urlSplitted[0];
      let paramsSplitted = (urlSplitted.length > 1) ? urlSplitted[1].split("&") : [];
      let i;
      let params = '';
      for (i = 0; i < paramsSplitted.length; i++) {
          if (paramsSplitted[i].substr(0,7) != 'filter='){
              if (i > 0){
                params = params + "&";
              }
              params = params + paramsSplitted[i];
          }
      } 
      if (value != ''){
        if (params.length > 0) {
          params = params + '&';
        }
        params = params + "filter=" + value;
      }
      window.location = baseUrl + ((params.length > 0) ? '?' + params: '');
  }
</script>
<?php
use YesWiki\Core\Service\DbService;
use YesWiki\Security\Controller\SecurityController;

//action réservée aux admins
if (! $this->UserIsAdmin()) {
    echo '<div class="alert alert-danger alert-error">'._t('ACLS_RESERVED_FOR_ADMINS').'</div>';
    return ;
}

    include_once 'tools/templates/libs/templates.functions.php';

    $table = $this->config['table_prefix'];

    //Modification de droits
    if (isset($_POST['geredroits_modifier'])) {
        if (!isset($_POST['selectpage'])) {
            $error = _t('ACLS_NO_SELECTED_PAGE');
        } else {
            if ($_POST['typemaj'] != 'default' && empty($_POST['newlire'])
            && empty($_POST['newecrire']) && empty($_POST['newcomment']) && empty($_POST['newlire_advanced'])
            && empty($_POST['newecrire_advanced']) && empty($_POST['newecrire_advanced'])) {
                $error = _t('ACLS_NO_SELECTED_RIGHTS');
            } else {
                foreach ($_POST['selectpage'] as $page_cochee) {
                    if ($_POST['typemaj'] == 'default') {
                        $this->DeleteAcl($page_cochee);
                    } else {
                        $appendAcl = $_POST['typemaj'] == 'ajouter';
                        if (!empty($_POST['newlire_advanced'])) {
                            $this->SaveAcl($page_cochee, 'read', $_POST['newlire_advanced'], $appendAcl);
                        } elseif (!empty($_POST['newlire'])) {
                            $this->SaveAcl($page_cochee, 'read', $_POST['newlire'], $appendAcl);
                        }
                        if (!empty($_POST['newecrire_advanced'])) {
                            $this->SaveAcl($page_cochee, 'write', $_POST['newecrire_advanced'], $appendAcl);
                        } elseif (!empty($_POST['newecrire'])) {
                            $this->SaveAcl($page_cochee, 'write', $_POST['newecrire'], $appendAcl);
                        }
                        if (!empty($_POST['newcomment_advanced'])) {
                            $this->SaveAcl($page_cochee, 'comment', $_POST['newcomment_advanced'], $appendAcl);
                        } elseif (!empty($_POST['newcomment'])) {
                            $this->SaveAcl($page_cochee, 'comment', $_POST['newcomment'], $appendAcl);
                        }
                    }
                }

                $success = _t('ACLS_RIGHTS_WERE_SUCCESFULLY_CHANGED');
            }
        }
    }

    // récupération des filtres
    $filter = $_GET['filter'] ?? null;
    if (!empty($filter)) {
        $dbService = $this->services->get(DbService::class);
        $filter = strval($filter);
        if ($filter == "pages") {
            $search = ' AND tag NOT IN ('.
          'SELECT DISTINCT resource FROM '.$table.'triples ' .
          'WHERE value = "fiche_bazar"'.
          ') ';
        } elseif ($filter == "specialpages") {
            $search = ' AND tag IN ("BazaR","GererSite","GererDroits","GererThemes","GererMisesAJour","GererUtilisateurs","TableauDeBord"'.
              ',"PageTitre","PageMenuHaut","PageRapideHaut","PageHeader","PageFooter","PageCSS","PageMenu"'.
              ',"PageColonneDroite","MotDePassePerdu","ParametresUtilisateur","GererConfig","ActuYeswiki","LookWiki") ';
        } elseif ($filter === strval(intval($filter))) {
            $requete_pages_wiki_bazar_fiches =
          'SELECT DISTINCT resource FROM '.$table.'triples ' .
          'WHERE value = "fiche_bazar" AND property = "http://outils-reseaux.org/_vocabulary/type" ' .
          'ORDER BY resource ASC';

            $search = ' AND body LIKE \'%"id_typeannonce":"' . $dbService->escape($filter) . '"%\'';
            $search .= ' AND tag IN (' . $requete_pages_wiki_bazar_fiches . ')';
            $search .= ' ';
        } elseif ($filter == "lists") {
            $requete_pages_wiki_listes =
              'SELECT DISTINCT resource FROM '.$table.'triples ' .
              'WHERE value = "liste" AND property = "http://outils-reseaux.org/_vocabulary/type" ' .
              'ORDER BY resource ASC';
            $search = ' AND tag IN (' . $requete_pages_wiki_listes . ')';
            $search .= ' ';
        } else {
            $filter = null;
        }
    }

    // récupération de tous les formulaires
    $forms = $this->services->get(YesWiki\Bazar\Service\FormManager::class)->getAll();

    //Récupération de la liste des pages
    $liste_pages = $this->Query('SELECT * FROM '.$table."pages WHERE latest='Y' ".($search ?? '')."ORDER BY "
        .$table.'pages.tag ASC');

    echo '<form method="post" action="'.$this->href(null, null, (!empty($filter) ? ['filter' => $filter] : [])).'" class="form-acls form-inline">';
?>

<?php
$num_page = 0;
while ($tab_liste_pages = mysqli_fetch_array($liste_pages)) {
    $page_et_droits[$num_page] = recup_droits($tab_liste_pages['tag']);
    ++$num_page;
}
?>

<?php
if (isset($error)) {
    echo "<div class='alert alert-danger'>$error</div>";
} elseif (isset($success)) {
    echo "<div class='alert alert-success'>$success</div>";
}
$this->addJavascriptFile('tools/templates/libs/vendor/datatables/jquery.dataTables.min.js');
$this->addJavascriptFile('tools/templates/libs/vendor/datatables/dataTables.bootstrap.min.js');
$this->addCSSFile('tools/templates/libs/vendor/datatables/dataTables.bootstrap.min.css');
?>
<p><?php echo _t('ACLS_SELECT_PAGES_TO_MODIFY'); ?></p>
<div class="form-group" style="display:flex;justify-content:flex-end;margin-bottom:10px;margin-top:10px;">
  <label for="filterforpages" style="margin-right:10px;"><?php echo _t('ACLS_SELECT_PAGES_FILTER'); ?></label>
  <select class="form-control" id="filterforpages" onchange="reloadGererDroits(this)">
    <option value="" <?php echo (empty($filter)) ? 'selected="selected"' : ''; ?>></option>
    <option value="pages" <?php echo ("pages" == $filter) ? 'selected="selected"' : ''; ?>><?php echo _t('ACLS_SELECT_PAGES_FILTER_ON_PAGES'); ?></option>
    <option value="specialpages" <?php echo ("specialpages" == $filter) ? 'selected="selected"' : ''; ?>><?php echo _t('ACLS_SELECT_PAGES_FILTER_ON_SPECIALPAGES'); ?></option>
    <option value="lists" <?php echo ("lists" == $filter) ? 'selected="selected"' : ''; ?>><?php echo _t('ACLS_SELECT_PAGES_FILTER_ON_LISTS'); ?></option>
    <?php foreach ($forms as $id => $form): ?>
      <option value="<?php echo $id;?>" <?php echo ($id == $filter) ? 'selected="selected"' : '';
        ?>><?php echo str_replace('{name}', $form['bn_label_nature'], str_replace('{id}', $id, _t('ACLS_SELECT_PAGES_FILTER_FORM')));?>
    <?php endforeach ?>
  </select>
</div>
<div class="table-responsive">
  <table class="table table-striped table-condensed table-acls">
    <thead>
      <tr>
        <th class="prevent-sorting">
          <label class="check-all-container">
            <input type="checkbox" name="id" value="tous" onClick="cocherTout(this.checked)">
            <span></span>
          </label>
        </th>
        <th><div><b><?php echo _t('ACLS_PAGE'); ?></b></div></th>
        <th><div align="center"><b><?php echo _t('YW_ACLS_READ'); ?></b></div></th>
        <th><div align="center"><b><?php echo _t('YW_ACLS_WRITE'); ?></b></div></th>
        <!-- TODO : repair comments <th><div align="center"><b>Commentaires</b></div></th> -->
      </tr>
    </thead>
<tbody>

<?php
if (!function_exists('display_droit')) {
            function display_droit($text)
            {
                $values = explode("\n", $text);
                $values = array_map(function ($el) {
                    switch ($el) {
                case '*': return '<span class="label label-success">'._t('ACLS_EVERYBODY').'</span>';
                case '+': return '<span class="label label-warning">'._t('ACLS_AUTHENTIFICATED_USERS').'</span>';
                case '%': return '<span class="label label-danger">'._t('ACLS_OWNER').'</span>';
            }
                    switch ($el[0]) {
                case '@': return "<span class='label label-primary'>$el</span>";
                case '!': return "<span class='label label-danger'>$el</span>";
            }
                    return "<span class='label label-default'>$el</span>";
                }, $values);
                $result = implode('<br>', $values);
                return nl2br($result);
            }
        }
?>
<?php for ($x = 0; $x < $num_page; ++$x) : ?>
    <tr>
      <td>
        <label for="selectpage[<?php echo $page_et_droits[$x]['page']; ?>]">
        	<input type="checkbox" name="selectpage[<?php echo $page_et_droits[$x]['page']; ?>]"
                 value="<?php echo $page_et_droits[$x]['page']; ?>" class="selectpage"
                 id="selectpage[<?php echo $page_et_droits[$x]['page']; ?>]">
          <span></span>
        </label>
      </td>
      <td>
      	<?php echo $this->Link($page_et_droits[$x]['page']); ?>
      </td>
      <td align="center">
      	<?php echo display_droit($page_et_droits[$x]['lire']); ?>
      </td>
      <td align="center">
      	<?php echo display_droit($page_et_droits[$x]['ecrire']); ?>
	  </td>
      <!-- TODO : repair comments <td align="center"> <?php // echo display_droit($page_et_droits[$x]['comment']);?> </td> -->
    </tr>

<?php endfor; ?>
</tbody>
</table>
</div>
  <p><b><?php echo _t('ACLS_FOR_SELECTED_PAGES'); ?> :</b></p>

  <p class="type-modif-container">
    <label for="typemajdefault">
        <input type=radio name="typemaj" value="default" id="typemajdefault"
                onClick="$('.edit-acl-container').slideUp()">
        <span><?php echo _t('ACLS_RESET_SELECTED_PAGES'); ?> <em>wakka.config.php</em>)</span>
    </label>

    <label for="typemalremplacer">
      <input type=radio name="typemaj" value="remplacer" id="typemalremplacer" checked
              onClick="$('.edit-acl-container').slideDown()">
      <span><?php echo _t('ACLS_REPLACE_SELECTED_PAGES'); ?></span>
    </label>
  </p>

  <div class="edit-acl-container">

    <p><b></b></p>

    <div class="switch">
      <label>
        <?php echo _t('ACLS_MODE_SIMPLE'); ?>
        <input type="checkbox" id="acl-switch-mode">
        <span class="lever"></span>
        <?php echo _t('ACLS_MODE_ADVANCED'); ?>
      </label>
    </div>

    <div class="alert alert-default acl-advanced">
        <?php echo _t('ACLS_HELPER'); ?>
    </div>

    <div class="acl-container">
      <?php $roles = ['lire' => _t('YW_ACLS_READ'), 'ecrire' => _t('YW_ACLS_WRITE')]; //TODO : repair comments , 'comment' => 'Commentaire'];
      foreach ($roles as $role => $label) { ?>
        <div class="acl-single-container">
          <label for="new<?php echo $role ?>" class="control-label">
            <?php echo $label ?>
          </label>
          <select name="new<?php echo $role ?>" class="form-control acl-simple">
            <option value=""><?php echo _t('ACLS_NO_CHANGE'); ?></option>
            <option value="*"><?php echo _t('ACLS_EVERYBODY'); ?></option>
            <option value="+"><?php echo _t('ACLS_AUTHENTIFICATED_USERS'); ?></option>
            <option value="%"><?php echo _t('ACLS_OWNER'); ?></option>
            <option value="@admins"><?php echo _t('ACLS_ADMIN_GROUP'); ?></option>
          </select>
          <input placeholder="<?php echo _t('ACLS_LIST_OF_ACLS'); ?>" name="new<?php echo $role ?>_advanced" class="acl-advanced form-control" />
        </div>
      <?php } ?>
    </div>
  </div>

	<p>
		<input
      name="geredroits_modifier"
      class="btn btn-primary" 
      onclick="$('.table-acls').DataTable().$('input, select').appendTo('.form-acls');" 
      value="<?php echo _t('ACLS_UPDATE'); ?>" 
      <?php if ($this->services->get(SecurityController::class)->isWikiHibernated()) {
          echo 'disabled data-toggle="tooltip" data-placement="bottom" title="'._t('WIKI_IN_HIBERNATION').'"';
      } ?>
      type="submit">
	</p>
<?php
echo $this->FormClose();
