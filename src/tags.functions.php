<?php

use YesWiki\Core\Attach;

/** afficher_image_attach() - genere une image en cache (gestion taille et vignettes) et l'affiche comme il faut.
 * @param   string  nom du fichier image
 * @param   string  label pour l'image
 * @param   string  classes html supplementaires
 * @param   int    largeur en pixel de la vignette
 * @param   int    hauteur en pixel de la vignette
 * @param   int    largeur en pixel de l'image redimensionnee
 * @param   int    hauteur en pixel de l'image redimensionnee
 *
 * @return html affichage a l'ecran
 */
function afficher_image_attach($idfiche, $nom_image, $label, $class, $largeur_vignette, $hauteur_vignette)
{
    $oldpage = $GLOBALS['wiki']->GetPageTag();
    $GLOBALS['wiki']->tag = $idfiche;
    $GLOBALS['wiki']->page['time'] = date('YmdHis');
    $GLOBALS['wiki']->setParameter('desc', $label);
    $GLOBALS['wiki']->setParameter('file', $nom_image);
    $GLOBALS['wiki']->setParameter('class', $class);
    $GLOBALS['wiki']->setParameter('width', $largeur_vignette);
    $GLOBALS['wiki']->setParameter('height', $hauteur_vignette);
    $attach = new Attach($GLOBALS['wiki']);
    ob_start();
    $attach->doAttach();
    $output = ob_get_contents();
    ob_end_clean();
    $GLOBALS['wiki']->tag = $oldpage;

    $output = preg_replace('/width=\".*\".*height=\".*\"/U', '', $output);
    preg_match_all('/(\<img.*\/\>)/U', $output, $matches);

    return isset($matches[0][0]) ? $matches[0][0] : false;
}

function sanitizeEntity($string)
{
    return preg_replace('~&([a-z]{1,2})(?:acute|cedil|circ|grave|lig|orn|ring|slash|th|tilde|uml|caron);~i', '$1', htmlentities(html_entity_decode($string), ENT_QUOTES, YW_CHARSET));
}

function tokenTruncate($string, $your_desired_width)
{
    $parts = preg_split('/([\s\n\r]+)/', $string, 0, PREG_SPLIT_DELIM_CAPTURE);
    $parts_count = count($parts);

    $length = 0;
    $last_part = 0;
    for (; $last_part < $parts_count; $last_part++) {
        $length += strlen($parts[$last_part]);
        if ($length > $your_desired_width) {
            break;
        }
    }

    return implode(array_slice($parts, 0, $last_part));
}

function get_filtertags_parameters_recursive($nb = 1, $tab = [])
{
    $filter = $GLOBALS['wiki']->GetParameter('filter' . $nb);

    if (empty($filter) && $nb == 1) {
        return '<div class="alert alert-danger"><strong>' . _t('TAGS_ACTION_FILTERTAGS') . '</strong> : ' . _t('TAGS_NO_FILTERS') . '</div>' . "\n";
    } elseif (empty($filter)) {
        return $tab;
    }
    if (!isset($tab['tags'])) {
        $tab['tags'] = '';
    } else {
        $tab['tags'] .= ',';
    }
    $explodelabel = explode(':', $filter);

    // on decoupe le choix pour recuperer le titre
    if (count($explodelabel) > 2) {
        return '<div class="alert alert-danger"><strong>' . _t('TAGS_ACTION_FILTERTAGS') . '</strong> : ' . _t('TAGS_ONLY_ONE_DOUBLEPOINT') . '</div>' . "\n";
    } elseif (count($explodelabel) == 2) {
        $tab[$nb]['title'] = '<strong>' . $explodelabel[0] . ' : </strong>' . "\n";
        $tab[$nb]['arraytags'] = explode(',', $explodelabel[1]);
    } else {
        $tab[$nb]['title'] = '';
        $tab[$nb]['arraytags'] = explode(',', $explodelabel[0]);
    }
    $toggle = $GLOBALS['wiki']->GetParameter('select' . $nb);
    if (!empty($toggle) && $toggle == 'checkbox') {
        $tab[$nb]['toggle'] = $toggle;
    } else {
        $tab[$nb]['toggle'] = 'radio';
    }
    $class = $GLOBALS['wiki']->GetParameter('class' . $nb);
    if (!empty($class)) {
        $tab[$nb]['class'] = $class;
    } else {
        $tab[$nb]['class'] = 'filter-inline';
    }
    $dbService = $GLOBALS['wiki']->services->get(YesWiki\Core\Service\DbService::class);
    $escapedTags = array_map(function ($tagname) use ($dbService) {
        return $dbService->escape($tagname);
    }, $tab[$nb]['arraytags']);
    // single-quoted, not double-quoted: DbService::escape() (PDO::quote()) only
    // guarantees safety inside a single-quoted SQL literal (see
    // AclService::updateRequestWithACL() for the same class of driver-dependent bug)
    $tab['tags'] .= "'" . implode("','", $escapedTags) . "'";
    $nb++;
    $tab = get_filtertags_parameters_recursive($nb, $tab);

    return $tab;
}

