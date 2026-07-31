<?php

// relocated from tools/bazar/{wiki.php,libs/bazar.fonct.php,libs/bazar.fonct.misc.php,
// libs/bazar.fonct.retrocompatibility.php} (ticket 24). Bazar's own wiki.php used to load
// these constants/functions unconditionally as a per-extension bootstrap file (see
// YesWiki::includeExtensionsBootstrapFiles()); since bazar is core now, this file is
// required directly from src/YesWikiRuntime.php alongside Kernel/urlutils.inc.php so the
// same unconditional, always-available guarantee holds without depending on an 'bazar'
// entry in the ExtensionRegistry service.
//
// BAZ_CHEMIN ('tools/bazar/') was dropped here in ticket 24. It turned out not to be dead
// at the time -- the vendored PEAR pagination library still depended on it -- but ticket 02
// deleted that library outright (replaced by YesWiki\Kernel\Service\Paginator), so nothing
// refers to BAZ_CHEMIN any more. BAZ_CHEMIN_UPLOAD below is a different, live constant.
//
// Ticket 02 (wave two) removed 21 functions from this file: 14 unreachable `baz_*`
// @deprecated wrappers, 5 further unreachable @deprecated wrappers (validateForm,
// searchResultstoArray, displayResultList, getAllParameters, getAllParameters_carto),
// and startsWith/endsWith, superseded by PHP 8's str_starts_with/str_ends_with. What
// remains has at least one real caller; the survivors fold into their owning module in
// ticket 05.

use function Symfony\Component\String\u;

use YesWiki\Content\Attach;
use YesWiki\Content\Controller\EntryController;
use YesWiki\Content\Exception\ParsingMultipleException;
use YesWiki\Content\Field\DateField;
use YesWiki\Content\Field\EnumField;
use YesWiki\Content\Field\MapField;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\ListManager;
use YesWiki\Identity\Service\Guard;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Render\Service\TemplateEngine;

define('BAZ_CHEMIN_UPLOAD', 'files/');

// The 24 BAZ_* action/view constants that used to live here are gone (ticket 12): 20 had
// no reader left anywhere, and the four that did -- the `vue` URL parameter and the
// listes/importer/exporter view names -- duplicated BazarAction's own class constants,
// which is where their callers read them from now. BAZ_CHEMIN_UPLOAD above is unrelated
// and still live.

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

function formAndListIds()
{
    $forms = [];
    $lists = $GLOBALS['yeswikiServices']->get(ListManager::class)->getAll();
    $lists = array_map(function ($list) {
        return $list['title'];
    }, $lists);
    foreach ($GLOBALS['yeswikiServices']->get(FormManager::class)->getAll() as $form) {
        $forms[$form['id']] = $form['label'];
    }

    return ['lists' => $lists, 'forms' => $forms];
}

