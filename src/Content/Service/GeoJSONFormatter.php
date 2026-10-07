<?php

namespace YesWiki\Content\Service;

use YesWiki\Content\Entity\FieldRole;
use YesWiki\Core\YesWikiController;

class GeoJSONFormatter extends YesWikiController
{
    protected FormManager $formManager;
    protected FieldRoleResolver $fieldRoles;

    public function __construct(
        FormManager $formManager,
        FieldRoleResolver $fieldRoles
    ) {
        $this->formManager = $formManager;
        $this->fieldRoles = $fieldRoles;
    }

    /**
     * Turns entries into a GeoJSON FeatureCollection.
     *
     * @param array<int|string, array<string, mixed>> $entries
     *
     * @return array{type: string, features: list<array<string, mixed>>}
     */
    public function formatToGeoJSON(array $entries): array
    {
        /** @var array<int, string|null> $cache */
        $cache = [];
        $features = [];
        foreach ($entries as $entry) {
            $propertyName = $this->geolocationPropertyNameOfEntry($entry, $cache);
            $properties = $entry;
            if ($propertyName !== null && is_array($properties[$propertyName] ?? null)) {
                unset($properties[$propertyName]['geometries']);
            }
            $id = $entry['tag'] ?? null;
            $title = $entry['title'] ?? $entry['bf_titre'] ?? '';

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
     * @param array<string, mixed>    $entry
     * @param array<int, string|null> $cache per-form-id memo
     *
     * @return array<string, mixed> ['latitude'=>000,'longitude'=>00] or []
     */
    public function getGeoData(array $entry, array &$cache): array
    {
        $propertyName = (string)$this->geolocationPropertyNameOfEntry($entry, $cache);
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
     * The shapes drawn on the entry's map field.
     *
     * @param array<string, mixed> $entry
     *
     * @return list<array<string, mixed>>
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

        return array_values(array_filter(
            is_array($features) ? $features : [],
            fn ($feature) => is_array($feature) && !empty($feature['geometry']['type'])
        ));
    }

    /**
     * The geolocation field of the entry's form, or null.
     *
     * @param array<string, mixed>    $entry
     * @param array<int, string|null> &$cache per-form-id memo
     */
    private function geolocationPropertyNameOfEntry(array $entry, array &$cache): ?string
    {
        if (empty($entry['form_id']) || $entry['form_id'] != intval($entry['form_id'])) {
            return null;
        }

        return $this->geolocationPropertyName((int)$entry['form_id'], $cache);
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
