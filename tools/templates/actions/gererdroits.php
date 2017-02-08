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
if (! $this->UserIsAdmin()) {
    echo '<div class="alert alert-danger alert-error"><strong>Erreur action {{gererdroits..}}</strong> : cette action est r&eacute;serv&eacute;e aux admins</div>';
    return ;
}

    include_once 'tools/templates/libs/templates.functions.php';

    $table = $this->config['table_prefix'];

    /**
     * Récupère les droits de la page désignée en argument et renvoie un tableau.
     *
     * @param string $page
     * @return array()
     */
    function recup_droits($page)
    {
        $wiki = $GLOBALS['wiki'] ;

        $readACL = $wiki->LoadAcl($page, 'read', false);
        $writeACL = $wiki->LoadAcl($page, 'write', false);
        $commentACL = $wiki->LoadAcl($page, 'comment', false);

        $acls = array(
            'page' => $page,
            'lire' => $wiki->GetConfigValue('default_read_acl'),
            'lire_default' => true,
            'ecrire' => $wiki->GetConfigValue('default_write_acl'),
            'ecrire_default' => true,
            'comment' => $wiki->GetConfigValue('default_comment_acl'),
            'comment_default' => true,
        );
        if( isset($readACL['list']) )
        {
            $acls['lire'] = $readACL['list'] ;
            $acls['lire_default'] = false ;
        }
        if( isset($writeACL['list']) )
        {
            $acls['ecrire'] = $writeACL['list'] ;
            $acls['ecrire_default'] = false ;
        }
        if( isset($commentACL['list']) )
        {
            $acls['comment'] = $commentACL['list'] ;
            $acls['comment_default'] = false ;
        }
        return $acls ;
        /*
        return array('page' => $page,
            'droits_lire' => isset($readACL['list']) ? $readACL['list'] : $wiki->GetConfigValue('default_read_acl') ,
            'droits_ecrire' =>  isset($writeACL['list']) ? $writeACL['list'] : $wiki->GetConfigValue('default_write_acl') ,
            'droits_comment' =>  isset($commentACL['list']) ? $commentACL['list'] : $wiki->GetConfigValue('default_comment_acl') ,
        );*/

    }

