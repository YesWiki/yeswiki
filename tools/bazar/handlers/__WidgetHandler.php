<?php

use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\YesWikiHandler;

class __WidgetHandler extends YesWikiHandler
{
    public function run()
    {
        $entryManager = $this->getService(EntryManager::class);
        $formManager = $this->getService(FormManager::class);

        if (!isset($_GET['id'])) {
            return null;
        }

        $this->wiki->AddJavascriptFile('tools/bazar/libs/bazar.js');

        echo $this->wiki->Header();
        echo '<div class="page">';
        echo '<h1>Partager les résultats par widget HTML (code embed)</h1>' . "\n";

        $entries = $entryManager->search(['formsIds' => [$_GET['id'] ?? null], 'keywords' => $_GET['q'] ?? null], true, true);
        $facettables = $formManager->scanAllFacettable($entries);
   
        $labels = array();
        $showTooltip = [];
        foreach ($entries as $entry) {
            $form = $formManager->getOne($entry['id_typeannonce']);
            foreach ($form['prepared'] as $field) {
                $propName = $field->getPropertyName() ;
                if (in_array($propName, array_keys($facettables)) &&
                        !in_array($propName, array_keys($labels))) {
                    $labels[$propName] = !empty($field->getLabel()) ? $field->getLabel() :
                        ($facettables[$propName]['source'] ?? $propName);
                    $showTooltip[$propName] = false;
                    if (!isset($facettables[$propName]['label'])) {
                        $facettables[$propName]['label'] = $labels[$propName];
                    }
                }
            }
        }

        $params = [
            'template' => $this->params->get('default_bazar_template'),
            'provider' => $this->params->get('baz_provider'),
            'zoom' => $this->params->get('baz_map_zoom'),
            'latitude' => $this->params->get('baz_map_center_lat'),
            'longitude' => $this->params->get('baz_map_center_lon'),
            'width' => $this->params->get('baz_map_width'),
            'height' => $this->params->get('baz_map_height')
        ];

        $urlParams = 'id=' . $_GET['id'] . (isset($_GET['query']) ? '&query=' . $_GET['query'] : '') . (!empty($q) ? '&q=' . $q : '');

        echo $this->render("@bazar/widget.tpl.html", [
            'facettes' => $facettables,
            'showtooltip' => $showTooltip,
            'facettestext' => $labels,
            'params' => $params,
            'urlparams' => $urlParams
        ]);

        echo '</div>';
        echo $this->wiki->Footer();
        exit();
    }
};
