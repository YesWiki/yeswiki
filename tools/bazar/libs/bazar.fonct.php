<?php

use YesWiki\Bazar\Exception\ParsingMultipleException;
use YesWiki\Bazar\Field\DateField;
use YesWiki\Bazar\Field\EnumField;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Bazar\Service\ListManager;

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
    $lists = $GLOBALS['wiki']->services->get(ListManager::class)->getAll();
    $lists = array_map(function ($list) {
        return $list['title'];
    }, $lists);
    $requete = 'SELECT bn_id_nature, bn_label_nature FROM ' . $GLOBALS['wiki']->config['table_prefix'] . 'nature';
    $result = $GLOBALS['wiki']->LoadAll($requete);
    foreach ($result as $form) {
        $forms[$form['bn_id_nature']] = $form['bn_label_nature'];
    }

    return ['lists' => $lists, 'forms' => $forms];
}

function getHtmlDataAttributes($fiche, $formtab = '')
{
    $htmldata = '';
    if (is_array($fiche) && isset($fiche['id_typeannonce'])) {
        $form = isset($formtab[$fiche['id_typeannonce']]) ? $formtab[$fiche['id_typeannonce']] : $GLOBALS['wiki']->services->get(FormManager::class)->getOne($fiche['id_typeannonce']);
        foreach ($fiche as $key => $value) {
            if (!empty($value)) {
                if (
                    in_array(
                        $key,
                        [
                            'bf_latitude',
                            'bf_longitude',
                            'id_typeannonce',
                            'owner',
                            'date_creation_fiche',
                            'date_debut_validite_fiche',
                            'date_fin_validite_fiche',
                            'id_fiche',
                            'statut_fiche',
                            'date_maj_fiche',
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
                                    $field instanceof EnumField
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
            $form = $GLOBALS['wiki']->services->get(FormManager::class)->getOne($fiche['id_typeannonce']);
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
    } else {
        // sinon, on rappele recursivement la fonction jusqu'a ce que le nom aille bien
        $occurence++;

        return genere_nom_wiki($nom, $occurence);
    }
}

function startsWith($haystack, $needle)
{
    $length = strlen($needle);

    return substr($haystack, 0, $length) === $needle;
}

function endsWith($haystack, $needle)
{
    $length = strlen($needle);
    if ($length == 0) {
        return true;
    }

    return substr($haystack, -$length) === $needle;
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
            } else {
                return isset($parameter[$entry[$field]]) ?
                    $parameter[$entry[$field]] : $default;
            }
        } else {
            // si la valeur n existe pas, on met l icone par defaut
            return $default;
        }
    } else {
        // si le parametre n'est pas un tableau, il contient la valeur par defaut
        return $default;
    }
}

// tri par ordre desire
function champCompare($a, $b)
{
    if ($GLOBALS['ordre'] == 'desc') {
        return strcoll(mb_strtolower($b[$GLOBALS['champ']]), mb_strtolower($a[$GLOBALS['champ']]));
    } else {
        return strcoll(mb_strtolower($a[$GLOBALS['champ']]), mb_strtolower($b[$GLOBALS['champ']]));
    }
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
