<?php
// Vérification de sécurité
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

$type = $this->GetTripleValue($this->GetPageTag(), 'http://outils-reseaux.org/_vocabulary/type', '', '');
$bazaroutput = '';
if ($type == 'fiche_bazar') {
    // si la page est de type fiche_bazar, alors on affiche la fiche plutot que de formater en wiki
    $valjson = $this->page["body"];
    $tab_valeurs = json_decode($valjson, true);
    if (YW_CHARSET != 'UTF-8') {
        $tab_valeurs = array_map('utf8_decode', $tab_valeurs);
    }
    $bazaroutput .= baz_voir_fiche(0, $tab_valeurs);
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
        $results = array();
        foreach ($params['idtypeannonce'] as $formid) {
            $results = array_merge(
                $results,
                baz_requete_recherche_fiches($params['query'], 'alphabetique', $formid, '', 1, '', '', true, $q)
            );
        }
    } else {
        $results = baz_requete_recherche_fiches($params['query'], 'alphabetique', $params['idtypeannonce'], '', 1, '', '', true, $q);
    }

    // affichage à l'écran
    $bazaroutput .=  displayResultList($results, $params, false);
}

// on court-circuite l'appel normal du handler si on a du contenu
if (!empty($bazaroutput)) {
    $output = '';
    // on recupere les entetes html mais pas ce qu'il y a dans le body
    $header = explode('<body', $this->Header());
    $output .= $header[0] . "<body class=\"yeswiki-body\">\n<div class=\"yeswiki-page-widget page-widget page\">\n";

    // affichage de la page formatee
    if (isset($_GET['iframelinks']) && $_GET['iframelinks'] == '0') {
        // pas de modification des urls
        $output .= $bazaroutput;
    } else {
        // pattern qui rajoute le /iframe pour les liens au bon endroit, merci raphael@tela-botanica.org
        $pattern = ',' . preg_quote($this->config['base_url']) . '(\w+)([&#?].*?)?(["<]),';
        $output .= preg_replace($pattern, $this->config['base_url'] . "$1/iframe$2$3", $bazaroutput);
    }
    $output .= "</div><!-- end div.page-widget -->";

    // on efface le style par defaut du fond pour l'iframe
    $styleiframe = '<style>
  html {
    overflow-y: auto;
    background-color : transparent;
    background-image : none;
  }
  .yeswiki-body {
    background-color : transparent;
    background-image : none;
    text-align : left;
    width : auto;
    min-width : 0;
    padding-top : 0;
  }
  .yeswiki-page-widget { padding:0 !important;min-height:auto !important; }
  </style>' . "\n";

    // on recupere juste les javascripts et la fin des balises body et html
    $output .= preg_replace('/^.+<script/Us', $styleiframe . '<script', $this->Footer());
    die($output);
}
