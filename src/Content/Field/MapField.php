<?php

namespace YesWiki\Content\Field;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Kernel\Service\LanguageService;
use YesWiki\Render\Service\ActionRunner;

#[\Field(['map', 'carte_google'])]
class MapField extends BazarField
{
    protected $autocompleteFieldnames;
    protected $autocomplete;
    protected $geolocate;
    protected $showMapInEntryView;
    protected $geometries;
    protected $max_geometries;
    protected $has_geometries;

    protected const FIELD_AUTOCOMPLETE_POSTALCODE = 4;
    protected const FIELD_AUTOCOMPLETE_TOWN = 5;
    protected const FIELD_AUTOCOMPLETE_OTHERS = 6;
    protected const FIELD_SHOW_MAP_IN_ENTRY_VIEW = 7;
    protected const FIELD_GEOMETRIES = 9;
    protected const FIELD_MAX_GEOMETRIES = 13;
    public const DEFAULT_FIELDNAME_POSTALCODE = 'bf_code_postal';
    public const DEFAULT_FIELDNAME_STREET = 'bf_adresse';
    public const DEFAULT_FIELDNAME_STREET1 = 'bf_adresse1';
    public const DEFAULT_FIELDNAME_STREET2 = 'bf_adresse2';
    public const DEFAULT_FIELDNAME_TOWN = 'bf_ville';
    public const DEFAULT_FIELDNAME_COUNTY = '';
    public const DEFAULT_FIELDNAME_STATE = 'bf_pays';
    public const DEFAULT_GEOMETRIES = 'marker';
    public const AVAILABLE_GEOMETRIES = ['marker', 'line', 'polygon', 'rectangle', 'circle'];

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->showMapInEntryView = $values[self::FIELD_SHOW_MAP_IN_ENTRY_VIEW] ?? '0';
        $this->autocomplete = (!empty($values[self::FIELD_AUTOCOMPLETE_POSTALCODE]) && !empty($values[self::FIELD_AUTOCOMPLETE_TOWN])) ?
            trim($values[self::FIELD_AUTOCOMPLETE_POSTALCODE]) . ',' . trim($values[self::FIELD_AUTOCOMPLETE_TOWN]) : null;

        $autocomplete = empty($this->autocomplete) ? '' : (
            is_string($this->autocomplete)
            ? $this->autocomplete
            : (
                is_array($this->autocomplete)
                ? implode(',', $this->autocomplete)
                : ''
            )
        );
        $data = array_map('trim', explode(',', $autocomplete));
        $postalCode = empty($data[0]) ? self::DEFAULT_FIELDNAME_POSTALCODE : $data[0];
        $town = empty($data[1]) ? self::DEFAULT_FIELDNAME_TOWN : $data[1];

        $autocompleteFieldnames = empty($values[self::FIELD_AUTOCOMPLETE_OTHERS])
            ? ''
            : (
                is_string($values[self::FIELD_AUTOCOMPLETE_OTHERS])
                ? $values[self::FIELD_AUTOCOMPLETE_OTHERS]
                : (
                    is_array($values[self::FIELD_AUTOCOMPLETE_OTHERS])
                    ? implode('|', $values[self::FIELD_AUTOCOMPLETE_OTHERS])
                    : ''
                )
            );
        $data = array_map('trim', explode('|', $autocompleteFieldnames));

        $this->geolocate = (empty($data[0]) || $data[0] != 1) ? 0 : 1;
        $street = empty($data[1]) ? self::DEFAULT_FIELDNAME_STREET : $data[1];
        $street1 = empty($data[2]) ? self::DEFAULT_FIELDNAME_STREET1 : $data[2];
        $street2 = empty($data[3]) ? self::DEFAULT_FIELDNAME_STREET2 : $data[3];
        $county = empty($data[4]) ? self::DEFAULT_FIELDNAME_COUNTY : $data[4];
        $state = empty($data[5]) ? self::DEFAULT_FIELDNAME_STATE : $data[5];

        $this->autocompleteFieldnames = compact(['postalCode', 'town', 'street', 'street1', 'street2', 'county', 'state']);

        $geometries = $values[self::FIELD_GEOMETRIES] ?? self::DEFAULT_GEOMETRIES;
        $geometries = explode(',', $geometries);
        $geometries = array_map('trim', $geometries);
        $geometries = array_intersect($geometries, self::AVAILABLE_GEOMETRIES);
        $this->geometries = !empty($geometries) ? $geometries : self::DEFAULT_GEOMETRIES;

        $this->max_geometries = intval($values[self::FIELD_MAX_GEOMETRIES] ?? 0);

