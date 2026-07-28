<?php

// relocated from tools/bazar/{wiki.php,libs/bazar.fonct.php,libs/bazar.fonct.misc.php,
// libs/bazar.fonct.retrocompatibility.php} (ticket 24). Bazar's own wiki.php used to load
// these constants/functions unconditionally as a per-extension bootstrap file (see
// YesWiki::includeExtensionsBootstrapFiles()); since bazar is core now, this file is
// required directly from src/YesWiki.php alongside urlutils.inc.php/email.inc.php so the
// same unconditional, always-available guarantee holds without depending on an 'bazar'
// entry in $wiki->extensions.
//
// BAZ_CHEMIN ('tools/bazar/') was dropped here in ticket 24. It turned out not to be dead
// at the time -- the vendored PEAR pagination library still depended on it -- but ticket 02
// deleted that library outright (replaced by YesWiki\Core\Service\Paginator), so nothing
// refers to BAZ_CHEMIN any more. BAZ_CHEMIN_UPLOAD below is a different, live constant.
//
// Ticket 02 (wave two) removed 21 functions from this file: 14 unreachable `baz_*`
// @deprecated wrappers, 5 further unreachable @deprecated wrappers (validateForm,
// searchResultstoArray, displayResultList, getAllParameters, getAllParameters_carto),
// and startsWith/endsWith, superseded by PHP 8's str_starts_with/str_ends_with. What
// remains has at least one real caller; the survivors fold into their owning module in
// ticket 05.

use function Symfony\Component\String\u;

use YesWiki\Core\Attach;
use YesWiki\Content\Controller\EntryController;
use YesWiki\Content\Exception\ParsingMultipleException;
use YesWiki\Content\Field\DateField;
use YesWiki\Content\Field\EnumField;
use YesWiki\Content\Field\MapField;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Identity\Service\Guard;
use YesWiki\Core\Service\HibernationService;
use YesWiki\Content\Service\ListManager;
use YesWiki\Render\Service\TemplateEngine;

define('BAZ_CHEMIN_UPLOAD', 'files/');

// +------------------------------------------------------------------------------------------------------+
// |                             LES CONSTANTES DES ACTIONS DE BAZAR                                      |
// +------------------------------------------------------------------------------------------------------+

// Constante des noms des variables
define('BAZ_VARIABLE_VOIR', 'vue');
define('BAZ_VARIABLE_ACTION', 'action');

// Premier niveau d'action : pour toutes les fiches
define('BAZ_VOIR_DEFAUT', 'formulaire');
// Recherche
define('BAZ_VOIR_CONSULTER', 'consulter');
// Recherche
define('BAZ_VOIR_MES_FICHES', 'mes_fiches');
define('BAZ_VOIR_S_ABONNER', 'rss');
define('BAZ_VOIR_SAISIR', 'saisir');
define('BAZ_VOIR_FORMULAIRE', 'formulaire');
define('BAZ_VOIR_LISTES', 'listes');
define('BAZ_VOIR_IMPORTER', 'importer');
define('BAZ_VOIR_EXPORTER', 'exporter');

// Second : actions du choix de premier niveau.

define('BAZ_MOTEUR_RECHERCHE', 'recherche');
define('BAZ_CHOISIR_TYPE_FICHE', 'choisir_type_fiche');
//
// Modifier le formulaire de creation des fiches
define('BAZ_VOIR_FICHE', 'voir_fiche');
define('BAZ_ACTION_NOUVEAU', 'saisir_fiche');
define('BAZ_ACTION_NOUVEAU_V', 'sauver_fiche');
// Creation apres validation
define('BAZ_ACTION_MODIFIER', 'modif_fiche');
define('BAZ_ACTION_MODIFIER_V', 'modif_sauver_fiche');
// Modification apres validation
define('BAZ_ACTION_NOUVELLE_LISTE', 'saisir_liste');
// Creation apres validation
define('BAZ_ACTION_MODIFIER_LISTE', 'modif_liste');
// Modification apres validation
define('BAZ_ACTION_SUPPRIMER_LISTE', 'supprimer_liste');
define('BAZ_ACTION_SUPPRESSION', 'supprimer');
define('BAZ_ACTION_PUBLIER', 'publier');
// Valider la fiche
define('BAZ_ACTION_PAS_PUBLIER', 'pas_publier');
// Invalider la fiche

