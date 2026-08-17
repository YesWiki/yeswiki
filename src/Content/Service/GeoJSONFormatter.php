<?php

namespace YesWiki\Content\Service;

use YesWiki\Content\Entity\FieldRole;
use YesWiki\Core\YesWikiController;

class GeoJSONFormatter extends YesWikiController
{
    protected $formManager;
    protected FieldRoleResolver $fieldRoles;

    public function __construct(
        FormManager $formManager,
        FieldRoleResolver $fieldRoles
    ) {
        $this->formManager = $formManager;
        $this->fieldRoles = $fieldRoles;
    }

    /**
     * get data grom entries in GeoJSON format.
     *
     * @return array data
     */
    public function formatToGeoJSON(array $entries): array
    {
        $cache = [];
        $entriesWithGeo = array_filter(array_map(function ($entry) use ($cache) {
            $geo = $this->getGeoData($entry, $cache);
            if (empty($geo)) {
                return [];
            }

            return [
                'entry' => $entry,
                'geo' => $geo,
            ];
        }, $entries), function ($entry) {
            return !empty($entry);
        });

        $data = [];
        if (!empty($entriesWithGeo)) {
            $data['type'] = 'FeatureCollection';
            $data['features'] = [];
            foreach ($entriesWithGeo as $id => $extendedEntry) {
                $entry = $extendedEntry['entry'];
                $data['features'][] = [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [$extendedEntry['geo']['longitude'], $extendedEntry['geo']['latitude']],
                    ],
                    'id' => $entry['tag'],

                    'title' => $entry['title'] ?? $entry['bf_titre'] ?? '',
                    'properties' => $entry,
                ];
            }
        }

        return $data;
    }

    /**
     * extract geoData.
     *
     * @return array ['latitude'=>000,'longitude'=>00] or []
     */
    public function getGeoData(array $entry, array &$cache): array
    {
        $propertyName = '';
        if (!empty($entry['form_id']) && $entry['form_id'] == intval($entry['form_id'])) {
            $propertyName = (string)$this->geolocationPropertyName((int)$entry['form_id'], $cache);
        }
        if (!empty($entry[$propertyName]['latitude']) && !empty($entry[$propertyName]['longitude'])) {
            $latitude = $entry[$propertyName]['latitude'];
            $longitude = $entry[$propertyName]['longitude'];
        } elseif (!empty($entry[$propertyName]['bf_latitude']) && !empty($entry[$propertyName]['bf_longitude'])) {
            $latitude = $entry[$propertyName]['bf_latitude'];
            $longitude = $entry[$propertyName]['bf_longitude'];
        } elseif (!empty($entry['bf_latitude']) && !empty($entry['bf_longitude'])) {
            $latitude = $entry['bf_latitude'];
            $longitude = $entry['bf_longitude'];
        } elseif (!empty($entry['carte_google'])
                && !empty(explode('|', $entry['carte_google'])[0])
                && !empty(explode('|', $entry['carte_google'])[1])) {
            $geo = explode('|', $entry['carte_google']);
            $latitude = $geo[0];
            $longitude = $geo[1];
        } else {
            return [];
        }

        return [
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];
    }

    /**
     * Which field of this form holds the geolocation -- the form's answer, not a guess at a field name (ticket 11).
     *
     * @param array<int, string|null> &$cache per-form-id memo
     */
    private function geolocationPropertyName(int $formId, array &$cache): ?string
    {
        if (!array_key_exists($formId, $cache)) {
            $cache[$formId] = $this->fieldRoles->propertyName(
                $this->formManager->getOne($formId),
                FieldRole::GEOLOCATION
            );
        }

        return $cache[$formId];
    }
}