        $geometriesWithoutMarker = array_diff($geometries, ['marker']);
        $this->has_geometries = !empty($geometriesWithoutMarker);
    }

    public function getValueStructure() // See BazarField::getValueStructure
    {
        return [
            $this->propertyName => [
                'latitude' => ['_mode_' => 'single', '_type_' => 'number'],
                'longitude' => ['_mode_' => 'single', '_type_' => 'number'],
                'geometries' => ['_mode_' => 'single', '_type_' => 'string'],
            ],
        ];
    }

    protected function getValue($entry)
    {
        $value = $entry[$this->propertyName] ?? [];

        return [
            'latitude' => $value['latitude'] ?? '',
            'longitude' => $value['longitude'] ?? '',
            'geometries' => json_decode($value['geometries'] ?? '', true) ?? '',
        ];
    }

    public function isEmpty($pValue)
    {
        if (empty($pValue) || !is_array($pValue)) {
            return true;
        }

        $vLatitude = $pValue['latitude'] ?? '';
        $vLongitude = $pValue['longitude'] ?? '';
        $vGeometries = $pValue['geometries'] ?? '';

        return trim($vLatitude) == '' && trim($vLongitude) == '' && trim($vGeometries) == '';
    }

    protected function getMapFieldData($entry)
    {
        $value = $this->getValue($entry);

        $params = $this->getService(ParameterBagInterface::class);

        $mapProvider = $params->get('baz_provider');
        $mapProviderId = $params->get('baz_provider_id');
        $mapProviderPass = $params->get('baz_provider_pass');
        if (!empty($mapProviderId) && !empty($mapProviderPass)) {
            if ($mapProvider == 'MapBox') {
                $mapProviderCredentials = [
                    'id' => $mapProviderId,
                    'accessToken' => $mapProviderPass,
                ];
            } else {
                $mapProviderCredentials = [
                    'app_id' => $mapProviderId,
                    'app_code' => $mapProviderPass,
                ];
            }
        } else {
            $mapProviderCredentials = null;
        }

        $latitude = is_array($value) && !empty($value['latitude']) ? $value['latitude'] : null;
        $longitude = is_array($value) && !empty($value['longitude']) ? $value['longitude'] : null;
        $geometries = is_array($value) && !empty($value['geometries']) ? $value['geometries'] : null;

        return [
            'bazWheelZoom' => $params->get('baz_wheel_zoom'),
            'bazShowNav' => $params->get('baz_show_nav'),
            'bazMapCenterLat' => $params->get('baz_map_center_lat'),
            'bazMapCenterLon' => $params->get('baz_map_center_lon'),
            'bazMapZoom' => $params->get('baz_map_zoom'),
            'mapProvider' => $mapProvider,
            'mapProviderCredentials' => $mapProviderCredentials,
            'geolocationfield' => $this->propertyName,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'geometries' => $geometries,
            'chosenGeometries' => $this->geometries,
            'maxGeometries' => $this->max_geometries,
            'hasGeometries' => $this->has_geometries,
        ];
    }

    /**
     * The labels javascripts/inputs/map-leaflet.js asks `_t()` for.
     *
     * `_t()` in the browser reads the javascript catalog only, so a key that lives in
     * yeswiki_*.php and not yeswikijs_*.php renders as its own name -- the whole map
     * widget was showing BAZ_ADJUST_MARKER_POSITION and forty other raw keys.
     *
     * @var list<string>
     */
    private const JAVASCRIPT_LABELS = [
        'BAZ_ADJUST_MARKER_POSITION', 'BAZ_GEOLOC_NOT_FOUND', 'BAZ_MAP_ERROR',
        'BAZ_NOT_VALID_GEOLOC_FORMAT', 'CANCEL_BUTTON_TEXT', 'CANCEL_DRAWING_TITLE',
        'CANCEL_EDITING_TITLE', 'CIRCLE_MARKER_TOOLTIP_START', 'CIRCLE_RADIUS_LABEL',
        'CIRCLE_TOOLTIP_START', 'CLEAR_ALL_BUTTON_TEXT', 'CLEAR_ALL_LAYERS_TITLE',
        'DELETE_DISABLED_BUTTON', 'DELETE_LAST_POINT_TEXT', 'DELETE_LAST_POINT_TITLE',
        'DELETE_LAYERS_BUTTON', 'DRAW_CIRCLE_BUTTON', 'DRAW_CIRCLE_MARKER_BUTTON',
        'DRAW_MARKER_BUTTON', 'DRAW_POLYGON_BUTTON', 'DRAW_POLYLINE_BUTTON',
        'DRAW_RECTANGLE_BUTTON', 'EDIT_DISABLED_BUTTON', 'EDIT_LAYERS_BUTTON',
        'EDIT_TOOLTIP_SUBTEXT', 'EDIT_TOOLTIP_TEXT', 'FINISH_BUTTON_TEXT',
        'FINISH_DRAWING_TITLE', 'GEOLOCATER_GROUP_GEOLOCATIZATION', 'GEOLOCATER_NOT_FOUND',
        'MARKER_TOOLTIP_START', 'POLYGON_TOOLTIP_CONT', 'POLYGON_TOOLTIP_END',
        'POLYGON_TOOLTIP_START', 'POLYLINE_ERROR', 'POLYLINE_TOOLTIP_CONT',
        'POLYLINE_TOOLTIP_END', 'POLYLINE_TOOLTIP_START', 'RECTANGLE_TOOLTIP_START',
        'REMOVE_TOOLTIP_TEXT', 'SAVE_BUTTON_TEXT', 'SAVE_CHANGES_TITLE',
        'SIMPLE_SHAPE_TOOLTIP_END',
    ];

    protected function renderInput($entry)
    {
        $mapFieldData = $this->getMapFieldData($entry);
        $this->getService(LanguageService::class)->loadJavascriptTranslations(...self::JAVASCRIPT_LABELS);

        return $this->render('@core/inputs/map.twig', [
            'latitude' => $mapFieldData['latitude'],
            'longitude' => $mapFieldData['longitude'],
            'geometries' => $mapFieldData['geometries'],
            'mapFieldData' => $mapFieldData,
        ]);
    }

    public function formatValuesBeforeSave($entry)
    {
        $vValue = $this->getValue($entry);

        $vLatitude = isset($vValue['latitude']) && is_numeric($vValue['latitude']) && is_string($vValue['latitude']) ? $vValue['latitude'] : '';
        $vLongitude = isset($vValue['longitude']) && is_numeric($vValue['longitude']) && is_string($vValue['longitude']) ? $vValue['longitude'] : '';
        $vGeometries = isset($vValue['geometries']) && is_array($vValue['geometries']) ? json_encode($vValue['geometries']) : '';

        if ((!empty($vLatitude) && !empty($vLongitude)) || !empty($vGeometries)) {
            return
            [
                $this->getPropertyName() => [
                    'latitude' => $vLatitude,
                    'longitude' => $vLongitude,
                    'geometries' => $vGeometries,
                ],
            ];
        }

        return [
            'fields-to-remove' => [
                $this->getPropertyName(),
            ],
        ];
    }

    protected function renderStatic($entry)
    {
        $output = '';

        // check the last used action containing the good form id
        $filteredActions =
            array_filter($this->getService(ActionRunner::class)->actionsLog(), function ($v) use ($entry) {
                return !empty($v['action'])
                    && substr($v['action'], 0, 5) === 'bazar'
                    && !empty($v['vars']['id'])
                    && $v['vars']['id'] == $entry['form_id'];
            });
        $lastAction = end($filteredActions);
        $showMapInDynamicListView = ($this->getRequest()->query->get('showmapinlistview') === '1');
        $showMapInListView = false;
        if (
            // classic list would perform action
            (!empty($lastAction['vars']['showmapinlistview'])
            && $lastAction['vars']['showmapinlistview'] === '1')
            // dynamic list calls api and use get param showmapinlistview
            || $showMapInDynamicListView
        ) {
            $showMapInListView = true;
        }
        $currentUrlIsEntry = (explode('/', $this->getRequest()->query->get('wiki', ''))[0] === $entry['tag']);

        // the map is only showed on the fullpage entry view,
        // or if action parameter showmapinlistview is set to '1'
        if (
            $this->showMapInEntryView === '1' && $currentUrlIsEntry
            || $showMapInListView
        ) {
            $mapFieldData = $this->getMapFieldData($entry);
            if (!empty($mapFieldData['latitude']) && !empty($mapFieldData['longitude']) || !empty($mapFieldData['geometries'])) {
                $output .= $this->render('@core/fields/map.twig', [
                    'tag' => $entry['tag'],
                    'mapFieldData' => $mapFieldData,
                ]);
            }
        }

        return $output;
    }

    // GETTERS. Needed to use them in the Twig syntax

    public function getAutocomplete()
    {
        return $this->autocomplete;
    }

    public function getGeolocate()
    {
        return $this->geolocate;
    }

    public function getAutocompleteFieldnames()
    {
        return $this->autocompleteFieldnames;
    }

    // change return of this method to keep compatible with php 7.3 (mixed is not managed)
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return array_merge(
            parent::jsonSerialize(),
            [
                'autocomplete' => $this->getAutocomplete(),
                'geolocate' => $this->getGeolocate(),
                'autocompleteFieldnames' => $this->getAutocompleteFieldnames(),
            ]
        );
    }
}
