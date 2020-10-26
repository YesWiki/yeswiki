<?php
/*

Copyright (c) 2016, Florian Schmitt <mrflos@gmail.com>
All rights reserved.
Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions
are met:
1. Redistributions of source code must retain the above copyright
notice, this list of conditions and the following disclaimer.
2. Redistributions in binary form must reproduce the above copyright
notice, this list of conditions and the following disclaimer in the
documentation and/or other materials provided with the distribution.
3. The name of the author may not be used to endorse or promote products
derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE AUTHOR ``AS IS'' AND ANY EXPRESS OR
IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES
OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT,
INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

// Vérification de sécurité
if (!defined('WIKINI_VERSION')) {
    die('acc&egrave;s direct interdit');
}

$ficheManager = $this->services->get('bazar.fiche.manager');

if (isset($_GET['id'])) {
    // js lib
    $this->AddJavascriptFile('tools/bazar/libs/bazar.js');

    echo $this->Header();
    echo '<h1>Partager les résultats par widget HTML (code embed)</h1>'."\n";
    $params = getAllParameters($this);

    // chaine de recherche
    $q = '';
    if (isset($_GET['q']) and !empty($_GET['q'])) {
        $q = $_GET['q'];
    }

    // tableau des fiches correspondantes aux critères
    if (is_array($params['idtypeannonce'])) {
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
    //$params['groups'][0] = 'all';
    //$results = searchResultstoArray($results, $params);
    //$tabfacette = scanAllFacettable($results, $params, '', true);
    $formval = baz_valeurs_formulaire($_GET['id']);
    $alllists = multiArraySearch($formval["prepared"], 'type', 'select');
    $allcheckboxes = multiArraySearch($formval["prepared"], 'type', 'checkbox');
    $tabfacette = array_merge($alllists, $allcheckboxes);
    $tabfacettetext = array();
    foreach ($tabfacette as $key => $fac) {
        $tabfacettetext[$fac['id']] = $fac['label'];
        $showtooltip[$fac['id']] = false;
    }
    $urlparams = 'id='.$_GET['id']
      .(isset($_GET['query']) ? '&query='.$_GET['query'] : '')
      .(!empty($q) ? '&q='.$q : '');
    // affichage des resultats
 
    $values = array('facettes' => $tabfacette, 'showtooltip' => $showtooltip, 'facettestext' => $tabfacettetext, 'params' => $params, 'urlparams' => $urlparams);

    include_once 'includes/squelettephp.class.php';
    try {
        $squel = new SquelettePhp('widget.tpl.html', 'bazar');
        echo $squel->render($values);
    } catch (Exception $e) {
        echo '<div class="alert alert-danger">Erreur handler widget : ',  $e->getMessage(), '</div>'."\n";
    }

    echo $this->Footer();
    exit;
}
