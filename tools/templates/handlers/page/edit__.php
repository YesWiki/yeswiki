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
 * Edition du Yeswiki.
 *
 *@author        Florian Schmitt <florian@outils-reseaux.org>
 *@copyright     2012 Outils-Reseaux
 */
if (!defined('WIKINI_VERSION')) {
    die('acc&egrave;s direct interdit');
}

// on enleve l'action template
$plugin_output_new = preg_replace('/'.'(\\{\\{template)'.'(.*?)'.'(\\}\\})'.'/is', '', $plugin_output_new);

// on enleve les restes de wikini : script obscur de la barre de redaction
$plugin_output_new = str_replace("<script type=\"text/javascript\">\n".
                "document.getElementById(\"body\").onkeydown=fKeyDown;\n".
                "</script>\n", '', $plugin_output_new);

// personnalisation graphique que dans le cas ou on est autorise
if ((!isset($this->config['hide_action_template']) or (isset($this->config['hide_action_template']) && !$this->config['hide_action_template'])) &&
    ($this->HasAccess('write') && $this->HasAccess('read') && (!SEUL_ADMIN_ET_PROPRIO_CHANGENT_THEME || (SEUL_ADMIN_ET_PROPRIO_CHANGENT_THEME && ($this->UserIsAdmin() || $this->UserIsOwner()))))) {

    // graphical options : theme and background image
    $selecteur = '
<div id="graphical_options" class="modal fade">'."\n".
    '  <div class="modal-dialog">'."\n".
    '    <div class="modal-content">'."\n".
    '      <div class="modal-header">'."\n".
    '        <a class="close" data-dismiss="modal">&times;</a>'."\n".
    '        <h3>'._t('TEMPLATE_CUSTOM_GRAPHICS').' '.$this->GetPageTag().'</h3>'."\n".
    '      </div>'."\n".
    '      <div class="modal-body">'."\n";
    $selecteur .= show_form_theme_selector('edit');
    $selecteur .= '
      </div>'."\n".
      '      <div class="modal-footer">'."\n".
      '        <a href="#" class="btn btn-default button_cancel" data-dismiss="modal">'._t('TEMPLATE_CANCEL').'</a>'."\n".
      '        <a href="#" class="btn btn-primary button_save" data-dismiss="modal">'._t('TEMPLATE_APPLY').'</a>'."\n".
      '      </div>'."\n".
      '    </div>'."\n".
      '  </div>'."\n".
      '</div> <!-- /#graphical_options -->'."\n";

    $js = add_templates_list_js().'<script src="tools/templates/libs/templates_edit.js"></script>'."\n";

    //quand le changement des valeurs du template est cache, il faut stocker les valeurs deja entrees pour ne pas retourner au template par defaut
    $selecteur .= '<input id="hiddentheme" type="hidden" name="theme" value="'.$this->config['favorite_theme'].'" />'."\n";
    $selecteur .= '<input id="hiddensquelette" type="hidden" name="squelette" value="'.$this->config['favorite_squelette'].'" />'."\n";
    $selecteur .= '<input id="hiddenstyle" type="hidden" name="style" value="'.$this->config['favorite_style'].'" />'."\n";
    $selecteur .= '<input id="hiddenbgimg" type="hidden" name="bgimg" value="'.$this->config['favorite_background_image'].'" />'."\n";

    // on rajoute la personnalisation graphique
    $plugin_output_new = preg_replace('/<\/body>/', $selecteur."\n".$js."\n".'</body>', $plugin_output_new);
    $changetheme = true;
} else {
    $changetheme = false;
}

$hidden = '';
// cas des pages speciales
if (isset($_SERVER["HTTP_REFERER"])) {
    $pagetag = str_replace($this->config['base_url'], '', $_SERVER["HTTP_REFERER"]);
    if ($this->IsWikiName($pagetag) && in_array(
        $pagetag,
        array('PageFooter', 'PageHeader', 'PageTitre', 'PageRapideHaut','PageMenuHaut', 'PageMenu')
    )) {
        $hidden = '<input type="hidden" name="returnto" value="'.$this->href('', $pagetag).'" />'."\n";
    }
}

// le bouton apercu c'est pour les vieilles versions de wikini, on en profite pour rajouter des classes pour colorer les boutons et la personnalisation graphique
$patterns = array(0 => '/<input name=\"submit\" type=\"submit\" value=\"Sauver\" accesskey=\"s\" \/>/',
                    1 => '/<input name=\"submit\" type=\"submit\" value=\"Aper\&ccedil;u\" accesskey=\"p\" \/>/',
                    2 => '/<input type=\"button\" value=\"Annulation\" onclick=\"document.location=\''.preg_quote(addslashes($this->href()), '/').'\';\" \/>/',
                    3 => '/ class=\"edit\">/',
                    );
$replacements = array(
                    0 => $hidden.'<div class="form-actions">'."\n".'<button type="submit" name="submit" value="Sauver" class="btn btn-primary">'._t('TEMPLATE_SAVE').'</button>'."\n",
                    1 => '',
                    2 => '<button class="btn btn-default" onclick="location.href=\''.addslashes($this->href()).'\';return false;">'._t('TEMPLATE_CANCEL').'</button>'."\n".
                            (($changetheme) ? '<a class="btn btn-info offset1 col-lg-offset-1" data-toggle="modal" data-target="#graphical_options" data-backdrop="false">'._t('TEMPLATE_THEME').'</a>'."\n" : '').'</div>'."\n", // le bouton Theme du bas de l'interface d'edition
                    3 => ' class="edit form-control">',
                    );
$plugin_output_new = preg_replace($patterns, $replacements, $plugin_output_new);