function multiArraySearch($array, $key, $value)
{
    $results = [];

    if (is_array($array)) {
        if (isset($array[$key]) && $array[$key] == $value) {
            $results[] = $array;
        }

        foreach ($array as $subarray) {
            $results = array_merge($results, multiArraySearch($subarray, $key, $value));
        }
    }

    return $results;
}

function baz_forms_and_lists_ids()
{
    $forms = [];
    $lists = $GLOBALS['wiki']->services->get(ListManager::class)->getAll();
    $lists = array_map(function ($list) {
        return $list['title'];
    }, $lists);
    foreach ($GLOBALS['wiki']->services->get(FormManager::class)->getAll() as $form) {
        $forms[$form['id']] = $form['label'];
    }

    return ['lists' => $lists, 'forms' => $forms];
}

function getHtmlDataAttributes($fiche, $formtab = '')
{
    $htmldata = '';
    if (is_array($fiche) && isset($fiche['form_id'])) {
        $form = isset($formtab[$fiche['form_id']]) ? $formtab[$fiche['form_id']] : $GLOBALS['wiki']->services->get(FormManager::class)->getOne($fiche['form_id']);
        foreach ($fiche as $key => $value) {
            if (!empty($value)) {
                if (
                    in_array(
                        $key,
                        [
                            'bf_latitude',
                            'bf_longitude',
                            'form_id',
                            'owner',
                            'created_at',
                            'date_debut_validite_fiche',
                            'date_fin_validite_fiche',
                            'tag',
                            'status',
                            'updated_at',
                        ]
                    )
                ) {
                    $htmldata .=
                        'data-' . htmlspecialchars($key) . '="' .
                        htmlspecialchars($value) . '" ';
                } else {
                    if (isset($form['prepared'])) {
                        foreach ($form['prepared'] as $field) {
                            $propertyName = $field->getPropertyName();
                            if ($propertyName === $key) {
                                if (
                                    $field instanceof MapField
                                    || $field instanceof EnumField
                                    || $field instanceof DateField
                                    || $field->getName() == 'scope'
                                ) {
                                    $htmldata .=
                                        'data-' . htmlspecialchars($key) . '="' .
                                        htmlspecialchars(is_array($value) ? '[' . implode(',', $value) . ']' : $value) . '" ';
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    return $htmldata;
}

/**  show() - Formatte un paragraphe champs d'une fiche seulement si la valeur est renseignée.
 * @global string champ de la fiche (au format html)
 * @global string Label du champs (facultatif)
 * @global string classe CSS du paragraphe (facultatif "field" par défaut)
 * @global string balise HTML du paragraphe (facultatif "field" par défaut)
 *
 * @return string HTML
 */
function show($val, $label = '', $class = 'field', $tag = 'p', $fiche = '')
{
    if (is_array($fiche)) {
        // on recupere les valeurs plutot que les clés pour les champs checkbox et liste
        if (substr($val, 0, 10) === 'listeListe' or substr($val, 0, 13) === 'checkboxListe') {
            $func = (substr($val, 0, 10) === 'listeListe' ? 'liste' : 'checkbox');
            $dummy = '';
            $form = $GLOBALS['wiki']->services->get(FormManager::class)->getOne($fiche['form_id']);
            $f = multiArraySearch($form, '1', preg_replace('/^(liste|checkbox)/i', '', $val));
            $f = array_shift($f);
            if (function_exists($func)) {
                $html = $func($dummy, $f, 'html', $fiche);
                preg_match_all(
                    '/<span class="BAZ_texte">\s*(.*)\s*<\/span>/is',
                    $html,
                    $matches
                );
                if (isset($matches[1][0]) && $matches[1][0] != '') {
                    $val = $matches[1][0];
                } else {
                    $val = '';
                }
            } else {
                $found = '';
                foreach ($form['prepared'] as $field) {
                    if ($field->getPropertyName() == $val) {
                        $found = $field->renderStaticIfPermitted($fiche);
                    }
                }
                $val = $found;
            }
        } else {
            $val = isset($fiche[$val]) ? $fiche[$val] : '';
        }
    }
    if (!empty($val)) {
        echo '<' . $tag;
        if (!empty($class)) {
            echo ' class="' . $class . '"';
        }
        echo '>' . "\n";
        if (!empty($label)) {
            echo '<strong>' . $label . '</strong> ' . "\n";
        }
        echo $val . '</' . $tag . '>' . "\n";
    }
}

/** removeAccents() Renvoie une chaine de caracteres avec les accents en moins.
 *   @param  string  chaine de caracteres avec de potentiels accents a enlever
 *
 *   return  string chaine de caracteres, sans accents
 */
function removeAccents($str, $charset = YW_CHARSET)
{
    $str = htmlentities($str, ENT_NOQUOTES, $charset);
    $str = preg_replace('#&([A-za-z])(?:acute|cedil|caron|circ|grave|orn|ring|slash|th|tilde|uml);#', '\1', $str);
    $str = preg_replace('#&([A-za-z]{2})(?:lig);#', '\1', $str); // pour les ligatures e.g. '&oelig;'
    $str = preg_replace('#&[^;]+;#', '', $str); // supprime les autres caractères

    return $str;
}

/** genere_nom_wiki()
 *  Prends une chaine de caracteres, et la tranforme en NomWiki unique, en la limitant
 *  a 50 caracteres et en mettant 2 majuscules
 *  Si le NomWiki existe deja, on propose recursivement NomWiki2, NomWiki3, etc..
 *
 *   @param  string  chaine de caracteres avec de potentiels accents a enlever
 *   @param int nombre d'iteration pour la fonction recursive (1 par defaut)
 *
 *   return  string chaine de caracteres, en NomWiki unique
 */
function genere_nom_wiki($nom, $occurence = 1)
{
    // si la fonction est appelee pour la premiere fois, on nettoie le nom passe en parametre
    if ($occurence <= 1) {
        // les noms wiki ne doivent pas depasser les 50 caracteres, on coupe a 48
        // histoire de pouvoir ajouter un chiffre derriere si nom wiki deja existant
        // plus traitement des accents et ponctuation
        // plus on met des majuscules au debut de chaque mot et on fait sauter les espaces
        $nom = u($nom)->ascii();
        $temp = removeAccents(mb_substr(preg_replace('/[[:punct:]]/', ' ', $nom), 0, 47, YW_CHARSET));
        $temp = explode(' ', ucwords(strtolower($temp)));
        $nom = '';
        foreach ($temp as $mot) {
            // on vire d'eventuels autres caracteres speciaux
            $nom .= preg_replace('/[^a-zA-Z0-9]/', '', trim($mot));
        }

        // on verifie qu'il y a au moins 2 majuscules, sinon on en rajoute une a la fin
        $var = preg_replace('/[^A-Z]/', '', $nom);
        if (strlen($var) < 2) {
            $last = ucfirst(substr($nom, strlen($nom) - 1));
            $nom = substr($nom, 0, -1) . $last;
        }

        $nom = '';
        foreach ($temp as $mot) {
            // on vire d'eventuels autres caracteres speciaux
            $nom .= preg_replace('/[^a-zA-Z0-9]/', '', trim($mot));
        }

        // on verifie qu'il y a au moins 2 majuscules, sinon on en rajoute une a la fin
        $var = preg_replace('/[^A-Z]/', '', $nom);
        if (strlen($var) < 2) {
            $last = ucfirst(substr($nom, strlen($nom) - 1));
            $nom = substr($nom, 0, -1) . $last;
        }
    } elseif ($occurence > 2) {
        // si on en est a plus de 2 occurences, on supprime le chiffre precedent et on ajoute la nouvelle occurence
        $nb = -1 * strlen(strval($occurence - 1));
        $nom = substr($nom, 0, $nb) . $occurence;
    } else {
        // cas ou l'occurence est la deuxieme : on reprend le NomWiki en y ajoutant le chiffre 2
        $nom = $nom . $occurence;
    }

    if ($occurence == 0) {
        // pour occurence = 0 on ne teste pas l'existance de la page
        return $nom;
    } elseif (!is_array($GLOBALS['wiki']->LoadPage($nom))) {
        // on verifie que la page n'existe pas deja : si c'est le cas on le retourne
        return $nom;
    }
    // sinon, on rappele recursivement la fonction jusqu'a ce que le nom aille bien
    $occurence++;

    return genere_nom_wiki($nom, $occurence);
}

// pour verifier la presence d une valeur dans une fiche, en vue de lui faire une icone ou couleur personnalisee
function getCustomValueForEntry($parameter, $field, $entry, $default)
{
    if (is_array($parameter) && !empty($field)) {
        if (isset($entry[$field])) {
            // pour les checkbox, on teste les differentes valeurs et on renvoie la premiere qui va bien
            if (!isset($parameter[$entry[$field]]) && strpos($entry[$field], ',') !== false) {
                $tab = explode(',', $entry[$field]);
                foreach ($tab as $value) {
                    if (isset($parameter[$value])) {
                        // on retourne la premiere valeur trouvee
                        return $parameter[$value];
                    }
                }

                // on n a pas trouve de valeur, on renvoie la valeur par defaut
                return $default;
            }

            return isset($parameter[$entry[$field]]) ?
                $parameter[$entry[$field]] : $default;
        }

        // si la valeur n existe pas, on met l icone par defaut
        return $default;
    }

    // si le parametre n'est pas un tableau, il contient la valeur par defaut
    return $default;
}

// tri par ordre desire
function champCompare($a, $b)
{
    if ($GLOBALS['ordre'] == 'desc') {
        return strcoll(mb_strtolower($b[$GLOBALS['champ']]), mb_strtolower($a[$GLOBALS['champ']]));
    }

    return strcoll(mb_strtolower($a[$GLOBALS['champ']]), mb_strtolower($b[$GLOBALS['champ']]));
}

/**
 * @deprecated use EntryManager::getMultipleParameters instead
 */
function getMultipleParameters($param, $firstseparator = ',', $secondseparator = '=')
{
    try {
        $tabparam = $GLOBALS['wiki']->services->get(EntryManager::class)->getMultipleParameters($param, $firstseparator, $secondseparator);
        $tabparam['fail'] = 0;
    } catch (ParsingMultipleException $th) {
        $tabparam['fail'] = 1;
    }

    return $tabparam;
}

function getConfigValue($key, $default = false, $cfg = '')
{
    if (isset($cfg[$key]) and !empty($cfg[$key])) {
        return $cfg[$key];
    }

    return $default;
}

function sanitizeFilename($string = '')
{
    // our list of "dangerous characters", add/remove characters if necessary
    $dangerous_characters = [' ', '"', "'", '&', '/', '\\', '?', '#', '(', ')', '+'];
    // every forbidden character is replace by an underscore
    $string = str_replace($dangerous_characters, '-', removeAccents($string));

    // Only allow one dash separator at a time (and make string lowercase)
    return mb_strtolower(preg_replace('/--+/u', '-', $string), YW_CHARSET);
}

function redimensionner_image($image_src, $image_dest, $largeur, $hauteur, $method = 'fit')
{
    $wiki = $GLOBALS['wiki'];
    if (file_exists($image_src)) {
        $attach = new Attach($wiki);

        // force new name
        $image_dest = $attach->getResizedFilename($image_src, $largeur, $hauteur, $method);

        if (!$wiki->services->get(HibernationService::class)->isWikiHibernated()
            && file_exists($image_dest)
            && isset($_GET['refresh'])
            && $_GET['refresh'] == 1
            && $wiki->UserIsAdmin()) {
            unlink($image_dest);
        }
        if (!file_exists($image_dest)) {
            $result = $attach->redimensionner_image($image_src, $image_dest, $largeur, $hauteur, $method);
            if ($result != $image_dest) {
                // do nothing : error
                return $image_src;
            }

            return $image_dest;
        }

        return $image_dest;
    }
}

function renameUrlToSanitizedFilename($url)
{
    $str = preg_replace('/[\r\n\t ]+/', ' ', basename($url));
    $str = preg_replace('/[\"\*\/\:\<\>\?\'\|]+/', ' ', $str);
    $str = str_replace(' ', '-', $str);

    return preg_replace('/-+/', '-', $str);
}

function copyUrlToLocalFile($url, $localPath)
{
    if (file_exists($localPath)) {
        return true;
    } elseif ($ch = curl_init($url)) { // teste l'existance du fichier a distance
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $imgcontent = curl_exec($ch);
        $error = curl_error($ch);
        if (PHP_VERSION_ID < 80500) {
            curl_close($ch);
        }
        $file = fopen($localPath, 'w+');
        fputs($file, $imgcontent);
        fclose($file);
        if ($error) {
            echo $error;

            return false;
        }

        return true;
    }
    echo _t('BAZ_IMAGE_FILE_NOT_FOUND') . ' : ' . $url;

    return false;
}

/* ~~~~~~~~~~~~ DEPRECATED ~~~~~~~~~~~~~~ */

/** afficher_image() - genere une image en cache (gestion taille et vignettes) et l'affiche comme il faut.
 *
 * @param    string champ de la base
 * @param    string nom du fichier image
 * @param    string  label pour l'image
 * @param    string classes html supplementaires
 * @param    int        largeur en pixel de la vignette
 * @param    int        hauteur en pixel de la vignette
 * @param    int        largeur en pixel de l'image redimensionnee
 * @param    int        hauteur en pixel de l'image redimensionnee
 *
 * @return void
 *
 * @deprecated use $wiki->render('@core/display-image.twig') instead
 */
function afficher_image(
    $champ,
    $nom_image,
    $label,
    $class,
    $largeur_vignette,
    $hauteur_vignette,
    $largeur_image,
    $hauteur_image,
    $method = 'fit',
    $show_vignette = true
) {
    // l'image initiale existe t'elle et est bien avec une extension jpg ou png et bien formatee
    $destimg = sanitizeFilename($nom_image);
    $wiki = $GLOBALS['wiki'];
    $authorizedExts = $wiki->config['authorized-extensions'];
    $url_base = $wiki->GetBaseUrl() . '/';
    // If we have a full URL, remove the base URL first
    $nom_image = str_replace($url_base . BAZ_CHEMIN_UPLOAD, '', $nom_image);
    $ext = pathinfo($nom_image)['extension'];

    $attach = new Attach($wiki);
    $imagePath = $attach->GetUploadPath() . '/' . $nom_image;
    $attach->file = $imagePath;

    if (file_exists($imagePath)
       && $attach->isPicture()) {
        return $wiki->render('@core/display-image.twig', [
            'baseUrl' => $url_base,
            'imageFullPath' => $imagePath,
            'fieldName' => $champ,
            'thumbnailHeight' => $hauteur_vignette,
            'thumbnailWidth' => $largeur_vignette,
            'imageHeight' => $hauteur_image,
            'imageWidth' => $largeur_image,
            'class' => $class,
            'mode' => $method,
            'showThumbnail' => $show_vignette,
        ]);
    }
}

/**
 * @deprecated Use FormManager::getOne, FormManager::getMany or FormManager::getAll
 */
function baz_valeurs_formulaire($idformulaire = [])
{
    $formManager = $GLOBALS['wiki']->services->get(FormManager::class);

    if (is_array($idformulaire) and count($idformulaire) > 0) {
        return $formManager->getMany($idformulaire);
    } elseif ($idformulaire != '' and !is_array($idformulaire)) {
        return $formManager->getOne($idformulaire);
    }

    return $formManager->getAll();
}

/**
 * @deprecated Use ListManager::getOne or ListManager::getAll
 */
function baz_valeurs_liste($idliste = '')
{
    $idliste = trim($idliste);
    if ($idliste != '') {
        return $GLOBALS['wiki']->services->get(ListManager::class)->getOne($idliste);
    }

    return $GLOBALS['wiki']->services->get(ListManager::class)->getAll();
}

/**
 * @deprecated Use Guard::isAllowed
 */
function baz_a_le_droit($demande = 'saisie_fiche', $id = '')
{
    return $GLOBALS['wiki']->services->get(Guard::class)->isAllowed($demande, $id);
}

/**
 * @deprecated Use EntryController::view
 */
function baz_voir_fiche($danslappli, $idfiche, $form = '')
{
    try {
        $output = $GLOBALS['wiki']->services->get(EntryController::class)->view($idfiche, '', $danslappli, null, $form);
    } catch (Throwable $t) {
        return $GLOBALS['wiki']->services->get(TemplateEngine::class)
            ->render('@core/alert-message.twig', [
                'type' => 'danger',
                'message' => _t('PERFORMABLE_ERROR') . '<br/>' . $GLOBALS['wiki']->dumpThrowable($t),
            ]);
    }

    return $output;
}

