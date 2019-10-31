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

//action réservée aux admins
if (! $this->UserIsAdmin()) {
    echo '<div class="alert alert-danger alert-error">Cette action est r&eacute;serv&eacute;e aux admins</div>';
    return ;
}

    include_once 'tools/templates/libs/templates.functions.php';

    $table = $this->config['table_prefix'];

    //Modification de droits
    if ( isset($_POST['geredroits_modifier'])
        && ( ($_POST['typemaj']=='default') || isset($_POST['modiflire']) || isset($_POST['modifecrire']) || isset($_POST['modifcomment'])) ) {

        if (!isset($_POST['selectpage'])) {
            $error = "Aucune page n'a &eacute;t&eacute; s&eacute;lectionn&eacute;e.";
        } else {

            if ( $_POST['typemaj'] != 'default' && (!isset($_POST['modiflire']))
                 && (!isset($_POST['modifecrire'])) && !(isset($_POST['modifcomment'])))  {
                $error = "Vous n'avez pas s&eacute;lectionn&eacute; de droits &agrave; modifier.";
            } else {
                foreach ($_POST['selectpage'] as $page_cochee) {

                    if( $_POST['typemaj'] == 'default') {
                        $this->DeleteAcl($page_cochee);
                    } else {
                        $appendAcl = $_POST['typemaj'] == 'ajouter';

                        if (isset($_POST['modiflire'])) {
                            $val = $_POST['newlire_advanced'] ? $_POST['newlire_advanced'] : $_POST['newlire'];
                            $this->SaveAcl($page_cochee, 'read', $val, $appendAcl );
                        }
                        if (isset($_POST['modifecrire'])) {
                            $val = $_POST['newecrire_advanced'] ? $_POST['newecrire_advanced'] : $_POST['newecrire'];
                            $this->SaveAcl($page_cochee, 'write', $val, $appendAcl );
                        }
                        if (isset($_POST['modifcomment'])) {
                            $val = $_POST['newcomment_advanced'] ? $_POST['newcomment_advanced'] : $_POST['newcomment'];
                            $this->SaveAcl($page_cochee, 'comment', $val, $appendAcl );
                        }
                    }
                }

                $success = 'Droit modifi&eacute;s avec succ&egrave;s';
            }
        }
    }

    //Récupération de la liste des pages
    $liste_pages = $this->Query('SELECT * FROM '.$table."pages WHERE latest='Y' ORDER BY "
        .$table.'pages.tag ASC');

    echo '<form method="post" action="'.$this->href().'" class="form-inline">';
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
} else if (isset($success)) {
  echo "<div class='alert alert-success'>$success</div>";
}
?>
<p>Cochez les pages que vous souhaitez modifier et choisissez une action en base de page</p>
  <table class="table table-striped table-condensed">
    <tr>
      <td><label><input type="checkbox" name="id" value="tous" onClick="cocherTout(this.checked)"><span></span></label></td>
      <td><div><b>Page</b></div></td>
      <td><div align="center"><b>Lecture</b></div></td>
      <td><div align="center"><b>Ecriture</b></div></td>
      <td><div align="center"><b>Commentaires</b></div></td>
    </tr>
<?php
  function display_droit($text) {
    $values = explode("\n", $text);
    $values = array_map(function($el) {
      switch($el) {
        case '*': return "<span class='label label-success'>Tout le monde</span>";
        case '+': return "<span class='label label-warning'>Utilisateurs connectés</span>";
        case '%': return "<span class='label label-danger'>Propriétaire</span>";
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
?>
<?php for ($x = 0; $x < $num_page; ++$x) : ?>
    <tr>
      <td>
        <label for="selectpage[<?php echo $page_et_droits[$x]['page']; ?>]">
        	<input type="checkbox" name="selectpage[<?php echo $page_et_droits[$x]['page']; ?>]"
                 value="<?php echo $page_et_droits[$x]['page']; ?>"
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
      <td align="center">
      	<?php echo display_droit($page_et_droits[$x]['comment']); ?>
      </td>
    </tr>

<?php endfor; ?>
</table>
  <p><b>Actions</b></p>

  <p class="type-modif-container">
    <label for="typemajdefault">
      <input type=radio name="typemaj" value="default" id="typemajdefault"
             onClick="$('.edit-acl-container').slideUp()">
      <span>Réinitialiser (avec les valeurs par défaut définies dans <em>wakka.config.php</em>)</span>
    </label>
    <label for="typemajajouter">
      <input type=radio name="typemaj" value="ajouter" id="typemajajouter" checked
             onClick="$('.edit-acl-container').slideDown()">
      <span>Ajouter (Les nouveaux droits seront ajout&eacute;s aux actuels)</span>
    </label>
    <label for="typemalremplacer">
      <input type=radio name="typemaj" value="remplacer" id="typemalremplacer"
             onClick="$('.edit-acl-container').slideDown()">
      <span>Remplacer (Les droits actuels seront supprim&eacute;s)</span>
    </label>
  </p>

  <div class="edit-acl-container">

    <p><b>Cochez le type de droits et choisissez une valeur</b></p>

    <div class="switch">
      <label>
        Mode simple
        <input type="checkbox" id="acl-switch-mode">
        <span class="lever"></span>
        Mode avancé
      </label>
    </div>

    <div class="alert alert-default acl-advanced">
      Séparez chaque entrée par un retour à la ligne, par example</br>
      <b>*</b> (tous les utilisateurs)</br>
      <b>+</b> (utilisateurs enregistrés)</br>
      <b>%</b> (créateur de la fiche/page)</br>
      <b>@nom_du_groupe</b> (groupe d'utilisateur, ex: @admins)</br>
      <b>JamesBond</b> (nom YesWiki d'un utilisateur)</br>
      <b>!SuperCat</b> (négation, SuperCat n'est pas autorisé)</br>
    </div>

    <div class="acl-container">
      <?php $roles = ['lire' => 'Lecture', 'ecrire' => 'Ecriture', 'comment' => 'Commentaire'];
      foreach ($roles as $role => $label) { ?>
        <div class="acl-single-container">
          <label for="modif<?php echo $role ?>" class="control-label">
            <input type="checkbox" name="modif<?php echo $role ?>" id="modif<?php echo $role ?>" class="form-control">
            <span><?php echo $label ?></span>
          </label>
          <select name="new<?php echo $role ?>" class="form-control acl-simple">
            <option value="*">Tout le monde</option>
            <option value="+">Utilisateurs connectés</option>
            <option value="%">Propriétaire de la page</option>
            <option value="@admins">Groupe admin</option>
          </select>
          <input placeholder="Liste des droits séparés par des virgules" name="new<?php echo $role ?>_advanced" class="acl-advanced form-control"></textarea>
        </div>
      <?php } ?>
    </div>
  </div>

	<p>
		<input name="geredroits_modifier" class="btn btn-primary btn-block" value="Mettre &agrave; jour" type="submit">
	</p>
<?php
echo $this->FormClose();
