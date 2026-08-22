<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * @Field({"map", "carte_google"})
 */
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
    private const FIELD_CLASS_TYPE = 'MapField';

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

        $autocomplete_other = [];
        if (!empty($values[self::FIELD_AUTOCOMPLETE_OTHERS])) {
            if (is_string($values[self::FIELD_AUTOCOMPLETE_OTHERS])) {
                $autocomplete_other = explode('|', $values[self::FIELD_AUTOCOMPLETE_OTHERS]);
            } else if (is_array($values[self::FIELD_AUTOCOMPLETE_OTHERS])) {
                $autocomplete_other = $values[self::FIELD_AUTOCOMPLETE_OTHERS];
            }
        }

        $postalCode = $values[self::FIELD_AUTOCOMPLETE_POSTALCODE] ?? self::DEFAULT_FIELDNAME_POSTALCODE;
        $town = $values[self::FIELD_AUTOCOMPLETE_TOWN] ?? self::DEFAULT_FIELDNAME_TOWN;

        $this->autocomplete = implode(',', [trim($postalcode), trim($town)]);

        $this->geolocate = (empty($autocomplete_other[0]) || $autocomplete_other[0] != 1) ? 0 : 1;
        $street = trim($autocomplete_other[1]) ?? self::DEFAULT_FIELDNAME_STREET;
        $street1 = trim($autocomplete_other[2]) ?? self::DEFAULT_FIELDNAME_STREET1;
        $street2 = trim($autocomplete_other[3]) ?? self::DEFAULT_FIELDNAME_STREET2;
        $county = trim($autocomplete_other[4]) ?? self::DEFAULT_FIELDNAME_COUNTY;
        $state = trim($autocomplete_other[5]) ?? self::DEFAULT_FIELDNAME_STATE;



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

    protected function renderInput($entry)
    {
        $mapFieldData = $this->getMapFieldData($entry);

        return $this->render('@bazar/inputs/map.twig', [
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
        } else {
            return [
                'fields-to-remove' => [
                    $this->getPropertyName(),
                ],
            ];
        }
    }

    protected function renderStatic($entry)
    {
        $output = '';
        $wiki = $this->getWiki();

        // check the last used action containing the good form id
        $filteredActions =
            array_filter($wiki->actionObjects, function ($v) use ($entry) {
                return !empty($v['action'])
                    && substr($v['action'], 0, 5) === 'bazar'
                    && !empty($v['vars']['id'])
                    && $v['vars']['id'] == $entry['id_typeannonce'];
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
        $currentUrlIsEntry = (explode('/', $this->getRequest()->query->get('wiki', ''))[0] === $entry['id_fiche']);

        // the map is only showed on the fullpage entry view,
        // or if action parameter showmapinlistview is set to '1'
        if (
            $this->showMapInEntryView === '1' && $currentUrlIsEntry
            || $showMapInListView
        ) {
            $mapFieldData = $this->getMapFieldData($entry);
            if ((!empty($mapFieldData['latitude']) && !empty($mapFieldData['longitude']) || !empty($mapFieldData['geometries']))) {
                $output .= $this->render('@bazar/fields/map.twig', [
                    'tag' => $entry['id_fiche'],
                    'mapFieldData' => $mapFieldData,
                ]);
            }
        }

        return $output;
    }

    // GETTERS. Needed to use them in the Twig syntax

    public function getLatitudeField()
    {
        return $this->latitudeField;
    }

    public function getLongitudeField()
    {
        return $this->longitudeField;
    }

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

    public static function mapToFieldArray($fieldProps): array
    {
        $new = parent::mapToFieldArray($fieldProps);
        $new[self::FIELD_AUTOCOMPLETE_POSTALCODE] = $fieldProps['autocompleteFieldnames']['postalCode'];
        $new[self::FIELD_AUTOCOMPLETE_TOWN] = $fieldProps['autocompleteFieldnames']['town'];
        $autocomplete_other = array_slice($fieldProps['autocompleteFieldnames'], 2);
        array_unshift($autocomplete_other, $fieldProps['geolocate']);
        $new[self::FIELD_AUTOCOMPLETE_OTHERS] = implode('|', $autocomplete_other);
        $new[self::FIELD_SHOW_MAP_IN_ENTRY_VIEW] = $fieldProps['showMapInEntryView'] ? 1 : ' ';
        $new[self::FIELD_MAX_GEOMETRIES] = $fieldProps['maxGeometries'] ?? ' ';
        ksort($new);

        return $new;
    }

    // change return of this method to keep compatible with php 7.3 (mixed is not managed)
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return array_merge(
            parent::jsonSerialize(),
            [
                'field_type' => self::FIELD_CLASS_TYPE,
                'autocomplete' => $this->getAutocomplete(),
                'geolocate' => $this->getGeolocate(),
                'autocompleteFieldnames' => $this->getAutocompleteFieldnames(),
                'showMapInEntryView' => $this->showMapInEntryView,
                'maxGeometries' => $this->max_geometries,
            ]
        );
    }
}