function getHtmlDataAttributes($entry, $formtab = '')
{
    $htmldata = '';
    if (is_array($entry) && isset($entry['form_id'])) {
        $form = isset($formtab[$entry['form_id']]) ? $formtab[$entry['form_id']] : $GLOBALS['yeswikiServices']->get(FormManager::class)->getOne($entry['form_id']);
        foreach ($entry as $key => $value) {
            if (!empty($value)) {
                if (
                    in_array(
                        $key,
                        [
                            // bf_latitude/bf_longitude used to be emitted here as
                            // data-attributes: they are not entry metadata, they were two
                            // fields of one particular French form, nothing reads the
                            // attributes, and geolocation has lived in a map field since
                            // 20260203091701_BazarChangeModelForGeolocation (ticket 11)
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
function show($val, $label = '', $class = 'field', $tag = 'p', $entry = '')
{
    if (is_array($entry)) {
        // on recupere les valeurs plutot que les clés pour les champs checkbox et liste
        if (substr($val, 0, 10) === 'listeListe' or substr($val, 0, 13) === 'checkboxListe') {
            $func = (substr($val, 0, 10) === 'listeListe' ? 'liste' : 'checkbox');
            $dummy = '';
            $form = $GLOBALS['yeswikiServices']->get(FormManager::class)->getOne($entry['form_id']);
            $f = multiArraySearch($form, '1', preg_replace('/^(liste|checkbox)/i', '', $val));
            $f = array_shift($f);
            if (function_exists($func)) {
                $html = $func($dummy, $f, 'html', $entry);
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
                        $found = $field->renderStaticIfPermitted($entry);
                    }
                }
                $val = $found;
            }
        } else {
            $val = isset($entry[$val]) ? $entry[$val] : '';
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

/**
 * Turn a string into a unique WikiName: accents stripped, capped at 50 characters, two
 * capitals. A name already taken is suffixed recursively -- NomWiki2, NomWiki3...
 *
 * @param string $name       the string to derive the name from
 * @param int    $occurrence recursion depth; 1 on the first call, and only >1 when
 *                           retrying with a suffix
 *
 * @return string a WikiName nothing else holds
 */
function generateWikiName($name, $occurrence = 1)
{
    // si la fonction est appelee pour la premiere fois, on nettoie le nom passe en parametre
    if ($occurrence <= 1) {
        // les noms wiki ne doivent pas depasser les 50 caracteres, on coupe a 48
        // histoire de pouvoir ajouter un chiffre derriere si nom wiki deja existant
        // plus traitement des accents et ponctuation
        // plus on met des majuscules au debut de chaque mot et on fait sauter les espaces
        $name = u($name)->ascii();
        $temp = removeAccents(mb_substr(preg_replace('/[[:punct:]]/', ' ', $name), 0, 47, YW_CHARSET));
        $temp = explode(' ', ucwords(strtolower($temp)));
        $name = '';
        foreach ($temp as $mot) {
            // on vire d'eventuels autres caracteres speciaux
            $name .= preg_replace('/[^a-zA-Z0-9]/', '', trim($mot));
        }

        // on verifie qu'il y a au moins 2 majuscules, sinon on en rajoute une a la fin
        $var = preg_replace('/[^A-Z]/', '', $name);
        if (strlen($var) < 2) {
            $last = ucfirst(substr($name, strlen($name) - 1));
            $name = substr($name, 0, -1) . $last;
        }

        $name = '';
        foreach ($temp as $mot) {
            // on vire d'eventuels autres caracteres speciaux
            $name .= preg_replace('/[^a-zA-Z0-9]/', '', trim($mot));
        }

        // on verifie qu'il y a au moins 2 majuscules, sinon on en rajoute une a la fin
        $var = preg_replace('/[^A-Z]/', '', $name);
        if (strlen($var) < 2) {
            $last = ucfirst(substr($name, strlen($name) - 1));
            $name = substr($name, 0, -1) . $last;
        }
    } elseif ($occurrence > 2) {
        // si on en est a plus de 2 occurences, on supprime le chiffre precedent et on ajoute la nouvelle occurence
        $nb = -1 * strlen(strval($occurrence - 1));
        $name = substr($name, 0, $nb) . $occurrence;
    } else {
        // cas ou l'occurence est la deuxieme : on reprend le NomWiki en y ajoutant le chiffre 2
        $name = $name . $occurrence;
    }

    if ($occurrence == 0) {
        // pour occurence = 0 on ne teste pas l'existance de la page
        return $name;
    } elseif (!is_array($GLOBALS['yeswikiServices']->get(YesWiki\Content\Service\PageManager::class)->getOne($name))) {
        // on verifie que la page n'existe pas deja : si c'est le cas on le retourne
        return $name;
    }
    // sinon, on rappele recursivement la fonction jusqu'a ce que le nom aille bien
    $occurrence++;

    return generateWikiName($name, $occurrence);
}

// tri par ordre desire
function compareFieldsByPosition($a, $b)
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
        $tabparam = $GLOBALS['yeswikiServices']->get(EntryManager::class)->getMultipleParameters($param, $firstseparator, $secondseparator);
        $tabparam['fail'] = 0;
    } catch (ParsingMultipleException $th) {
        $tabparam['fail'] = 1;
    }

    return $tabparam;
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

function resizeImage($image_src, $image_dest, $largeur, $hauteur, $method = 'fit')
{
    $services = $GLOBALS['yeswikiServices'];
    if (file_exists($image_src)) {
        $attach = new Attach($services);

        // force new name
        $image_dest = $attach->getResizedFilename($image_src, $largeur, $hauteur, $method);

        if (!$services->get(HibernationService::class)->isWikiHibernated()
            && file_exists($image_dest)
            && isset($_GET['refresh'])
            && $_GET['refresh'] == 1
            && $services->get(YesWiki\Identity\Service\AclService::class)->isAdmin()) {
            unlink($image_dest);
        }
        if (!file_exists($image_dest)) {
            $result = $attach->resizeImage($image_src, $image_dest, $largeur, $hauteur, $method);
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

/**
 * @deprecated Use FormManager::getOne, FormManager::getMany or FormManager::getAll
 */
function formValues($formId = [])
{
    $formManager = $GLOBALS['yeswikiServices']->get(FormManager::class);

    if (is_array($formId) and count($formId) > 0) {
        return $formManager->getMany($formId);
    } elseif ($formId != '' and !is_array($formId)) {
        return $formManager->getOne($formId);
    }

    return $formManager->getAll();
}

/**
 * @deprecated Use ListManager::getOne or ListManager::getAll
 */
function listValues($listId = '')
{
    $listId = trim($listId);
    if ($listId != '') {
        return $GLOBALS['yeswikiServices']->get(ListManager::class)->getOne($listId);
    }

    return $GLOBALS['yeswikiServices']->get(ListManager::class)->getAll();
}

/**
 * @deprecated Use Guard::isAllowed
 */
function userIsAllowedTo($request = 'saisie_fiche', $id = '')
{
    return $GLOBALS['yeswikiServices']->get(Guard::class)->isAllowed($request, $id);
}

/**
 * @deprecated Use EntryController::view
 */
function renderEntryView($inApp, $entryId, $form = '')
{
    try {
        $output = $GLOBALS['yeswikiServices']->get(EntryController::class)->view($entryId, '', $inApp, null, $form);
    } catch (Throwable $t) {
        return $GLOBALS['yeswikiServices']->get(TemplateEngine::class)
            ->render('@core/alert-message.twig', [
                'type' => 'danger',
                'message' => _t('PERFORMABLE_ERROR') . '<br/>' . $GLOBALS['yeswikiServices']->get(YesWiki\Kernel\Service\ThrowableFormatter::class)->dump($t),
            ]);
    }

    return $output;
}

/**
 * Resolves a bazarliste display parameter (color=, icon=, ...) for one entry: when
 * the parameter is a value-map and a field name is given, pick the entry's matched
 * value (first match for comma-separated checkbox values), else the default.
 * Deleted by mistake in the wave-2 dead-code purge while the bazar list templates
 * (liste_liens, material-card, map, tableau, ...) still call it — restored for
 * ticket 07, where the Twig `customValueForEntry` helper delegates here.
 *
 * @param array<mixed>|string|null $parameter
 * @param string|null              $field
 * @param array<string,mixed>      $entry
 */
function getCustomValueForEntry($parameter, $field, $entry, mixed $default): mixed
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
