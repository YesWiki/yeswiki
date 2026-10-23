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
     * Turns entries into a FeatureCollection: one feature for an entry's point, one per shape drawn on its map.
     */
    public function formatToGeoJSON(array $entries): array
    {
        $cache = [];
        $features = [];
        foreach ($entries as $entry) {
            $propertyName = $this->getMapFieldPropertyNameOfEntry($entry, $cache);
            $properties = $entry;
            if ($propertyName !== null && is_array($properties[$propertyName] ?? null)) {
                unset($properties[$propertyName]['geometries']);
            }
            $id = $entry['id_fiche'] ?? null;
            $title = $entry['bf_titre'] ?? null;

            $geo = $this->getGeoData($entry, $cache);
            if (!empty($geo)) {
                $features[] = [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [floatval($geo['longitude']), floatval($geo['latitude'])],
                    ],
                    'id' => $id,
                    'title' => $title,
                    'properties' => $properties,
                ];
            }

            foreach ($this->getDrawnFeatures($entry, $propertyName) as $index => $drawn) {
                $features[] = [
                    'type' => 'Feature',
                    'geometry' => $drawn['geometry'],
                    'id' => $id === null ? null : $id . '-' . ($index + 1),
                    'title' => $title,
                    'properties' => array_merge($properties, is_array($drawn['properties'] ?? null) ? $drawn['properties'] : []),
                ];
            }
        }

        return [
            'type' => 'FeatureCollection',
            'features' => $features,
        ];
    }

    /**
     * extract geoData.
     *
     * @return array ['latitude'=>000,'longitude'=>00] or []
     */
    public function getGeoData(array $entry, array &$cache): array
    {
        $location = $entry[$this->getMapFieldPropertyNameOfEntry($entry, $cache) ?? ''] ?? null;
        if (!empty($location['latitude']) && !empty($location['longitude'])) {
            $latitude = $location['latitude'];
            $longitude = $location['longitude'];
        } elseif (!empty($location['bf_latitude']) && !empty($location['bf_longitude'])) {
            $latitude = $location['bf_latitude'];
            $longitude = $location['bf_longitude'];
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
     * Returns the features drawn on the entry's map field, each with a geometry.
     */
    private function getDrawnFeatures(array $entry, ?string $propertyName): array
    {
        $geometries = $propertyName === null ? null : ($entry[$propertyName]['geometries'] ?? null);
        if (is_string($geometries)) {
            $geometries = json_decode($geometries, true);
        }
        if (!is_array($geometries)) {
            return [];
        }
        $features = ($geometries['type'] ?? null) === 'FeatureCollection' ? ($geometries['features'] ?? []) : [$geometries];

        return array_values(array_filter($features, fn ($feature) => is_array($feature) && !empty($feature['geometry']['type'])));
    }

    private function getMapFieldPropertyNameOfEntry(array $entry, array &$cache): ?string
    {
        if (empty($entry['id_typeannonce']) || $entry['id_typeannonce'] != intval($entry['id_typeannonce'])) {
            return null;
        }

        return $this->getFirstMapFieldPropertyName($entry['id_typeannonce'], $cache);
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
        if (!array_key_exists($formId, $cache)) {
            $form = $this->formManager->getOne($formId);
            $cache[$formId] = null;
            foreach ($form['prepared'] ?? [] as $field) {
                if ($field instanceof MapField) {
                    $cache[$formId] = $field->getPropertyName();
                    break;
                }
            }
        }

        return $cache[$formId];
    }
}
