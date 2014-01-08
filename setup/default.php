<?php
/*
default.php
Copyright (c) 2002, Hendrik Mans <hendrik@mans.de>
Copyright 2002, 2003 David DELON
Copyright 2002 Patrick PAUL
All rights reserved.
Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions
are met:
1. Redistributions of source code must retain the above copyright
notice, this list of conditions and the following disclaimer.
2. Redistributions in binary form must reproduce the above copyright
notice, this list of conditions and the following disclaimer in the
documentation and/or other materials provided with the distribution.
3. The name of the author may not be used to endorse or promote products
derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE AUTHOR ``AS IS'' AND ANY EXPRESS OR
IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES
OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT,
INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/
if (!defined('WIKINI_VERSION'))
{
	die ("acc&egrave;s direct interdit");
}
?>

<link href="tools/templates/presentation/styles/bootstrap.min.css" rel="stylesheet">
<link href="tools/templates/presentation/styles/install.css" rel="stylesheet">

<form class="form-horizontal" action="<?php echo  myLocation() ?>?installAction=install" method="post">

	<div class="row span12">

	<div class="hero-unit"><h1>Installation de YesWiki</h1>

	<?php
	if (($wakkaConfig["wakka_version"]) || ($wakkaConfig["wikini_version"]))
	{
		if ($wakkaConfig["wikini_version"]) {
			$config=$wakkaConfig["wikini_version"];
		}
		else {
			$config=$wakkaConfig["wakka_version"];
		}
		echo "<p>Votre syst&egrave;me YesWiki existant a &eacute;t&eacute;",
			" reconnu comme &eacute;tant la version ",$config,". Vous &ecirc;tes sur le point ",
			"de <b>mettre &agrave; jour</b> YesWiki pour la version ",WIKINI_VERSION,
			". Veuillez revoir vos informations de configuration ci-dessous.</p>\n";
		$wiki = new Wiki($wakkaConfig);
	}
	else
	{
		echo "<h4>(Cercopith&egrave;que)</h4><p>Veuillez compl&eacute;ter le formulaire suivant :</p>\n";
		$wiki = null;
	}
	?>

	</div>

	<fieldset>
	
		<legend class="legend-first">Configuration de la base de donn&eacute;es <span>(Informations fournies par votre h&eacute;bergeur)</span></legend>
			
			<div class="accordion-heading">
				<a class="plusinfosfirst btn btn-mini btn-info" data-toggle="collapse" data-parent="#accordion2" href="#collapseOne">+ Infos</a>
			</div>
			
			<div class="accordion" id="accordion2">
				<div class="accordion-group">
					<div id="collapseOne" class="accordion-body collapse">
						<div class="accordion-inner">
							<dl class="dl-horizontal">
    							<dt>Machine MySQL </dt>
    							<dd>La machine sur laquelle se trouve votre serveur MySQL</dd>
    						</dl>
    						<dl class="dl-horizontal">
    							<dt>Base de donn&eacute;es MySQL </dt>
    							<dd>Cette base de donn&eacute;es doit d&eacute;j&agrave; exister avant de pouvoir continuer</dd>
    						</dl>
    						<dl class="dl-horizontal">
    							<dt>Nom de l'utilisateur MySQL </dt>
    							<dd>N&eacute;cessaire pour se connecter &agrave; votre base de donn&eacute;es</dd>
    						</dl>
    						<dl class="dl-horizontal">
    							<dt>Pr&eacute;fixe des tables </dt>
    							<dd>Permet d'utiliser plusieurs YesWiki sur une m&ecirc;me base de donn&eacute;es<br>
    								(Un pr&eacute;fixe diff&eacute;rent pour chaque YesWiki)</dd>
    						</dl>
						</div>
					</div>
				</div>
			</div>

			<div class="control-group">
				<label class="control-label">Machine MySQL</label>
				<div class="controls">
					<input type="text" required class="input-large" name="config[mysql_host]" value="<?php echo $wakkaConfig["mysql_host"] ?>" />
				</div>
			</div>

			<div class="control-group">
				<label class="control-label">Base de donn&eacute;es MySQL</label>
				<div class="controls">
					<input type="text" required class="input-large" name="config[mysql_database]" value=""/>
				</div>
			</div>

			<div class="control-group">
				<label class="control-label">Nom de l'utilisateur MySQL</label>
				<div class="controls">
					<input type="text" required class="input-large" name="config[mysql_user]" value="" />
				</div>
			</div>

			<div class="control-group">
				<label class="control-label">Mot de passe MySQL</label>
				<div class="controls">
					<input type="password"  class="input-large" name="config[mysql_password]" value="" />
				</div>
			</div>

			<div class="control-group">
				<label class="control-label">Pr&eacute;fixe des tables</label>
				<div class="controls">
					<input type="text" required class="input-large" name="config[table_prefix]" value="<?php echo $wakkaConfig["table_prefix"] ?>" />
				</div>
			</div>

		<legend>Configuration de votre site YesWiki</legend>

			<div class="accordion-heading">
				<a class="plusinfos btn btn-mini btn-info" data-toggle="collapse" data-parent="#accordion2" href="#collapseTwo">+ Infos</a>
			</div>
			
			<div class="accordion" id="accordion2">
				<div class="accordion-group">
					<div id="collapseTwo" class="accordion-body collapse">
						<div class="accordion-inner">
							<dl class="dl-horizontal">
    							<dt>Nom de votre site</dt>
    							<dd>Ceci est g&eacute;n&eacute;ralement un NomWiki et EstSousCetteForme</dd>
    						</dl>
    						<dl class="dl-horizontal">
    							<dt>Home page</dt>
    							<dd>La page d'accueil de votre YesWiki. Elle doit &ecirc;tre un NomWiki</dd>
    						</dl>
    						<dl class="dl-horizontal">
    							<dt>Mots clefs</dt>
    							<dd>META Mots clefs/Description qui seront ins&eacute;r&eacute;s dans les codes HTML</dd>
    						</dl>
    						<dl class="dl-horizontal">
    							<dt>Description</dt>
    							<dd>La description de votre site</dd>
    						</dl>
						</div>
					</div>
				</div>
			</div>

			<div class="control-group">
				<label class="control-label">Nom de votre site</label>
				<div class="controls">
					<input type="text" required class="input-large" name="config[wakka_name]" value="<?php echo $wakkaConfig["wakka_name"] ?>" />
				</div>
			</div>

			<div class="control-group">
				<label class="control-label">Home page</label>
				<div class="controls">
					<input type="text" required class="input-large" name="config[root_page]" value="<?php echo $wakkaConfig["root_page"] ?>" />
				</div>
			</div>

			<div class="control-group">
				<label class="control-label">Mots clefs</label>
				<div class="controls">
					<input type="text" class="input-large" name="config[meta_keywords]" value="<?php echo $wakkaConfig["meta_keywords"] ?>" />
				</div>
			</div>

			<div class="control-group">
				<label class="control-label">Description</label>
				<div class="controls">
					<input type="text" class="input-large" name="config[meta_description]" value="<?php echo $wakkaConfig["meta_description"] ?>" />
				</div>
			</div>

		<legend>Cr&eacute;ation d'un compte administrateur</legend>

			<div class="accordion-heading">
				<a class="plusinfos btn btn-mini btn-info" data-toggle="collapse" data-parent="#accordion2" href="#collapseThree">+ Infos</a>
			</div>
			
			<div class="accordion" id="accordion2">
				<div class="accordion-group">
					<div id="collapseThree" class="accordion-body collapse">
						<div class="accordion-inner">
							<dl class="dl-horizontal">
    							<dt class="admin">Le compte administrateur permet de</dt>
    							<dd class="droits-admin">
    								<ul>
    									<li>Modifier et supprimer n'importe quelle page</li>
    									<li>Modifier les droits d'acc&egrave;s &agrave; n'importe quelle page</li>
    									<li>G&eacute;rer les droits d'acc&egrave;s &agrave; n'importe quelle action ou handler</li>
    									<li>G&eacute;rer les groupes, ajouter/supprimer des utilisateurs au groupe administrateur</br>
    										(ayant les m&ecirc;mes droits que lui)</li>
    								</ul>
    							</dd>
    						</dl>
							<div class="control-group">
								<p>Toutes les t&acirc;ches d'administration sont d&eacute;crites dans la page "<span>AdministrationDeWikiNi</span>" accessible depuis la page d'accueil</p>
							</div>
						</div>
					</div>
				</div>
			</div>

	<?php
		if ($wiki && $users = $wiki->LoadUsers())
		{
	?>
	<div class="controls"><p>Utiliser un compte existant :</p><br>
			<select name="admin_login">
			<option selected="selected">non</option>
	<?php
			foreach ($users as $user)
			{
				echo '<option value="', htmlspecialchars($user['name']), '">', htmlspecialchars($user['name']), "</option>\n";
			}
	?>
		</select>
		<p>Ou cr&eacute;er un nouveau compte :</p></div>
	<?php
		}
	?>

			<div class="control-group">
				<label class="control-label">Administrateur</label>
				<div class="controls">
					<input type="text" required class="input-large" name="admin_name" value="WikiAdmin" /> (choississez un NomWiki)
				</div>
			</div>

			<div class="control-group">
				<label class="control-label">Mot de passe</label>
				<div class="controls">
					<input type="password" required class="input-large" name="admin_password" value="" /> (minimum 6 caract&egrave;res)
				</div>
			</div>

			<div class="control-group">
				<label class="control-label">Confirmation du mot de passe</label>
				<div class="controls">
					<input type="password" required class="input-large" size="50" name="admin_password_conf" value="" />
				</div>
			</div>

			<div class="control-group">
				<label class="control-label">Adresse e-mail</label>
				<div class="controls">
					<input type="text" required class="input-large" name="admin_email" value="" />
				</div>
			</div>

   		<legend>Options suppl&eacute;mentaires</legend>
   	
			<div class="accordion-heading">
				<a class="plusinfos btn btn-mini btn-info" data-toggle="collapse" data-parent="#accordion2" href="#collapseFour">+ Configuration avanc&eacute;e</a>
			</div>
			
			<div class="accordion" id="accordion2">
				<div class="accordion-group">
					<div id="collapseFour" class="accordion-body collapse">
						<div class="accordion-inner">

						<p><legend><span>Redirection d'URL</span></legend></p>

						<?php
							if (!$wakkaConfig["wikini_version"])
							{
								echo "Ceci est une nouvelle installation. Le programme d'installation",
									" va essayer de trouver les valeurs appropri&eacute;es. <br />Changez-les uniquement" .
									" si vous savez ce que vous faites.</p>";
							} ?>

						<p>Les noms des pages seront directement rajout&eacute;s &agrave; l'URL de base de votre site YesWiki.<br />
							Supprimez la partie "?wiki=" uniquement si vous utilisez la redirection (voir ci apr&egrave;s).</p>

							<div class="control-group">
								<label class="control-label">URL de base </label>
								<div class="controls">
									<input type="text" class="input-xxlarge" name="config[base_url]" value="<?php echo $wakkaConfig["base_url"] ?>" />
								</div>
							</div>

						<p>Le mode "redirection automatique" doit &ecirc;tre s&eacute;lectionn&eacute; uniquement si vous utilisez YesWiki avec la redirection d'URL <br />
						(si vous ne savez pas ce qu'est la redirection d'URL n'activez pas cette option).</p>

							<div class="control-group">
								<label class="checkbox">
									<input type="hidden" name="config[rewrite_mode]" value="0" />
									<input type="checkbox" name="config[rewrite_mode]" value="1" <?php echo $wakkaConfig["rewrite_mode"] ? "checked" : "" ?> >  &nbsp;Activation du mode "redirection automatique"
								</label>
							</div>

						<p><legend><span>Autres Options</span></legend></p>

							<!-- option apercu avant sauvegarde de page -->
							<!--<div class="control-group">
								<label class="checkbox">
									<input type="hidden" name="config[preview_before_save]" value="0" />
									<input type="checkbox" name="config[preview_before_save]" value="1" <?php //echo $wakkaConfig["preview_before_save"] ? "checked" : "" ?> />
									 &nbsp;Imposer de faire un aper&ccedil;u avant de pouvoir sauver une page
								</label>
							</div>-->

							<div class="control-group">
								<label class="checkbox">
									<input type="checkbox" name="config[allow_raw_html]" value="1" <?php echo $wakkaConfig['allow_raw_html'] ? '' : 'checked' ?> />
									 &nbsp;Autoriser l'insertion de HTML brut<br />
								</label>
							</div>

						</div>   
					</div>
				</div>
			</div>

	<div class="form-actions">
		<input class="btn btn-large btn-primary continuer" type="submit" value="Continuer" />
	</div>
	
	</fieldset>

</form>

</div> <!-- row -->

<script src="tools/templates/libs/jquery-1.8.2.min.js"></script>
<script src="tools/templates/libs/bootstrap.min.js"></script>
<script src="tools/templates/libs/bootstrap/js/bootstrap-popover.js"></script>

    <script type="text/javascript">
      $(function(){ 
        $("#example").popover();
      });
    </script>