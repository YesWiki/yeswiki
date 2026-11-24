<?php

namespace YesWiki\Content\TemplateData;

use YesWiki\Content\Attribute\PreparesTemplate;
use YesWiki\Content\Entity\FieldRole;
use YesWiki\Content\Service\FieldRoleResolver;
use YesWiki\Content\Service\FormManager;
use YesWiki\Search\Service\SearchManager;

/** What the map draws with: its background, its controls and its markers, from the settings a webmaster set. */
#[PreparesTemplate(['gogomap', 'gogocarto', 'map-and-table'])]
class PrepareDataMap extends PrepareData
{
    public function prepare(array $arguments): array
    {
        $get = $this->getRequest()->query;

        $markerSize = $get->get('markersize') ?? $arguments['markersize'] ?? null;
        $smallMarker = $get->get('smallmarker') ?? $arguments['smallmarker'] ?? ($markerSize === 'small' ? '1' : $this->config('baz_small_marker'));

        $template = (string)($arguments['template'] ?? 'map');
        if (str_contains($template, 'gogomap') || str_contains($template, 'gogocarto')) {
            $template = 'map';
        }

        return [
            'provider' => $get->get('provider') ?? $arguments['provider'] ?? $this->config('baz_provider'),
            'providerid' => $arguments['providerid'] ?? null,
            'providerpass' => $arguments['providerpass'] ?? null,
            'providers' => $this->formatArray($arguments['providers'] ?? []),
            'layers' => $this->formatArray($arguments['layers'] ?? []),

            'markersize' => $markerSize,
            'smallmarker' => $this->formatBoolean($smallMarker, false),

            'width' => $get->get('width') ?? $arguments['width'] ?? $this->config('baz_map_width'),
            'height' => $get->get('height') ?? $arguments['height'] ?? $this->config('baz_map_height'),
            'latitude' => $get->get('lat') ?? $arguments['lat'] ?? $this->config('baz_map_center_lat'),
            'longitude' => $get->get('lon') ?? $arguments['lon'] ?? $this->config('baz_map_center_lon'),
            'zoom' => $get->get('zoom') ?? $arguments['zoom'] ?? $this->config('baz_map_zoom'),

            'navigation' => $this->formatBoolean($get->get('navigation') ?? $arguments['navigation'] ?? $this->config('baz_show_nav'), true),
            'zoom_molette' => $this->formatBoolean($arguments['scrollwheelzoom'] ?? $this->config('baz_wheel_zoom'), false),
            'cluster' => $this->formatBoolean($arguments, false, 'cluster'),
            'fullscreen' => $this->formatBoolean($arguments, true, 'fullscreen'),

            'template' => $template,

            'entrydisplay' => $arguments['entrydisplay'] ?? 'sidebar',
            'pagination' => -1,
            'query' => $this->getService(SearchManager::class)->aggregateQueries($arguments, $get->all()),
            'geolocationfield' => $get->get('geolocationfield') ?? $arguments['geolocationfield'] ?? $this->geolocationField($arguments),
        ];
    }

    /**
     * The field the listed form marks as its location, since a form migrated from before the geolocation model keeps its old name.
     *
     * @param array<string, mixed> $arguments
     */
    private function geolocationField(array $arguments): string
    {
        $ids = $arguments['id'] ?? [];
        $ids = is_array($ids) ? $ids : explode(',', (string)$ids);
        $formManager = $this->getService(FormManager::class);
        $roles = $this->getService(FieldRoleResolver::class);
        foreach ($ids as $id) {
            $name = $roles->propertyName($formManager->getOne(trim((string)$id)), FieldRole::GEOLOCATION);
            if ($name !== null) {
                return $name;
            }
        }

        return 'bf_geolocation';
    }
}
