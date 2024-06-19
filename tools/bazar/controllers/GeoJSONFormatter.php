<?php

namespace YesWiki\Bazar\Controller;

use YesWiki\Bazar\Field\MapField;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\YesWikiController;

class GeoJSONFormatter extends YesWikiController
{
    protected $formManager;

    public function __construct(
        FormManager $formManager
    ) {
        $this->formManager = $formManager;
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
            } else {
                return [
                    'entry' => $entry,
                    'geo' => $geo,
                ];
            }
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
                    'id' => $entry['id_fiche'],
                    'title' => $entry['bf_titre'],
                    'properties' => $entry,
                ];
            }
        }

        return $data;
    }

    /**
     * extract geoData.
     *
     * @param array &$cache
     *
     * @return array ['latitude'=>000,'longitude'=>00] or []
     */
    public function getGeoData(array $entry, array &$cache): array
    {
        if (!empty($entry['id_typeannonce']) && $entry['id_typeannonce'] == intval($entry['id_typeannonce'])) {
            $propertyName = $this->getFirstMapFieldPropertyName($entry['id_typeannonce'], $cache);
        }
        if (!empty($entry[$propertyName])
                && !empty($entry[$propertyName]['bf_latitude'])
                && !empty($entry[$propertyName]['bf_longitude'])) {
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
     * get first field propertyName corresponding to a MapField in a form.
     *
     * @param array &$cache cache of correspondance of propertynames and forms id
     *
     * @return string|null $propertyName
     */
    private function getFirstMapFieldPropertyName(int $formId, array &$cache): ?string
    {
        if (!isset($cache[$formId])) {
            $form = $this->formManager->getOne($formId);
            $cache[$formId] = null;
            if (!empty($form['prepared'])) {
                $found = false;
                foreach ($form['prepared'] as $field) {
                    if (!$found && $field instanceof MapField) {
                        $cache[$formId] = $field->getPropertyName();
                        $found = true;
                    }
                }
            }
        }

        return $cache[$formId];
    }
}
