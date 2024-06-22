<?php

use YesWiki\Bazar\Service\BazarListService;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\YesWikiHandler;

class __WidgetHandler extends YesWikiHandler
{
    public function run()
    {
        $entryManager = $this->getService(EntryManager::class);
        $formManager = $this->getService(FormManager::class);
        $bazarListService = $this->getService(BazarListService::class);

        if (!isset($_GET['id'])) {
            return null;
        }

        $this->wiki->AddJavascriptFile('tools/bazar/presentation/javascripts/bazar.js');

        ob_start();
        echo '<div class="page">';
        echo '<h1>' . _t('BAZ_WIDGET_HANDLER_TITLE') . '</h1>' . "\n";

        $entries = $entryManager->search(['formsIds' => [!empty($_GET['id']) ? strip_tags($_GET['id']) : null], 'keywords' => (!empty($_GET['q']) ? strip_tags($_GET['q']) : null)], true, true);
        $forms = $formManager->getAll();
        $filters = $bazarListService->getFilters(['groups' => ['all']], $entries, $forms);

        // Reproduce the sames variables from the new $filters, so the view does not need to be refactored
        $labels = $facettes = $showTooltip = [];
        foreach ($filters as $filter) {
            $labels[$filter['propName']] = $filter['title'];
            $facettes[$filter['propName']] = [
                'label' => $filter['title'],
                'source' => $filter['propName'],
            ];
            $showTooltip[$filter['propName']] = false;
        }

        $params = [
            'template' => $this->params->get('default_bazar_template'),
            'provider' => $this->params->get('baz_provider'),
            'zoom' => $this->params->get('baz_map_zoom'),
            'latitude' => $this->params->get('baz_map_center_lat'),
            'longitude' => $this->params->get('baz_map_center_lon'),
            'width' => $this->params->get('baz_map_width'),
            'height' => $this->params->get('baz_map_height'),
        ];

        $urlParams = 'id=' . strip_tags($_GET['id']) . (isset($_GET['query']) ? '&query=' . strip_tags($_GET['query']) : '') . (!empty($q) ? '&q=' . $q : '');

        echo $this->render('@bazar/widget.tpl.html', [
            'facettes' => $facettes,
            'showtooltip' => $showTooltip,
            'facettestext' => $labels,
            'params' => $params,
            'urlparams' => $urlParams,
        ]);

        echo '</div>';
        $output = ob_get_contents();
        ob_end_clean();
        echo $this->wiki->Header() . $output . $this->wiki->Footer();
        $this->wiki->exit();
    }
}
