<?php
/**
* bazarlistecategorie : programme affichant les fiches du bazar catégorisées par les champs liste
* sous forme de liste accordeon (ou autre template).
*/

use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Core\Service\TemplateEngine;

if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}

$entryManager = $this->services->get(EntryManager::class);

$this->AddJavascriptFile('tools/bazar/presentation/javascripts/bazar.js');

// initialisation de la fonction de tri , inspiré par http://php.net/manual/fr/function.usort.php
if (!function_exists('champCompare')) {
    // tri par ordre desire
    function champCompare($a, $b)
    {
        if ($GLOBALS['ordre'] == 'desc') {
            return strnatcasecmp($b[$GLOBALS['champ']], $a[$GLOBALS['champ']]);
        } else {
            return strnatcasecmp($a[$GLOBALS['champ']], $b[$GLOBALS['champ']]);
        }
    }
}

$id_typeannonce = $this->GetParameter('idtypeannonce');
if (empty($id_typeannonce)) {
    $id_typeannonce = 'toutes';
}
$GLOBALS['ordre'] = $this->GetParameter('ordre');
if (empty($GLOBALS['ordre'])) {
    $GLOBAL['ordre'] = 'asc';
}

$template = $this->GetParameter('template');
$template = $this->services->get(TemplateEngine::class)->hasTemplate("@bazar/$template") ? $template : '';
if (empty($template)) {
    $template = $GLOBALS['wiki']->config['default_bazar_template'];
}

// identifiant de la base de donnée pour la liste
$id = $this->GetParameter('id');
if (empty($id)) {
    throw new Exception('Error action bazarlistecategorie: parameter "id" missing.');
} else {
    $GLOBALS['champ'] = $id;
}

// NomWiki de la liste
$list = $this->GetParameter('list');
if (empty($list)) {
    echo '<div class="alert alert-danger">Error action bazarlistecategorie: parameter "list" missing.</div>';
} else {
    // on recupere les parameres pour une requete specifique
    if (isset($_GET['query'])) {
        $query = $_GET['query'];
    } else {
        $query = '';
    }
    unset($_GET['query']);
    if (!empty($query)) {
        $tabquery = [];
        $tableau = [];
        $tab = explode('|', $query); //découpe la requete autour des |
        foreach ($tab as $req) {
            $tabdecoup = explode('=', $req, 2);
            $tableau[$tabdecoup[0]] = trim($tabdecoup[1]);
        }
        $tabquery = array_merge($tabquery, $tableau);
    } else {
        $tabquery = '';
    }
    $tabfiches = $entryManager->search(['queries' => $tabquery, 'formsIds' => [$id_typeannonce]]);

    $fiches['info_res'] = '';
    $fiches['pager_links'] = '';
    $fiches['fiches'] = [];
    foreach ($tabfiches as $fiche) {
        // pour les checkbox, on crée une fiche par case cochée pour apparaitre é différents endroits
        $tabcheckbox = explode(',', $fiche[$id]);
        foreach ($tabcheckbox as $value) {
            // on sauve les multiples valeurs pour les retablir é l'affichage
            $multiplecheckbox[$fiche['id_fiche']] = $fiche[$id];
            $fiche[$id] = $value;

            // permet de voir la fiche
            $fiche['html'] = baz_voir_fiche(0, $fiche);
            // lien de suppression visible pour le super admin
            if (baz_a_le_droit('supp_fiche', $fiche['owner'])) {
                $fiche['lien_suppression'] = '<a class="modalbox" href="'
                    . $this->href('deletepage', $fiche['id_fiche'], 'incoming=' . urlencode($this->getAbsolutePath())) . '"></a>' . "\n";
            }
            if (baz_a_le_droit('modif_fiche', $fiche['owner'])) {
                $fiche['lien_edition'] = '<a class="BAZ_lien_modifier" href="' . $this->href('edit', $fiche['id_fiche']) . '"></a>' . "\n";
            }
            $fiche['lien_voir_titre'] = '<a class="BAZ_lien_modifier" href="' . $this->href('', $fiche['id_fiche']) . '">' . $fiche['bf_titre'] . '</a>' . "\n";
            $fiche['lien_voir'] = '<a class="BAZ_lien_modifier" href="' . $this->href('', $fiche['id_fiche']) . '"></a>' . "\n";
            $fiches['fiches'][] = $fiche;
        }
    }
    // trie par liste choisie
    usort($fiches['fiches'], 'champCompare');

    $listvalues = baz_valeurs_liste($list);
    $currentlabel = 'this is an impossible label';
    $fichescat = [];
    $output = '';
    $first = true;
    foreach ($fiches['fiches'] as $fiche) {
        $fiche['multipleid'] = htmlspecialchars(trim(str_replace('/', '', $fiche[$id])) . $fiche['id_fiche']);
        if ($currentlabel !== $fiche[$id]) {
            if (!$first) {
                if (is_array($fichescat) && count($fichescat) > 0) {
                    $output .= $this->render("@bazar/$template", $fichescat);
                }
                // it's not the first time in the loop so we must close previously opened div
                $output .= '</div>' . "\n";
                $fichescat = [];
            } else {
                $first = false;
            }
            $output .= '<h3 class="collapsed yeswiki-list-category" '
                . 'data-target="#collapse_' . htmlspecialchars(trim(str_replace('/', '', $fiche[$id])))
                . '" data-toggle="collapse"><i class="fa fa-chevron-right"></i> '
                . (empty($listvalues['label'][$fiche[$id]]) ? _t('BAZ_NOT_CATEGORIZED') : $listvalues['label'][$fiche[$id]]) . '</h3>
                <div id="collapse_' . htmlspecialchars(trim(str_replace('/', '', $fiche[$id]))) . '" class="collapse">';
        }
        $currentlabel = $fiche[$id];
        // on rétablit les valeurs multiples
        if (isset($multiplecheckbox[$fiche['id_fiche']])) {
            $fiche[$id] = $multiplecheckbox[$fiche['id_fiche']];
        }
        $fichescat['fiches'][] = $fiche;
    }
    // last results
    if (is_array($fichescat) && count($fichescat) > 0) {
        $output .= $this->render("@bazar/$template", $fichescat);
    }
    // it's not the first time in the loop so we must close previously opened div
    $output .= '</div>' . "\n";
    echo $output;

    $_GET['query'] = $query;
}
