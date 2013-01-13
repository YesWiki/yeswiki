<?php
/*vim: set expandtab tabstop=4 shiftwidth=4: */
// +------------------------------------------------------------------------------------------------------+
// | PHP version 5                                                                                        |
// +------------------------------------------------------------------------------------------------------+
// | Copyright (C) 2012 Outils-R?seaux (accueil@outils-reseaux.org)                                       |
// +------------------------------------------------------------------------------------------------------+
// | This library is free software; you can redistribute it and/or                                        |
// | modify it under the terms of the GNU Lesser General Public                                           |
// | License as published by the Free Software Foundation; either                                         |
// | version 2.1 of the License, or (at your option) any later version.                                   |
// |                                                                                                      |
// | This library is distributed in the hope that it will be useful,                                      |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of                                       |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU                                    |
// | Lesser General Public License for more details.                                                      |
// |                                                                                                      |
// | You should have received a copy of the GNU Lesser General Public                                     |
// | License along with this library; if not, write to the Free Software                                  |
// | Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA                            |
// +------------------------------------------------------------------------------------------------------+
// 
/**
* Edition du Yeswiki
*
*@package 		templates
*@author        Florian Schmitt <florian@outils-reseaux.org>
*@copyright     2012 Outils-R?seaux
*/

if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}

// on enleve l'action template
$plugin_output_new = preg_replace ("/".'(\\{\\{template)'.'(.*?)'.'(\\}\\})'."/is", '', $plugin_output_new);

// on enleve les restes de wikini : script obscur de la barre de redaction
$plugin_output_new = str_replace("<script type=\"text/javascript\">\n".
				"document.getElementById(\"body\").onkeydown=fKeyDown;\n".
				"</script>\n", '', $plugin_output_new);

// personnalisation graphique que dans le cas ou on est autoris?
if ((!isset($this->config['hide_action_template']) or (isset($this->config['hide_action_template']) && !$this->config['hide_action_template'])) && 
	($this->HasAccess("write") && $this->HasAccess("read") && (!SEUL_ADMIN_ET_PROPRIO_CHANGENT_THEME || (SEUL_ADMIN_ET_PROPRIO_CHANGENT_THEME && ($this->UserIsAdmin() || $this->UserIsOwner() ) ) ) ) ) { 

	$selecteur = '<div id="graphical_options" class="modal hide fade">'."\n".
				'<div class="modal-header">'."\n".
					'<a class="close" data-dismiss="modal">&times;</a>'."\n".
					'<h3>'.TEMPLATE_CUSTOM_GRAPHICS.' '.$this->GetPageTag().'</h3>'."\n".
				'</div>'."\n".
				'<div class="modal-body">'."\n";
	$selecteur .= show_form_theme_selector('edit');
	$selecteur .= '</div>'."\n".
				'<div class="modal-footer">'."\n".
					'<a href="#" class="btn button_cancel" data-dismiss="modal">'.TEMPLATE_CANCEL.'</a>'."\n".
					'<a href="#" class="btn btn-primary button_save" data-dismiss="modal">'.TEMPLATE_APPLY.'</a>'."\n".						
				'</div>'."\n".	
			'</div>'."\n";

	$js = add_templates_list_js().'<script src="tools/templates/libs/templates_edit.js"></script>'."\n";

	//quand le changement des valeurs du template est cach?, il faut stocker les valeurs d?ja entr?es pour ne pas retourner au template par d?faut
	$selecteur .= '<input id="hiddentheme" type="hidden" name="theme" value="'.$this->config['favorite_theme'].'" />'."\n";
	$selecteur .= '<input id="hiddensquelette" type="hidden" name="squelette" value="'.$this->config['favorite_squelette'].'" />'."\n";
	$selecteur .= '<input id="hiddenstyle" type="hidden" name="style" value="'.$this->config['favorite_style'].'" />'."\n";
	$selecteur .= '<input id="hiddenbgimg" type="hidden" name="bgimg" value="'.$this->config['favorite_background_image'].'" />'."\n";


	// on rajoute la personnalisation graphique
	$plugin_output_new = preg_replace('/<\/body>/', $selecteur."\n".$js."\n".'</body>', $plugin_output_new);
	$changetheme = TRUE;
} else {
	$changetheme = FALSE;
}

// le bouton apercu c'est pour les vieilles versions de wikini, on en profite pour rajouter des classes pour colorer les boutons et la personnalisation graphique
$patterns = array(	0 => 	'/<input name=\"submit\" type=\"submit\" value=\"Sauver\" accesskey=\"s\" \/>/',
					1 => 	'/<input name=\"submit\" type=\"submit\" value=\"Aper\&ccedil;u\" accesskey=\"p\" \/>/',
					2 => 	'/<input type=\"button\" value=\"Annulation\" onclick=\"document.location=\'' . preg_quote(addslashes($this->href()), '/') . '\';\" \/>/'
					);
$replacements = array(
					0 => 	'<div class="form-actions">'."\n".'<button type="submit" name="submit" value="Sauver" class="btn btn-primary">'.TEMPLATE_SAVE.'</button>',
					1 => 	'', 
					2 => 	'<button class="btn" onclick="location.href=\''.addslashes($this->href()).'\';return false;">'.TEMPLATE_CANCEL.'</button>'."\n".
							(($changetheme) ? '<button class="btn btn-info offset1" data-toggle="modal" data-target="#graphical_options" data-backdrop="false">'.TEMPLATE_THEME.'</button>'."\n" : '').'</div>' // le bouton Theme du bas de l'interface d'edition
					);
$plugin_output_new = preg_replace($patterns, $replacements, $plugin_output_new);

?>
