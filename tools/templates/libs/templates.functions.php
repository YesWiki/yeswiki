<?php
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}


/**
 *
 * Verifie si le nombre d'elements graphiques d'un type trouvés et de leur fermeture correspondent
 *
 * @param $element : name of element
 *
 * return bool vrai si chaque élément est bien fermé
 */
function check_graphical_elements($element, $pagetag, $pagecontent)
{
    preg_match_all('/{{'.$element.'.*}}/Ui', $pagecontent, $matchesaction);
    preg_match_all('/{{end.*elem="'.$element.'".*}}/Ui', $pagecontent, $matchesendaction);
    return count($matchesaction[0]) == count($matchesendaction[0]);
}


/**
 *
 * Parcours des dossiers a la recherche de templates
 *
 * @param $directory : chemin relatif vers le dossier contenant les templates
 *
 * return array : tableau des themes trouves, ranges par ordre alphabetique
 *
 */
function search_template_files($directory)
{
    $tab_themes = array();
    $dir = opendir($directory);
    while ($dir && ($file = readdir($dir)) !== false) {
        if ($file!='.' && $file!='..' && $file!='CVS' && is_dir($directory.DIRECTORY_SEPARATOR.$file)) {
            if (is_dir($directory.DIRECTORY_SEPARATOR.$file.DIRECTORY_SEPARATOR.'styles')
                && is_dir($directory.DIRECTORY_SEPARATOR.$file.DIRECTORY_SEPARATOR.'squelettes')) {
                $dir2 = opendir($directory.DIRECTORY_SEPARATOR.$file.DIRECTORY_SEPARATOR.'styles');
                while (false !== ($file2 = readdir($dir2))) {
                    if (substr($file2, -4, 4)=='.css' || substr($file2, -5, 5)=='.less') {
                        $tab_themes[$file]["style"][$file2] = $file2;
                    }
                }
                closedir($dir2);
                if (is_array($tab_themes[$file]["style"])) {
                    ksort($tab_themes[$file]["style"]);
                }
                $dir3 = opendir($directory.DIRECTORY_SEPARATOR.$file.DIRECTORY_SEPARATOR.'squelettes');
                while (false !== ($file3 = readdir($dir3))) {
                    if (substr($file3, -9, 9)=='.tpl.html') {
                        $tab_themes[$file]["squelette"][$file3]=$file3;
                    }
                }
                closedir($dir3);
                if (is_array($tab_themes[$file]["squelette"])) {
                    ksort($tab_themes[$file]["squelette"]);
                }
            }
        }
    }
    closedir($dir);

    if (is_array($tab_themes)) {
        ksort($tab_themes);
    }

    return $tab_themes;
}



/**
*
* remplace juste la premiere occurence d'une chaine de caracteres
*
* @param $from : partie de la chaine recherch?e
* @param $to   : chaine de remplacement
* @param $str  : chaine entree
*
* return string : chaine entree avec la premiere occurence changee
*
*/
function str_replace_once($from, $to, $str)
{
    if (!$newStr = strstr($str, $from)) {
        return $str;
    }
    $iNewStrLength = strlen($newStr);
    $iFirstPartlength = strlen($str) - $iNewStrLength;
    return substr($str, 0, $iFirstPartlength).$to.substr($newStr, strlen($from), $iNewStrLength);
}

// str_ireplace est php5 seulement
if (!function_exists('str_ireplacement')) {
    function str_ireplacement($search, $replace, $subject)
    {
        $token = chr(1);
        $haystack = strtolower($subject);
        $needle = strtolower($search);
        while (($pos=strpos($haystack, $needle))!==false) {
            $subject = substr_replace($subject, $token, $pos, strlen($search));
            $haystack = substr_replace($haystack, $token, $pos, strlen($search));
        }
        $subject = str_replace($token, $replace, $subject);
        return $subject;
    }
}


/**
*
* savoir si l'url est bien une image
*
* @param $url : url de l'image
*
* return boolean : indique si l'url est une image ou pas
*
*/
function image_exists($url)
{
    $info = @getimagesize($url);
    return((bool) $info);
}

//fonction recursive pour detecter un nomwiki deja present
function nomwikidouble($nomwiki, $nomswiki)
{
    if (in_array($nomwiki, $nomswiki)) {
        return nomwikidouble($nomwiki.'bis', $nomswiki);
    } else {
        return $nomwiki;
    }
}

