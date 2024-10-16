<?php

/* Paramétres :
 -- page : nom wiki de la page a inclure (obligatoire)
 -- class : nom de la classe de style é inclure (facultatif)
 -- auth : option d'affichage dans le cas d'un utilisateur non autorisé (facultatif)
    -- par défaut : ne fait rien
    -- valeur "noError" : n'affiche aucun message d'erreur
 -- edit : option d'accés en édition é la page incluse (facultatif)
    -- par défaut : ne fait rien
    -- valeur "show" :  ajoute un lien "[édition]" en haut é droite de la boite
*/

use YesWiki\Core\Service\LinkTracker;

// récuperation du nom de la page é inclure
$incPageName = trim($this->GetParameter('page'));

/*
* @todo améliorer le traitement des classes css
*/
if ($this->GetParameter('class')) {
    $array_classes = explode(' ', $this->GetParameter('class'));
    $classes = '';
    foreach ($array_classes as $c) {
        if ($c && preg_match('`^[A-Za-z0-9-_]+$`', $c)) {
            $classes .= ($classes ? ' ' : '') . "include_$c";
        }
    }
}

// Affichage de la page ou d'un message d'erreur
//
if (empty($incPageName)) {
    echo '<div class="alert alert-danger"><strong>' . _t('ERROR') . ' ' . _t('ACTION') . ' Include</strong> : ' . _t('MISSING_PAGE_PARAMETER') . '.</div>' . "\n";
} elseif ($this->IsIncludedBy($incPageName)) {
    $inclusions = $this->GetAllInclusions();
    $pg = strtolower($incPageName); // on l'effectue avant le for sinon il sera recalculé é chaque pas
    $err = '[[' . $pg . ']]';
    for ($i = 0; $inclusions[$i] != $pg; $i++) {
        $err = '[[' . $inclusions[$i] . ']] > ' . $err;
    }
    echo '<div class="alert alert-danger"><strong>' . _t('ERROR') . ' ' . _t('ACTION') . ' Include</strong> : ' . _t('IMPOSSIBLE_FOR_THIS_PAGE') . ' ' . $incPageName . ' ' . _t('TO_INCLUDE_ITSELF')
         . ($i ? ':<br /><strong>' . _t('INCLUSIONS_CHAIN') . '</strong> : ' . $pg . ' > ' . $err : '') . '</div>' . "\n"; // si $i = 0, alors c'est une page qui s'inclut elle-méme directement...
} elseif (!$this->HasAccess('read', $incPageName) && $this->GetParameter('auth') != 'noError') {
    echo '<div class="alert alert-danger"><strong>' . _t('ERROR') . ' ' . _t('ACTION') . ' Include</strong> : ' . ' ' . _t('READING_OF_INCLUDED_PAGE') . ' ' . $incPageName . ' ' . _t('NOT_ALLOWED') . '.</div>' . "\n";
} elseif (!$incPage = $this->LoadPage($incPageName)) {
    echo '<div class="alert alert-danger"><strong>' . _t('ERROR') . ' ' . _t('ACTION') . ' Include</strong> : ' . _t('INCLUDED_PAGE') . ' ' . $incPageName . ' ' . _t('DOESNT_EXIST') . '...</div>' . "\n";
}
// Affichage de la page quand il n'y a pas d'erreur
elseif ($this->HasAccess('read', $incPageName)) {
    $this->services->get(LinkTracker::class)->forceAddIfNotIncluded($incPageName);
    $this->RegisterInclusion($incPageName);
    $output = $this->Format($incPage['body']);
    if (isset($classes)) {
        if ($this->GetParameter('edit') == 'show') {
            $editLink = '<div class="include_editlink"><a href="' . $this->Href('edit', $incPageName) . '">[' . _t('EDITION') . "]</a></div>\n";
        } else {
            $editLink = '';
        }
        // Affichage
        echo '<div class="include ' . $classes . "\">\n" . $editLink . $output . "</div>\n";
    } else {
        echo $output;
    }
    $this->UnregisterLastInclusion();
}
