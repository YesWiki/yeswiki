<?php

namespace YesWiki\Content\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Search\Service\SearchManager;

class EntryMapAction extends YesWikiAction implements RegisteredAction, ProvidesComponents
{
    /** `{{entrymap}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'entrymap';
    }

    /** `{{entrymap}}` itself, which the palette does not offer. */
    public function components(): array
    {
        return [
            Component::for('entrymap-legacy')
                ->writes('entrymap')
                ->category(Category::Lists)
                ->label(_t('AB_bazarcarto_label'))
                ->icon('map-2')
                ->notOffered(),
        ];
    }

    public function formatArguments($arg)
    {
        $get = $this->getRequest()->query;
        $provider = $get->get('provider') ?? $arg['provider'] ?? $this->params->get('baz_provider');
        $providerId = $arg['providerid'] ?? null;
        $providerPass = $arg['providerpass'] ?? null;
        if (!empty($providerId) && !empty($providerPass)) {
            if ($provider === 'MapBox') {
                $providerCredentials = ', {id: \'' . $providerId . '\', accessToken: \'' . $providerPass . '\'}';
            } else {
                $providerCredentials = ', {app_id: \'' . $providerId . '\',app_code: \'' . $providerPass . '\'}';
            }
        } else {
            $providerCredentials = '';
        }

        $markerSize = $get->get('markersize') ?? $arg['markersize'] ?? null;
        $smallMarker = $get->get('smallmarker') ?? $arg['smallmarker'] ?? $markerSize === 'small' ? '1' : $this->params->get('baz_small_marker');

        $dynamic = $this->formatBoolean($arg, false, 'dynamic');
        $navigation = (!$dynamic) ?
            ($get->get('navigation') ?? $arg['navigation'] ?? $this->params->get('baz_show_nav')) :
            $this->formatBoolean($arg['navigation'] ?? $this->params->get('baz_show_nav'), true);
        $zoom_molette = (!$dynamic) ?
            ($arg['scrollwheelzoom'] ?? $this->params->get('baz_wheel_zoom')) :
            $this->formatBoolean($arg['scrollwheelzoom'] ?? $this->params->get('baz_wheel_zoom'), false);
        $fullscreen = (!$dynamic) ?
            ($arg['fullscreen'] ?? 'true') :
            $this->formatBoolean($arg, true, 'fullscreen');
        $template = (!$dynamic) ?
            ($arg['template'] ?? 'map.twig') :
            ($arg['template'] ?? 'map');

        if (strpos($template, 'gogomap') !== false || strpos($template, 'gogocarto') !== false) {
            $template = 'map';
        }
        $spider = (!$dynamic) ?
            ($arg['spider'] ?? 'false') :
            $this->formatBoolean($arg, false, 'spider');
        $cluster = (!$dynamic) ?
            ($arg['cluster'] ?? 'false') :
            $this->formatBoolean($arg, false, 'cluster');

        $vSearchManager = $this->getService(SearchManager::class);

        $query = $vSearchManager->aggregateQueries($arg, $get->all());

        return [
            'provider' => $provider,
            'providerid' => $providerId,
            'providerpass' => $providerPass,
            'provider_credentials' => $providerCredentials,

            'providers' => $this->formatArray($arg['providers'] ?? []),

            'layers' => $this->formatArray($arg['layers'] ?? []),

            'markersize' => $markerSize,
            'smallmarker' => $smallMarker === '1' ? '' : ' xl',
            'iconSize' => $smallMarker === '1' ? '[15, 20]' : '[35, 46]',
            'iconAnchor' => $smallMarker === '1' ? '[8, 19]' : '[18, 45]',
            'popupAnchor' => $smallMarker === '1' ? '[0, -19]' : '[0, -45]',

            'width' => $get->get('width') ?? $arg['width'] ?? $this->params->get('baz_map_width'),

            'height' => $get->get('height') ?? $arg['height'] ?? $this->params->get('baz_map_height'),

            'latitude' => $get->get('lat') ?? $arg['lat'] ?? $this->params->get('baz_map_center_lat'),

            'longitude' => $get->get('lon') ?? $arg['lon'] ?? $this->params->get('baz_map_center_lon'),

            'zoom' => $get->get('zoom') ?? $arg['zoom'] ?? $this->params->get('baz_map_zoom'),

            'navigation' => $navigation,

            'zoom_molette' => $zoom_molette,

            'spider' => $spider,

            'cluster' => $cluster,

            'fullscreen' => $fullscreen,

            'jsonconfurl' => $arg['jsonconfurl'] ?? null,

            'template' => $template,

            'entrydisplay' => $arg['entrydisplay'] ?? 'sidebar',
            'pagination' => -1,
            'query' => $query,
            'geolocationfield' => $get->get('geolocationfield') ?? $arg['geolocationfield'] ?? 'bf_geolocation',
        ];
    }

    public function run()
    {
        return $this->callAction('entrylist', $this->arguments);
    }
}