//fonction pour remplacer les liens vers les NomWikis n'existant pas
function replace_missingpage_links($output)
{
    $pattern = '/<span class="(forced-link )?missingpage">(.*)<\/span><a href="'.str_replace(
        array('/', '?'),
        array('\/', '\?'),
        $GLOBALS['wiki']->config['base_url']
    ).'(.*)\/edit">\?<\/a>/U';
    preg_match_all($pattern, $output, $matches, PREG_SET_ORDER);

    foreach ($matches as $values) {
        // on passe en parametres GET les valeurs du template de la page de provenance,
        // pour avoir le meme graphisme dans la page creee
        $query_string = 'theme='.urlencode($GLOBALS['wiki']->config['favorite_theme']).
                        '&amp;squelette='.urlencode($GLOBALS['wiki']->config['favorite_squelette']).
                        '&amp;style='.urlencode($GLOBALS['wiki']->config['favorite_style']).
                        '&amp;bgimg='.urlencode($GLOBALS['wiki']->config['favorite_background_image']).
                        ((!$GLOBALS['wiki']->IsWikiName($values[2])) ? '&amp;body='.urlencode($values[2]) : '').
                        '&amp;newpage=1';
        $replacement = '<a class="yeswiki-editable" title="'._t('TEMPLATE_EDIT_THIS_PAGE').'" href="'
            .$GLOBALS['wiki']->href("edit", $values[3], $query_string)
            .'">'
            .$values[2].' <i class="glyphicon glyphicon-pencil"></i></a>';
            if (empty($values[1])) {
                $replacement .= '<a href="'.$GLOBALS['wiki']->href('escapeword', $GLOBALS['wiki']->getPageTag(), 'word='.urlencode($values[3])).'" title="'._t('TEMPLATE_WIKINAME_IS_NOT_A_PAGE').'"><i class="glyphicon glyphicon-ok"></i></a>';
            }
        $output = str_replace_once($values[0], $replacement, $output);
    }

    return $output;
}


/**
 *
 * cree un diaporama a partir d'une PageWiki
 *
 * @param $pagetag : nom de la PageWiki
 * @param $template : fichier template pour le diaporama
 * @param $class : classe CSS a ajouter au diaporama
 *
 */
function print_diaporama($pagetag, $template = 'diaporama_slides.tpl.html', $class = '')
{
    // On teste si l'utilisateur peut lire la page
    if (!$GLOBALS['wiki']->HasAccess("read", $pagetag)) {
        return '<div class="alert alert-danger">'
            ._t('TEMPLATE_NO_ACCESS_TO_PAGE').'</div>'
            .$GLOBALS['wiki']->Format('{{login template="minimal.tpl.html"}}');
    } else {
        // On teste si la page existe
        if (!$page = $GLOBALS['wiki']->LoadPage($pagetag)) {
            return '<div class="alert alert-danger">'._t('TEMPLATE_PAGE_DOESNT_EXIST').' ('.$pagetag.').</div>';
        } else {
            $body_f = $GLOBALS['wiki']->Format($page["body"]);
            // on regarde si on gere la 2d pour reveal
            //preg_match_all('/<h1>.*<\/h1>/m', $body_f, $titles);
            preg_match_all('/======.*======/Um', $page["body"], $titles);
            $istwodimensions = count($titles[0]) > 1;
            $first = true;
            // on decoupe pour chaque titre de niveau 1 ou 2, ou chaque fois que background-image est utilisée
            // $body = preg_split(
            //     '/(.*<h[12]>.*<\/h[12]>)'
            //     .'|(.*<div class="background-image.*">.*<\!-- \/\.background-image -->)/m',
            //     $body_f,
            //     -1,
            //     PREG_SPLIT_DELIM_CAPTURE
            // );
            $body = preg_split(
                '/(\======.*======)'
                .'|(=====.*=====)'
                .'|(\{\{backgroundimage.*\}\}\s*.*\s*\{\{endbackgroundimage\}\})/Um',
                $page["body"],
                -1,
                PREG_SPLIT_DELIM_CAPTURE
            );
            //var_dump($body);break;
            if (!$body) {
                return '<div class="=alert alert-danger">'
                    ._t('TEMPLATE_PAGE_CANNOT_BE_SLIDESHOW').' ('.$pagetag.').</div>';
            } else {
                // preparation des tableaux pour le squelette -------------------------
                $i = 0 ;
                $slides = array() ;
                $titles = array() ;
                $previousistitle = false;
                foreach ($body as $slide) {
                    $slide = $GLOBALS['wiki']->Format($slide);
                    //var_dump($slide);
                    // s'il a des titres de niveau 1 ou 2 il s'agit des separateurs de diapo
                    if (preg_match('/<h[12]>.*<\/h[12]>/', $slide)) {
                        // s'il y a un titre de niveau 1 qui commence la diapositive, on la deplace en titre
                        // et on gere l'aspect multidimentionnel
                        if (preg_match('/<h1>.*<\/h1>/', $slide)) {
                            if ($istwodimensions) {
                                if ($first) {
                                    $first = false;
                                } else {
                                    $slides[$i]['closesection'] = true;
                                }
                                $slides[$i]['opensection'] = true;
                            }
                        }
                        //pour les titres de niveau 2, on les transforme en titre 1
                        $titles[$i] = str_replace('<h2', '<h1', $slide);
                        if ($previousistitle) {
                            $slides[$i]['html'] = '';
                            $i++;
                        }
                        $previousistitle = true;
                    } elseif (!empty($slide) || $previousistitle) {
                        $previousistitle = false;
                        $slides[$i]['html'] = $slide ;
                        $slides[$i]['title'] = ((isset($titles[$i])) ? strip_tags($titles[$i]) : '') ;
                        $i++;
                    }
                }
            }
        }


        $buttons = '';
        //si la fonction est appelee par le handler diaporama, on ajoute les liens d'edition et de retour
        if ($GLOBALS['wiki']->GetMethod() == "diaporama") {
            $buttons .= '<a class="btn" href="'.$GLOBALS['wiki']->href('', $pagetag).'">&times;</a>'."\n";
        }

        //on affiche le template

        include_once('includes/squelettephp.class.php');

        $squel = new SquelettePhp('tools/templates/presentation/templates/'.$template);
        $squel->set(array(
            "pagetag" => $pagetag,
            "slides" => $slides,
            "titles" => $titles,
            "buttons" => $buttons,
            "class" => $class
        ));
        $output = $squel->analyser() ;

        return $output;
    }
}

