<?php

// label é afficher devant la zone de saisie
$label = $this->GetParameter('label', _t('WHAT_YOU_SEARCH') . '&nbsp;: ');
// largeur de la zone de saisie
$size = $this->GetParameter('size', '40');
// texte du bouton
$button = $this->GetParameter('button', _t('SEARCH'));
// texte é chercher
$phrase = $this->GetParameter('phrase', false);
// séparateur entre les éléments trouvés
$separator = $this->GetParameter('separator', false);

// se souvenir si c'était :
// -- un paramétre de l'action : {{textsearch phrase="Test"}}
// -- ou du CGI http://example.org/wakka.php?wiki=RechercheTexte&phrase=Test
//
// récupérer le paramétre de l'action
$paramPhrase = htmlspecialchars($phrase, ENT_COMPAT, YW_CHARSET);
// ou, le cas échéant, récupérer le paramétre du CGI
if (!$phrase && isset($_GET['phrase'])) {
    $phrase = htmlspecialchars($_GET['phrase'], ENT_COMPAT, YW_CHARSET);
}

// s'il y a un paramétre d'action "phrase", on affiche uniquement le résultat
// dans le cas contraire, présenter une zone de saisie
if (!$paramPhrase) {
    echo $this->FormOpen('', '', 'get');
    echo '<div class="input-prepend input-append input-group input-group-lg">
			<span class="add-on input-group-addon"><i class="fa fa-search icon-search"></i></span>
      <input name="phrase" type="text" class="form-control" placeholder="' . (($label) ? $label : '') . '" size="', $size, '" value="', $phrase, '" >
      <span class="input-group-btn">
        <input type="submit" class="btn btn-primary btn-lg" value="', $button, '" />
      </span>
    </div><!-- /input-group --><br>';
    echo "\n", $this->FormClose();
}

if ($phrase) {
    $results = $this->FullTextSearch($phrase);
    $aclService = $this->services->get(\YesWiki\Core\Service\AclService::class);
    $results = array_filter($results, function ($page) use ($aclService) {
        return $aclService->hasAccess('read', $page['tag']);
    });
    if ($results) {
        if ($separator) {
            $separator = htmlspecialchars($separator, ENT_COMPAT, YW_CHARSET);
            if (!$paramPhrase) {
                echo '<p>' . _t('SEARCH_RESULT_OF') . ' "', htmlspecialchars($phrase, ENT_COMPAT, YW_CHARSET), '"&nbsp;: ';
            }
            $first = true;
            foreach ($results as $i => $page) {
                if ($first) {
                    $first = false;
                } else {
                    echo $separator;
                }
                echo $this->ComposeLinkToPage($page['tag']);
            }
            if (!$paramPhrase) {
                echo '</p>', "\n";
            }
        } else {
            echo '<p><strong>' . _t('SEARCH_RESULT_OF') . ' "', htmlspecialchars($phrase, ENT_COMPAT, YW_CHARSET), '"&nbsp;:</strong></p>', "\n",
            '<ol>', "\n";
            foreach ($results as $i => $page) {
                echo '<li>', $this->ComposeLinkToPage($page['tag']), "</li>\n";
            }
            echo "</ol>\n";
        }
    } else {
        if (!$paramPhrase) {
            echo '<div class="alert alert-info">' . _t('NO_RESULT_FOR') . ' "', htmlspecialchars($phrase, ENT_COMPAT, YW_CHARSET), "\". :-(</div>\n";
        }
    }
}
