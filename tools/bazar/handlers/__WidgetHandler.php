<?php

use YesWiki\Bazar\Service\BazarListService;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Bazar\Service\SearchManager;
use YesWiki\Core\YesWikiHandler;

class __WidgetHandler extends YesWikiHandler
{
    public function run()
    {
        $vSearchManager = $this->getService(SearchManager::class);
        $formManager = $this->getService(FormManager::class);
        $bazarListService = $this->getService(BazarListService::class);

        $query = $this->getRequest()->query;
        if (!$query->has('id')) {
            return null;
        }

        $this->wiki->AddJavascriptFile('tools/bazar/presentation/javascripts/bazar.js', true, true);

        ob_start();
        echo '<div class="page">';
        echo '<h1>' . _t('BAZ_WIDGET_HANDLER_TITLE') . '</h1>' . "\n";

        $id = $query->get('id');
        $q = $query->get('q');
        $entries = $vSearchManager->search(['formsIds' => [!empty($id) ? strip_tags($id) : null], 'keywords' => (!empty($q) ? strip_tags($q) : null)], true, true);
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

        $urlParams = 'id=' . urlencode(strip_tags($id)) . ($query->has('query') ? '&query=' . urlencode(strip_tags($query->get('query'))) : '') . (!empty($q) ? '&q=' . urlencode($q) : '');

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