function show_form_theme_selector($mode = 'selector', $formclass = 'form-horizontal')
{
    // en mode edition on recupere aussi les images de fond
    if ($mode=='edit') {
        $id = 'form_graphical_options';
        // recuperation des images de fond
        $backgroundsdir = 'files/backgrounds';
        $dir = (is_dir($backgroundsdir) ? opendir($backgroundsdir) : false);
        while ($dir && ($file = readdir($dir)) !== false) {
            $imgextension = strtolower(substr($file, -4, 4));
            // les jpg sont les fonds d'ecrans, ils doivent etre mis en miniature
            if ($imgextension == '.jpg') {
                if (!is_file($backgroundsdir.'/thumbs/'.$file)) {
                    require_once 'tools/attach/libs/class.imagetransform.php';
                    $imgTrans = new imageTransform();
                    $imgTrans->sourceFile = $backgroundsdir.'/'.$file;
                    $imgTrans->targetFile = $backgroundsdir.'/thumbs/'.$file;
                    $imgTrans->resizeToWidth = 100;
                    $imgTrans->resizeToHeight = 75;
                    if ($imgTrans->resize()) {
                        $backgrounds[] = $imgTrans->targetFile;
                    }
                } else {
                    $backgrounds[] = $backgroundsdir.'/thumbs/'.$file;
                }
            } elseif ($imgextension == '.png') {
                // les png sont les images a repeter en mosaique
                $backgrounds[] = $backgroundsdir.'/'.$file;
            }
        }
        if ($dir) {
            closedir($dir);
        }

        $bgselector = '';

        if (isset($backgrounds) && is_array($backgrounds)) {
            $bgselector .= '<h3>'._t('TEMPLATE_BG_IMAGE').'</h3>
            <div id="bgCarousel" class="carousel" data-interval="5000" data-pause="true">
        <!-- Carousel items -->
        <div class="carousel-inner">'."\n";
            $nb = 0;
            $thumbs_per_slide = 8;
            $firstitem = true;
            sort($backgrounds);

            foreach ($backgrounds as $background) {
                $nb++;
                if ($nb == 1) {
                    $bgselectorlist = '';
                    $class = '';
                }

                // dans le cas ou il n'y a pas d'image de fond selectionnee on bloque la premiere diapo
                if ($GLOBALS['wiki']->config['favorite_background_image'] == '' && $firstitem) {
                    $class = ' active';
                    $firstitem = false;
                }

                $choosen = ($background == 'files/backgrounds/'.$GLOBALS['wiki']->config['favorite_background_image']);
                if ($choosen) {
                    $class = ' active';
                }

                $imgextension = strtolower(substr($background, -4, 4));

                if ($imgextension=='.jpg') {
                    $bgselectorlist .= '<img class="bgimg'.($choosen ? ' choosen' : '').'" src="'.$background.'" width="100" height="75" />'."\n";
                } elseif ($imgextension=='.png') {
                    $bgselectorlist .= '<div class="mozaicimg'.($choosen ? ' choosen' : '').'" style="background:url('.$background.') repeat top left;"></div>'."\n";
                }
                // on finit la diapositive
                if ($nb == $thumbs_per_slide) {
                    $nb=0;
                    $bgselector .= '<div class="item'.$class.'">'."\n".$bgselectorlist.'</div>'."\n";
                }
            }
            // si la boucle se termine et qu'on ne vient pas de finir une diapositive
            if ($nb != 0) {
                $bgselector .= '<div class="item'.$class.'">'."\n".$bgselectorlist.'</div>'."\n";
            }
            $bgselector .= '</div>
        <!-- Carousel nav -->
        <a class="carousel-control left" href="#bgCarousel" data-slide="prev">&lsaquo;</a>
        <a class="carousel-control right" href="#bgCarousel" data-slide="next">&rsaquo;</a>
        </div>'."\n";
        }
    } else {
        $id = 'form_theme_selector';
        $bgselector = '';
    }

    $selecteur = '      <form class="'.$formclass.'" id="'.$id.'">'."\n";


    $selecteur .= '         <div class="control-group form-group">'."\n".
                    '               <label class="control-label col-lg-4">'._t('TEMPLATE_THEME').'</label>'."\n".
                    '               <div class="controls col-lg-7">'."\n".
                    '                   <select class="form-control" id="changetheme" name="theme">'."\n";
    foreach (array_keys($GLOBALS['wiki']->config['templates']) as $key => $value) {
        if ($value !== $GLOBALS['wiki']->config['favorite_theme']) {
            $selecteur .= '                     <option value="'.$value.'">'.$value.'</option>'."\n";
        } else {
            $selecteur .= '                     <option value="'.$value.'" selected="selected">'.$value.'</option>'."\n";
        }
    }
    $selecteur .= '                 </select>'."\n".'               </div>'."\n".'          </div>'."\n";
    $selecteur .=
    '           <div class="control-group form-group">'."\n".
    '               <label class="control-label col-lg-4">'._t('TEMPLATE_SQUELETTE').'</label>'."\n".
    '               <div class="controls col-lg-7">'."\n".
    '                   <select class="form-control" id="changesquelette" name="squelette">'."\n";
    ksort($GLOBALS['wiki']->config['templates'][$GLOBALS['wiki']->config['favorite_theme']]['squelette']);
    foreach ($GLOBALS['wiki']->config['templates'][$GLOBALS['wiki']->config['favorite_theme']]['squelette'] as $key => $value) {
        if ($value !== $GLOBALS['wiki']->config['favorite_squelette']) {
            $selecteur .= '                     <option value="'.$key.'">'.$value.'</option>'."\n";
        } else {
            $selecteur .= '                     <option value="'.$GLOBALS['wiki']->config['favorite_squelette'].'" selected="selected">'.$value.'</option>'."\n";
        }
    }
    $selecteur .= '                 </select>'."\n".'               </div>'."\n".'          </div>'."\n";

    ksort($GLOBALS['wiki']->config['templates'][$GLOBALS['wiki']->config['favorite_theme']]['style']);
    $selecteur .=
    '           <div class="control-group form-group">'."\n".
    '               <label class="control-label col-lg-4">'._t('TEMPLATE_STYLE').'</label>'."\n".
    '               <div class="controls col-lg-7">'."\n".
    '                   <select class="form-control" id="changestyle" name="style">'."\n";
    foreach ($GLOBALS['wiki']->config['templates'][$GLOBALS['wiki']->config['favorite_theme']]['style'] as $key => $value) {
        if ($value !== $GLOBALS['wiki']->config['favorite_style']) {
            $selecteur .= '                     <option value="'.$key.'">'.$value.'</option>'."\n";
        } else {
            $selecteur .= '                     <option value="'.$GLOBALS['wiki']->config['favorite_style'].'" selected="selected">'.$value.'</option>'."\n";
        }
    }
    $selecteur .=   '                   </select>'."\n".'               </div>'."\n".'              </div>'."\n".$bgselector."\n";

    if ($mode == 'edit') {
        $selecteur .= '
            <div class="panel-group accordion" id="accordion-avanced-page-settings">
                <div class="panel panel-default accordion-group">
                    <div class="panel-heading accordion-heading">
                      <h4 class="panel-title accordion-trigger" data-parent="#accordion-avanced-page-settings" href="#avanced-page-settings" data-target="#avanced-page-settings" data-toggle="collapse" title="'._t('SEE_THE_ADVANCED_PARAMETERS').'">'._t('ADVANCED_PARAMETERS').'</h4>
                    </div>
                    <div id="avanced-page-settings" class="panel-collapse accordion-body collapse">
                        <div class="panel-body accordion-inner">
                            <div class="control-group form-group">
                                <label class="control-label col-lg-4">'._t('PAGE_LANGUAGE').'</label>
                                <div class="controls col-lg-7">
                                    <select class="form-control" name="lang">'."\n";

        // choice of language
        foreach ($GLOBALS['available_languages'] as $value) {
            $selecteur .=  "                                <option value=\"".$value."\"".(($value==$GLOBALS['prefered_language']) ? ' selected="selected"' : '').">".ucfirst(htmlentities($GLOBALS['languages_list'][$value]['nativeName'], ENT_COMPAT | ENT_HTML401, 'UTF-8'))."</option>\n";
        }
        $selecteur .= '                             </select>
                                </div>
                            </div>'."\n";

        $tablistWikinames = $GLOBALS['wiki']->LoadAll('SELECT DISTINCT tag FROM '. $GLOBALS['wiki']->GetConfigValue('table_prefix') .'pages WHERE latest="Y"');
        foreach ($tablistWikinames as $tag) {
            $listWikinames[] = $tag['tag'];
        }
        $listWikinames = '["'.implode('","', $listWikinames).'"]';

        $selecteur .= '         <fieldset>
                                <legend>'._t('CHOOSE_PAGE_FOR').' : </legend>
                                <div class="control-group form-group">
                                    <label class="control-label col-lg-4">'._t('HORIZONTAL_MENU_PAGE').'</label>
                                    <div class="controls col-lg-7">
                                        <input class="form-control" type="text" autocomplete="off" name="PageMenuHaut" data-provide="typeahead" data-items="5" data-source=\''.$listWikinames.'\' value="'.(isset($GLOBALS['wiki']->page["metadatas"]['PageMenuHaut']) ? $GLOBALS['wiki']->page["metadatas"]['PageMenuHaut'] : 'PageMenuHaut').'" />
                                    </div>
                                </div>'."\n";
        $selecteur .= '
                                <div class="control-group form-group">
                                    <label class="control-label col-lg-4">'._t('FAST_ACCESS_RIGHT_PAGE').'</label>
                                    <div class="controls col-lg-7">
                                        <input class="form-control" type="text" autocomplete="off" name="PageRapideHaut" data-provide="typeahead" data-items="5" data-source=\''.$listWikinames.'\' value="'.(isset($GLOBALS['wiki']->page["metadatas"]['PageRapideHaut']) ? $GLOBALS['wiki']->page["metadatas"]['PageRapideHaut'] : 'PageRapideHaut').'" />
                                    </div>
                                </div>'."\n";
        $selecteur .= '
                                <div class="control-group form-group">
                                    <label class="control-label col-lg-4">'._t('HEADER_PAGE').'</label>
                                    <div class="controls col-lg-7">
                                        <input class="form-control" type="text" autocomplete="off" name="PageHeader" data-provide="typeahead" data-items="5" data-source=\''.$listWikinames.'\' value="'.(isset($GLOBALS['wiki']->page["metadatas"]['PageHeader']) ? $GLOBALS['wiki']->page["metadatas"]['PageHeader'] : 'PageHeader').'" />
                                    </div>
                                </div>'."\n";
        $selecteur .= '
                                <div class="control-group form-group">
                                    <label class="control-label col-lg-4">'._t('FOOTER_PAGE').'</label>
                                    <div class="controls col-lg-7">
                                        <input class="form-control" type="text" autocomplete="off" name="PageFooter" data-provide="typeahead" data-items="5" data-source=\''.$listWikinames.'\' value="'.(isset($GLOBALS['wiki']->page["metadatas"]['PageFooter']) ? $GLOBALS['wiki']->page["metadatas"]['PageFooter'] : 'PageFooter').'" />
                                    </div>
                                </div>'."\n";
        $selecteur .= '         <div class="control-group form-group">
                                    <label class="control-label col-lg-4">'._t('VERTICAL_MENU_PAGE').'</label>
                                    <div class="controls col-lg-7">
                                        <input class="form-control" type="text" autocomplete="off" name="PageMenu" data-provide="typeahead" data-items="5" data-source=\''.$listWikinames.'\' value="'.(isset($GLOBALS['wiki']->page["metadatas"]['PageMenu']) ? $GLOBALS['wiki']->page["metadatas"]['PageMenu'] : 'PageMenu').'" />
                                    </div>
                                </div>'."\n";

        $selecteur .= '
                    <div class="control-group form-group">
                        <label class="control-label col-lg-4">'._t('RIGHT_COLUMN_PAGE').'</label>
                        <div class="controls col-lg-7">
                            <input class="form-control" type="text" autocomplete="off" name="PageColonneDroite" data-provide="typeahead" data-items="5" data-source=\''.$listWikinames.'\' value="'.(isset($GLOBALS['wiki']->page["metadatas"]['PageColonneDroite']) ? $GLOBALS['wiki']->page["metadatas"]['PageColonneDroite'] : 'PageColonneDroite').'" />
                        </div>
                    </div>'."\n".
                '</fieldset>
                </div>
            </div>
        </div>
    </div> <!-- /#avanced-page-settings -->';
    }

    $selecteur .=   '</form>'."\n";

    $js = add_templates_list_js();
    $GLOBALS['wiki']->addJavascript($js);
    return $selecteur;
}