function utf8_special_decode($matches)
{
    return iconv('UCS-4LE', 'UTF-8', pack('V', hexdec($matches[1])));
}

function get_title_from_body($page)
{
    // on recupere les bf_titre ou les titres de niveau 1 et de niveau 2, on met la PageWiki sinon
    preg_match_all('/"bf_titre":"(.*)"/U', $page['body'], $titles);
    if (is_array($titles[1]) && isset($titles[1][0]) && $titles[1][0] != '') {
        $title = preg_replace_callback('/\\\\u([a-f0-9]{4})/', 'utf8_special_decode', $titles[1][0]);
    // preg_replace("/\\\\u([a-f0-9]{4})/e", "iconv('UCS-4LE','UTF-8',pack('V', hexdec('U$1')))", $titles[1][0]));
    } else {
        preg_match_all("/\={6}(.*)\={6}/U", $page['body'], $titles);
        if (is_array($titles[1]) && isset($titles[1][0]) && $titles[1][0] != '') {
            $title = $GLOBALS['wiki']->Format(trim($titles[1][0]));
        } else {
            preg_match_all('/={5}(.*)={5}/U', $page['body'], $titles);
            if (is_array($titles[1]) && isset($titles[1][0]) && $titles[1][0] != '') {
                $title = $GLOBALS['wiki']->Format(trim($titles[1][0]));
            } else {
                $title = $page['tag'];
            }
        }
    }

    return strip_tags($title);
}

function encodingFromUTF8($matches)
{
    return iconv('UCS-4LE', 'UTF-8', pack('V', hexdec($matches[1])));
}

// on cherche les actions attach avec image, puis les images bazar -- afficher_image()/
// genere_nom_wiki() below are defined by tools/bazar (bazar.fonct.misc.php/bazar.fonct.php),
// a pre-existing cross-tool dependency this function inherited unchanged, not introduced
// by this relocation
function get_image_from_body($page)
{
    preg_match_all("/\{\{attach.*file=\".*\.(?i)(jpg|png|gif|bmp).*\}\}/U", $page['body'], $images);
    if (is_array($images[0]) && isset($images[0][0]) && $images[0][0] != '') {
        preg_match_all("/.*file=\"(.*\.(?i)(jpg|png|gif|bmp))\".*desc=\"(.*)\".*\}\}/U", $images[0][0], $attachimg);
        $image = afficher_image_attach($page['tag'], $attachimg[1][0], $attachimg[3][0], 'filtered-image', 300, 225);
    } else {
        preg_match_all('/"imagebf_image":"(.*)"/U', $page['body'], $image);
        if (is_array($image[1]) && isset($image[1][0]) && $image[1][0] != '') {
            $imagefile = mb_convert_encoding(
                preg_replace_callback(
                    '/\\\\u([a-f0-9]{4})/',
                    'encodingFromUTF8',
                    $image[1][0]
                ),
                'ISO-8859-1',
                'UTF-8'
            );
            $image = afficher_image('bf_image', 'files/' . $imagefile, 'cache/' . $imagefile, 'filtered-image img-responsive', '', '', 300, 225);
        } else {
            preg_match_all("/\[\[(http.*\.(?i)(jpg|png|gif|bmp)) .*\]\]/U", $page['body'], $image);
            if (is_array($image[1]) && isset($image[1][0]) && $image[1][0] != '') {
                $image = $GLOBALS['wiki']->Format('""<img loading="lazy" alt=\'\' class="img-responsive" src="' . trim(str_replace('\\', '', $image[1][0])) . '" />""');
            } else {
                preg_match_all("/\<img.*src=\"(.*)\"/U", $page['body'], $image);
                if (is_array($image[1]) && isset($image[1][0]) && $image[1][0] != '') {
                    $image = $GLOBALS['wiki']->Format('""<img loading="lazy" alt=\'\' class="img-responsive" src="' . trim($image[1][0]) . '" />""');
                } else {
                    $image = '';
                }
            }
        }
    }

    return $image;
}
