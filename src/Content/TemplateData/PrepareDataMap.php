<?php

namespace YesWiki\Content\TemplateData;

use YesWiki\Search\Service\SearchManager;

/**
 * Leaflet's vocabulary, from the marker settings a webmaster set (was `EntryMapAction::formatArguments()`).
 *
 * `renderMap()` in EntryListAction reads `iconSize`, `smallmarker`, `provider_credentials`
 * and the rest; until ticket 49 nothing that produced them lived in the same class, which is
 * why `{{entrylist template="map"}}` had to make a round trip through `{{entrymap}}` to work
 * at all.
 */
#[\PreparesTemplate(['gogomap', 'gogocarto', 'map-and-table'])]
class PrepareDataMap extends PrepareData
{
    public function prepare(array $arguments): array
    {
        $get = $this->getRequest()->query;

        $provider = $get->get('provider') ?? $arguments['provider'] ?? $this->config('baz_provider');
        $providerId = $arguments['providerid'] ?? null;
        $providerPass = $arguments['providerpass'] ?? null;
        if (!empty($providerId) && !empty($providerPass)) {
            $providerCredentials = $provider === 'MapBox'
                ? ', {id: \'' . $providerId . '\', accessToken: \'' . $providerPass . '\'}'
                : ', {app_id: \'' . $providerId . '\',app_code: \'' . $providerPass . '\'}';
        } else {
            $providerCredentials = '';
        }

        $markerSize = $get->get('markersize') ?? $arguments['markersize'] ?? null;
        $smallMarker = $get->get('smallmarker') ?? $arguments['smallmarker'] ?? $markerSize === 'small' ? '1' : $this->config('baz_small_marker');

        $dynamic = $this->formatBoolean($arguments, false, 'dynamic');
        $navigation = (!$dynamic)
            ? ($get->get('navigation') ?? $arguments['navigation'] ?? $this->config('baz_show_nav'))
            : $this->formatBoolean($arguments['navigation'] ?? $this->config('baz_show_nav'), true);
        $zoomWheel = (!$dynamic)
            ? ($arguments['scrollwheelzoom'] ?? $this->config('baz_wheel_zoom'))
            : $this->formatBoolean($arguments['scrollwheelzoom'] ?? $this->config('baz_wheel_zoom'), false);
        $fullscreen = (!$dynamic)
            ? ($arguments['fullscreen'] ?? 'true')
            : $this->formatBoolean($arguments, true, 'fullscreen');
        $spider = (!$dynamic)
            ? ($arguments['spider'] ?? 'false')
            : $this->formatBoolean($arguments, false, 'spider');
        $cluster = (!$dynamic)
            ? ($arguments['cluster'] ?? 'false')
            : $this->formatBoolean($arguments, false, 'cluster');

        // The retired GoGoCarto integration (ticket 34) still has its template name written
        // in stored pages; it renders as an ordinary map rather than as nothing.
        $template = $arguments['template'] ?? ($dynamic ? 'map' : 'map.twig');
        if (strpos($template, 'gogomap') !== false || strpos($template, 'gogocarto') !== false) {
            $template = $dynamic ? 'map' : 'map.twig';
        }

        return [
            'provider' => $provider,
            'providerid' => $providerId,
            'providerpass' => $providerPass,
            'provider_credentials' => $providerCredentials,

            'providers' => $this->formatArray($arguments['providers'] ?? []),
            'layers' => $this->formatArray($arguments['layers'] ?? []),

            'markersize' => $markerSize,
            'smallmarker' => $smallMarker === '1' ? '' : ' xl',
            'iconSize' => $smallMarker === '1' ? '[15, 20]' : '[35, 46]',
            'iconAnchor' => $smallMarker === '1' ? '[8, 19]' : '[18, 45]',
            'popupAnchor' => $smallMarker === '1' ? '[0, -19]' : '[0, -45]',

            'width' => $get->get('width') ?? $arguments['width'] ?? $this->config('baz_map_width'),
            'height' => $get->get('height') ?? $arguments['height'] ?? $this->config('baz_map_height'),
            'latitude' => $get->get('lat') ?? $arguments['lat'] ?? $this->config('baz_map_center_lat'),
            'longitude' => $get->get('lon') ?? $arguments['lon'] ?? $this->config('baz_map_center_lon'),
            'zoom' => $get->get('zoom') ?? $arguments['zoom'] ?? $this->config('baz_map_zoom'),

            'navigation' => $navigation,
            'zoom_molette' => $zoomWheel,
            'spider' => $spider,
            'cluster' => $cluster,
            'fullscreen' => $fullscreen,

            'jsonconfurl' => $arguments['jsonconfurl'] ?? null,
            'template' => $template,

            'entrydisplay' => $arguments['entrydisplay'] ?? 'sidebar',
            'pagination' => -1,
            'query' => $this->getService(SearchManager::class)->aggregateQueries($arguments, $get->all()),
            'geolocationfield' => $get->get('geolocationfield') ?? $arguments['geolocationfield'] ?? 'bf_geolocation',
        ];
    }
}