function add_templates_list_js()
{
    // AJOUT DU JAVASCRIPT QUI PERMET DE CHANGER DYNAMIQUEMENT DE TEMPLATES
    $js = '
    var tab1 = new Array();
    var tab2 = new Array();'."\n";
    foreach (array_keys($GLOBALS['wiki']->config['templates']) as $key => $value) {
        $js .= '        tab1["'.$value.'"] = new Array(';
        $nbocc=0;
        foreach ($GLOBALS['wiki']->config['templates'][$value]["squelette"] as $key2 => $value2) {
            if ($nbocc==0) {
                $js .= '\''.$value2.'\'';
            } else {
                $js .= ',\''.$value2.'\'';
            }
            $nbocc++;
        }
        $js .= ');'."\n";

        $js .= '        tab2["'.$value.'"] = new Array(';
        $nbocc=0;
        foreach ($GLOBALS['wiki']->config['templates'][$value]["style"] as $key3 => $value3) {
            if ($nbocc==0) {
                $js .= '\''.$value3.'\'';
            } else {
                $js .= ',\''.$value3.'\'';
            }
            $nbocc++;
        }
        $js .= ');'."\n";
    }

    return $js;
}

// Callback function for bootstrap navbar menu
function make_dropdown_menu($matches)
{
    $replace = str_replace(
        array('<li>','<a ','<ul>'),
        array('<li class="dropdown">', '<a class="dropdown-toggle" data-toggle="dropdown" ', '<ul class="dropdown-menu">'),
        $matches[1]
    );
    return ;
}

