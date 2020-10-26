<?php
// Vérification de sécurité
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

$ficheManager = $this->services->get('bazar.fiche.manager');

$bazaroutput = '';
if ($ficheManager->isFiche($this->GetPageTag())) {
    // si la page est une fiche bazar, alors on affiche la fiche plutot que de formater en wiki
    $this->AddJavascriptFile('tools/bazar/libs/bazar.js');
    $valjson = $this->page["body"];
    $tab_valeurs = json_decode($valjson, true);
    if (YW_CHARSET != 'UTF-8') {
        $tab_valeurs = array_map('utf8_decode', $tab_valeurs);
    }
    $bazaroutput .= baz_voir_fiche(true, $tab_valeurs);
} elseif (isset($_GET['id'])) {
    // si le parametre id est passé, on souhaite afficher une liste bazar
    // TODO : factoriser avec bazarliste?
    // on compte le nombre de fois que l'action bazarliste est appelée afin de différencier les instances
    if (!isset($GLOBALS['_BAZAR_']['nbbazarliste'])) {
        $GLOBALS['_BAZAR_']['nbbazarliste'] = 0;
    }
    ++$GLOBALS['_BAZAR_']['nbbazarliste'];
    // Recuperation de tous les parametres
    $params = getAllParameters($this);

    // chaine de recherche
    $q = '';
    if (isset($_GET['q']) and !empty($_GET['q'])) {
        $q = $_GET['q'];
    }

    // tableau des fiches correspondantes aux critères
    if (is_array($params['idtypeannonce'])) {
        // TODO see if we could use multiple form IDs, as is allowed by search function
        $results = array();
        foreach ($params['idtypeannonce'] as $formId) {
            $results = array_merge(
                $results,
                $ficheManager->search(['queries' => $params['query'], 'formsIds' => [$formId], 'keywords' => $q])
            );
        }
    } else {
        $results = $ficheManager->search(['queries' => $params['query'], 'formsIds' => [$params['idtypeannonce']], 'keywords' => $q]);
    }

    // affichage à l'écran
    $bazaroutput .=  displayResultList($results, $params, false);
}

// on court-circuite l'appel normal du handler si on a du contenu
if (!empty($bazaroutput)) {
    $output = '';
    // on recupere les entetes html mais pas ce qu'il y a dans le body
    $header = explode('<body', $this->Header());
    $output .= $header[0] . "<body class=\"yeswiki-iframe-body\">\n<div class=\"container\">\n<div class=\"yeswiki-page-widget page-widget page\">\n";

    // affichage de la page formatee
    if (isset($_GET['iframelinks']) && $_GET['iframelinks'] == '0') {
        // pas de modification des urls
        $output .= $bazaroutput;
    } else {
        // pattern qui rajoute le /iframe pour les liens au bon endroit, merci raphael@tela-botanica.org
        $bazaroutput = str_replace($this->href('edit'), $this->href('editiframe'), $bazaroutput);
        $pattern = ',' . preg_quote($this->config['base_url']) . '(\w+)([&#?].*?)?(["<]),';
        $output .= preg_replace($pattern, $this->config['base_url'] . "$1/iframe$2$3", $bazaroutput);
    }
    $output .= "</div></div><!-- end div container & page-widget -->";

    $this->AddJavascriptFile('tools/templates/libs/vendor/iframeResizer.contentWindow.min.js');
    // on recupere juste les javascripts et la fin des balises body et html
    $output .= preg_replace('/^.+<script/Us', '<script', $this->Footer());
    die($output);
}