error_log( var_export($_POST,true));

    //Modification de droits
    if ( isset($_POST['geredroits_modifier'])
        && ( ($_POST['typemaj']=='default') || isset($_POST['modiflire']) || isset($_POST['modifecrire']) || isset($_POST['modifcomment']))
        ) {

        if (!isset($_POST['selectpage'])) {

            $this->SetMessage("Aucune page n'a &eacute;t&eacute; s&eacute;lectionn&eacute;e.");

        } else {

            if ( $_POST['typemaj'] != 'default'
                && (!isset($_POST['modiflire'])) && (!isset($_POST['modifecrire'])) && !(isset($_POST['modifcomment']))
                ) {
                $this->SetMessage("Vous n'avez pas s&eacute;lectionn&eacute; de droits &agrave; modifier.");

            } else {

                foreach ($_POST['selectpage'] as $page_cochee) {

                    if( $_POST['typemaj'] == 'default') {

                        $this->DeleteAcl($page_cochee);

                    } else {

                        if ($_POST['typemaj'] == 'ajouter') {
                            $appendAcl = true ;
                        } else { //if ($_POST['typemaj'] == 'remplacer') {
                            $appendAcl = false ;
                        }

                        if (isset($_POST['modiflire'])) {
                            $this->SaveAcl($page_cochee, 'read', $_POST['newlire'], $appendAcl );
                        }

                        if (isset($_POST['modifecrire'])) {
                            $this->SaveAcl($page_cochee, 'write', $_POST['newecrire'], $appendAcl );
                        }

                        if (isset($_POST['modifcomment'])) {
                            $this->SaveAcl($page_cochee, 'comment', $_POST['newcomment'], $appendAcl );
                        }
                    }
                }

                $this->SetMessage('Droit modifi&eacute;s avec succ&egrave;s');

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
<div class="alert alert-info">
	<?php echo $num_page; ?> pages trouv&eacute;es
</div>

<p>En <span style="font-weight:bold;color:orange">orange</span> les valeurs par défaut <em>dans wakka.config.php</em>.</p>
  <table class="table table-striped table-condensed">
    <tr>
      <td><input type="checkbox" name="id" value="tous" onClick="cocherTout(this.checked)"></td>
      <td><div><b>Page</b></div></td>
      <td><div align="center"><b>Lecture</b></div></td>
      <td><div align="center"><b>Ecriture</b></div></td>
      <td><div align="center"><b>Commentaires</b></div></td>
    </tr>
<?php for ($x = 0; $x < $num_page; ++$x) : ?>
    <tr>
      <td>
      	<input type="checkbox" name="selectpage[]" value="<?php echo $page_et_droits[$x]['page']; ?>">
      </td>
      <td>
      	<?php echo $this->Link($page_et_droits[$x]['page']); ?>
      </td>
      <td align="center"
        <?php if($page_et_droits[$x]['lire_default']) echo 'style="font-weight:bold;color:orange"' ;?>
        >
      	<?php echo nl2br(str_replace(' ', '<br>', $page_et_droits[$x]['lire'])); ?>
      </td>
      <td align="center"
        <?php if($page_et_droits[$x]['ecrire_default']) echo 'style="font-weight:bold;color:orange"' ;?>
        >
      	<?php echo nl2br(str_replace(' ', '<br>', $page_et_droits[$x]['ecrire'])); ?>
	  </td>
      <td align="center"
        <?php if($page_et_droits[$x]['comment_default']) echo 'style="font-weight:bold;color:orange"' ;?>
        >
      	<?php echo nl2br(str_replace(' ', '<br>', $page_et_droits[$x]['comment'])); ?>
      </td>
    </tr>

<?php endfor; ?>
</table>
  <h4>Modifier les droits</h4>
  <p><i>Seuls les pages cochées seront modifiées.</i></p>

	<p>
	<input type=radio name="typemaj" value="default" id="typemajdefault" >
	<label for="typemajdefault">Valeurs par défaut (<em>wakka.config.php</em>)</label> (Supprime les droits dans la base de données).
	</p>

  <p><i>Seuls les droits cochés seront modifiés.</i></p>
  <table class="table">
    <tr cellpadding="3">
      <td>
        <div class="form-group">
          <input type="checkbox" name="modiflire" id="modiflire" class="form-control" value="modiflire">
          <label for="modiflire" class="control-label">Lecture</label>
        </div>
      </td>
      <td>
        <div class="form-group">
          <input type="checkbox" name="modifecrire" id="modifecrire" class="form-control" value="modifecrire">
          <label for="modifecrire" class="control-label">Ecriture</label>
        </div>
      </td>
      <td>
        <div class="form-group">
          <input type="checkbox" name="modifcomment" id="modifcomment" value="modifcomment">
          <label for="modifcomment" class="control-label">Commentaires</label>
        </div>
      </td>
    </tr>
    <tr>
      <td>
		<textarea name="newlire" rows=4 cols=10 ><?php
        if (isset($_POST['newlire'])) {
            echo $_POST['newlire'];
        }
        ?></textarea>
      </td>
      <td>
      	<textarea name="newecrire" rows=4 cols=10 ><?php
        if (isset($_POST['newecrire'])) {
            echo $_POST['newecrire'];
        }
        ?></textarea>
      </td>
      <td><textarea name="newcomment" rows=4 cols=10 ><?php
        if (isset($_POST['newcomment'])) {
            echo $_POST['newcomment'];
        }
        ?></textarea>
      </td>
    </tr>
  </table>

	<input type=radio name="typemaj" value="ajouter" id="typemajajouter" checked>
	<label for="typemajajouter">Ajouter</label> (Les nouveaux droits seront ajout&eacute;s aux actuels).
    <br/>
	<input type=radio name="typemaj" value="remplacer" id="typemalremplacer">
	<label for="typemalremplacer">Remplacer</label> (Les droits actuels seront supprim&eacute;s).
    <br/>

	<p>
		<input name="geredroits_modifier" class="btn <?php if ($btnclass != '') echo ' '.$btnclass; ?>"
    		value="Mettre &agrave; jour" type="submit"
    	>
	</p>
<?php
echo $this->FormClose();