function implode_r($glue, array $arr)
{
    $ret = '';

    foreach ($arr as $piece) {
        if (is_array($piece)) {
            $ret .= $glue . implode_r($glue, $piece);
        } else {
            $ret .= $glue . $piece;
        }
    }

    return $ret;
}


/**
 * vérifie l'extension d'un fichier.
 *
 * Compare l'extension du fichier dont le nom est passé en paramètre à une
 * extension. Retourne vrai si l'extension correspond sinon retourne faux.
 *
 * @param string $filename Nom du fichier dont l'extension est a vérifer
 * @param string $ext      extension attendue
 *
 * @return bool
 */
function isExtension($filename, $ext)
{
    return substr($filename, -strlen($ext), strlen($filename)) === $ext;
}


/**
 * recupere le parametre data sous forme d'un tableau
 *
 *
 * @return array or null if not result
 */
function getDataParameter()
{
    // container data attributes
    $data = $GLOBALS['wiki']->GetParameter('data');
    if (!empty($data)) {
        $datas = array();
        $tab = explode(',', $data);
        foreach ($tab as $req) {
            $tabdecoup = explode('=', $req, 2);
            $key = htmlspecialchars($tabdecoup[0]);
            $datas[$key] = htmlspecialchars(trim($tabdecoup[1]));
        }
        if (is_array($datas)) {
            return $datas;
        } else {
            return null;
        }
    } else {
        return null;
    }
}

function postFormat($output)
{
    // pour les buttondropdown, on ajoute les classes css aux listes
    $pattern = array(
       '/(\<!-- start of buttondropdown -->.*)\<ul\>(.*\<!-- end of buttondropdown --\>)/Uis',
       '/<li>\s*<hr \/>\s*<\/li>/Uis',
    );
    $replacement = array(
        '$1<ul class="dropdown-menu dropdown-menu-right" role="menu">$2',
        '<li class="divider"></li>',
    );
    return preg_replace($pattern, $replacement, $output);
}

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
}

//Récupère les droits de la page désignée en argument et renvoie un tableau
function recup_meta($page) { 
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
    $js = '
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
';
    return $js;
}

function theme_selector($method = '') {

    if (!isset($formclass)) {
        $formclass = 'form-horizontal' ;
    }

    $id = 'select_theme';

    $selecteur = '		<form '.(!empty($method) ? 'method="'.$method.'"' : '' ).'class="' . $formclass . '" id="' . $id . '">' . "\n";

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
        '				<div class="controls col-lg-4">' . "\n" .
        '					<select class="form-control" id="changetheme" name="theme_select">' . "\n";
    foreach (array_keys($GLOBALS['wiki']->config['templates']) as $key => $value) {
        $selected = '';
        if ($GLOBALS['wiki']->config['favorite_theme'] == $value) {
            $selected = ' selected';
        }
        $selecteur .= '						<option value="' . $value . '"'.$selected.'>' . $value . '</option>' . "\n";
    }
    $selecteur .= '					</select>' . "\n" . '				</div>' . "\n" . '			</div>' . "\n";

    $selecteur .=
    '			<div class="control-group form-group">' . "\n" .
    '				<label class="control-label col-lg-4">' . _t('TEMPLATE_SQUELETTE') . '</label>' . "\n" .
        '				<div class="controls col-lg-4">' . "\n" .
        '					<select class="form-control" id="changesquelette" name="squelette_select">' . "\n";
    ksort($GLOBALS['wiki']->config['templates'][$GLOBALS['wiki']->config['favorite_theme']]['squelette']);
    foreach ($GLOBALS['wiki']->config['templates'][$GLOBALS['wiki']->config['favorite_theme']]['squelette'] as $key => $value) {
        $selected = '';
        if ($GLOBALS['wiki']->config['favorite_squelette'] == $value) {
            $selected = ' selected';
        }
        $selecteur .= '						<option value="' . $key . '"'.$selected.'>' . $value . '</option>' . "\n";
    }
    $selecteur .= '					</select>' . "\n" . '				</div>' . "\n" . '			</div>' . "\n";

    ksort($GLOBALS['wiki']->config['templates'][$GLOBALS['wiki']->config['favorite_theme']]['style']);
    $selecteur .=
    '			<div class="control-group form-group">' . "\n" .
    '				<label class="control-label col-lg-4">' . _t('TEMPLATE_STYLE') . '</label>' . "\n" .
        '				<div class="controls col-lg-4">' . "\n" .
        '					<select class="form-control" id="changestyle" name="style_select">' . "\n";
    foreach ($GLOBALS['wiki']->config['templates'][$GLOBALS['wiki']->config['favorite_theme']]['style'] as $key => $value) {
        $selected = '';
        if ($GLOBALS['wiki']->config['favorite_style'] == $value) {
            $selected = ' selected';
        }
        $selecteur .= '						<option value="' . $key . '"'.$selected.'>' . $value . '</option>' . "\n";
    }
    $selecteur .= '					</select>' . "\n" . '				</div>' . "\n" . '				</div>' . "\n";
    
    $js = add_templates_list_js() . "\n" . add_change_theme_js();
    $GLOBALS['wiki']->addJavascript($js);

    return $selecteur;
}

/**
 * Get the first image in the page
 *
 * @param array  $page   Page info
 * @param string $width  Width of the image
 * @param string $height Height of the image
 * 
 * @return string  link to the image
 */
function getImageFromBody($page, $width, $height)
{
    if (!isset($page['body'])) {
        if (isset($GLOBALS['wiki']->config['opengraph_image'])
            and file_exists($GLOBALS['wiki']->config['opengraph_image'])
        ) {
            $image = $GLOBALS['wiki']->getBaseUrl().'/'.$GLOBALS['wiki']->config['opengraph_image'];
        } else {
            $image = '';
        }
        return $image;
    }
    $image = '';
    // on cherche les actions attach avec image, puis les images bazar
    preg_match_all("/\{\{attach.*file=\".*\.(?i)(jpe?g|png).*\}\}/U", $page['body'], $images);
    if (is_array($images[0]) && !empty($images[0][0])) {
        preg_match_all("/.*file=\"(.*\.(?i)(jpe?g|pngif))\".*desc=\"(.*)\".*\}\}/U", $images[0][0], $img);

        $oldpage = $GLOBALS['wiki']->GetPageTag();
        $GLOBALS['wiki']->tag = $page['tag'];
        $GLOBALS['wiki']->page['time'] = $page['time'];
        if (isset($img[1][0])) {
            $GLOBALS['wiki']->setParameter('desc', $img[1][0]);
            $GLOBALS['wiki']->setParameter('file', $img[1][0]);
        }
        $GLOBALS['wiki']->setParameter('width', $width);
        $GLOBALS['wiki']->setParameter('height', $height);
        if (!class_exists('attach')) {
            include 'tools/attach/actions/attach.class.php';
        }
        $attach = new Attach($GLOBALS['wiki']);
        $attach->CheckParams();
        $imagefile = $attach->GetFullFilename();
        $GLOBALS['wiki']->tag = $oldpage;
        $image = $GLOBALS['wiki']->getBaseUrl().'/'.redimensionner_image(
            $imagefile,
            'cache/'.$width.'x'.$height.'-'.str_replace('files/', '', $imagefile),
            $width,
            $height,
            'crop'
        );
    } else {
        preg_match_all('/"imagebf_image":"(.*)"/U', $page['body'], $image);
        if (is_array($image[1]) && !empty($image[1][0])) {
            $imagefile = utf8_decode(
                preg_replace_callback(
                    '/\\\\u([a-f0-9]{4})/',
                    'encodingFromUTF8',
                    $image[1][0]
                )
            );
            $image = $GLOBALS['wiki']->getBaseUrl().'/'.redimensionner_image(
                'files/'.$imagefile,
                'cache/'.$width.'x'.$height.'-'.$imagefile,
                $width,
                $height,
                'crop'
            );
        } else {
            preg_match_all("/\[\[(http.*\.(?i)(jpe?g|png)) .*\]\]/U", $page['body'], $image);
            if (is_array($image[1]) && !empty($image[1][0])) {
                $image = trim(str_replace('\\', '', $image[1][0]));
            } else {
                
                preg_match_all("/<img.*src=\"(.*\.(jpe?g|png))\"/U", $page['body'], $image);
                if (is_array($image[1]) && !empty($image[1][0])) {
                    $image = trim($image[1][0]);
                } elseif (isset($GLOBALS['wiki']->config['opengraph_image'])
                    and file_exists($GLOBALS['wiki']->config['opengraph_image'])
                ) {
                    $image = $GLOBALS['wiki']->getBaseUrl().'/'.$GLOBALS['wiki']->config['opengraph_image'];
                } else {
                    $image = '';
                }
            }
        }
    }

    return $image;
}

/**
 * Get the first title in page
 *
 * @param array $page Informations de la page
 * 
 * @return string The title string
 */
function getTitleFromBody($page)
{
    if (!isset($page['body'])) {
        return _t('TEMPLATES_PAGE_WITHOUT_TITLE');
    }
    $title = '';
    // on recupere les bf_titre ou les titres de niveau 1 et de niveau 2 
    preg_match_all('/"bf_titre":"(.*)"/U', $page['body'], $titles);
    if (is_array($titles[1]) && isset($titles[1][0]) && $titles[1][0] != '') {
        $title = $titles[1][0];
    } else {
        preg_match_all("/\={6}(.*)\={6}/U", $page['body'], $titles);
        if (is_array($titles[1]) && isset($titles[1][0]) && $titles[1][0] != '') {
            $title = $GLOBALS['wiki']->Format(trim($titles[1][0]));
        } else {
            preg_match_all('/={5}(.*)={5}/U', $page['body'], $titles);
            if (is_array($titles[1]) && isset($titles[1][0]) && $titles[1][0] != '') {
                $title = $GLOBALS['wiki']->Format(trim($titles[1][0]));
            }
        }
    }

    return empty($title) ? _t('TEMPLATES_PAGE_WITHOUT_TITLE') : strip_tags($title);
}

/**
 * Get the first title in page
 *
 * @param array $page   Page informations
 * @param int   $length Max number of chars (default 300)
 * 
 * @return string The title string
 */
function getDescriptionFromBody($page, $length = 300)
{
    if (!isset($page['body'])) {
        return '';
    }
    $desc = '';
    // si la page est de type fiche_bazar, alors on affiche la fiche plutot que de formater en wiki
    $type = $GLOBALS['wiki']->GetTripleValue(
        $page['tag'],
        'http://outils-reseaux.org/_vocabulary/type',
        '',
        ''
    );

    if ($type == 'fiche_bazar') {
        $entry = baz_valeurs_fiche($GLOBALS['wiki']->GetPageTag());
        $desc = baz_voir_fiche(0, $entry);
    } else {
        $desc = $GLOBALS['wiki']->Format($page['body']);
    }
    // no javascript
    $desc = preg_replace('~<\s*\bscript\b[^>]*>(.*?)<\s*\/\s*script\s*>~Uis', "", $desc);

    // no double space or new lines
    $desc = trim(
        preg_replace(
            '!\s+!',
            ' ',
            str_replace(
                array("\r", "\n"),
                ' ',
                str_replace(getTitleFromBody($page), '', strip_tags($desc))
            )
        )
    );
    $desc = strtok(wordwrap($desc, $length, "…\n"), "\n");
    return $desc;
}