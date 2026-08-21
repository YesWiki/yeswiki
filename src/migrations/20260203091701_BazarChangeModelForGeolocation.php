<?php

use YesWiki\Content\Entity\PageType;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Search\Service\SearchManager;

class BazarChangeModelForGeolocation extends YesWikiMigration
{
    /** @var list<string> what this migration did, in the order it did it */
    protected array $aReport = [];

    protected function report(string $pMessage): void
    {
        $this->aReport[] = $pMessage;
    }

    /**
     * @param mixed $pGeometries normalised in place to a JSON string, or null when empty
     *
     * @return array{isArray: bool, needUpdate: bool}
     */
    private function checkGeometries(&$pGeometries): array
    {
        $vIsArray = false;
        $vNeedUpdate = false;

        if (is_array($pGeometries)) {
            $vIsArray = true;
            $vNeedUpdate = true;

            if (count(array_keys($pGeometries)) > 0) {
                $pGeometries = json_encode($pGeometries);
            } else {
                $pGeometries = null;
            }
        } elseif ($pGeometries == '""' || $pGeometries == '\'\'' || $pGeometries == '[]' || $pGeometries == '"[]"' || $pGeometries == '\'[]\'') {
            $vNeedUpdate = true;
            $pGeometries = null;
        }

        return ['isArray' => $vIsArray, 'needUpdate' => $vNeedUpdate];
    }

    private function dumpGeolocation(mixed $pLatitude = null, mixed $pLongitude = null, mixed $pGeometries = null, mixed $pGeometriesAsArray = null): string
    {
        $vLatitude = (isset($pLatitude) && $pLatitude != '') ? $pLatitude : null;
        $vLongitude = (isset($pLongitude) && $pLongitude != '') ? $pLongitude : null;

        $vGeometries = (isset($pGeometries) && $pGeometries != '""' && $pGeometries != '\'\'' && $pGeometries != '') ? $pGeometries : null;

        $vResult = '';

        if (isset($vLatitude) || isset($vLongitude)) {
            $vResult = '[' . ($vLatitude ?? 'undefined') . ', ' . ($vLongitude ?? 'undefined') . '] ';
        }

        if (isset($vGeometries)) {
            $vResult .= 'with geometries ' . ($pGeometriesAsArray ? 'as array ' : 'as string ') . $vGeometries;
        }

        if ($vResult == '') {
            $vResult = 'empty geolocation';
        }

        return $vResult;
    }

    public function run()
    {
        $this->report('----------------------');
        $this->report('--- Start ChangeModelForGeolocation migration');

        $vFormManager = $this->getService(FormManager::class);
        $vEntryManager = $this->getService(EntryManager::class);
        $vSearchManager = $this->getService(SearchManager::class);

        $vForms = $vFormManager->getAll();

        $this->report('Found ' . count($vForms) . ' forms :');

        $this->report('----------------------');

        $this->report('--- Processing forms');

        $vFormsToProcess = [];

        $vUpdatedForms = 0;
        $vUpdatedEntries = 0;

        foreach ($vForms as &$vForm) {
            $this->report('Processing form ' . $vForm['bn_label_nature'] . ' (' . $vForm['bn_id_nature'] . ')...');

            $vTemplate = &$vForm['template'];

            $vNeedUpdate = false;

            foreach ($vTemplate as &$vField) {
                if ($vField[0] == 'map') {
                    $this->report('MapField Found (param 1, param2) : ' . $vField[1] . ', ' . $vField[2]);

                    if ($vField[1] == 'bf_latitude' && $vField[2] == 'bf_longitude') {
                        $this->report('This field is NOT OK and needs to be updated');

                        $vField[1] = 'bf_geolocation';
                        $vField[2] = _t('BAZ_FORM_EDIT_GEO_LABEL');

                        $vFormsToProcess[] = $vForm['bn_id_nature'];

                        $vNeedUpdate = true;
                    } elseif ($vField[1] !== 'bf_geolocation') {
                        $this->report('This field may be not OK but will not be handled');
                    } else {
                        $this->report('This field seems to be OK');
                    }

                    $vFormsToProcess[] = $vForm['bn_id_nature'];

                    if (trim((string)($vField[6] ?? '')) != '') {
                        $vAutocompleteFieldnames =
                        is_string($vField[6])
                        ? $vField[6]
                        : (
                            is_array($vField[6])
                            ? implode('|', $vField[6])
                            : ''
                        );

                        $vAutocompleteOther = array_map('trim', explode('|', $vAutocompleteFieldnames));

                        if (count($vAutocompleteOther) == 6) {
                            $vAutocompleteOther[6] = $vAutocompleteOther[5];
                            $vAutocompleteOther[5] = '';

                            $vField[6] = implode('|', $vAutocompleteOther);
                        }
                    }
                }
            }

            if ($vNeedUpdate) {
                $vUpdatedForms++;

                $this->report('Updating form ' . $vForm['bn_label_nature'] . ' (' . $vForm['bn_id_nature'] . ')...');

                $this->dbService->query('UPDATE ' . $this->dbService->prefixTable('nature') . ' SET bn_template = \'' . $this->dbService->escape($vFormManager->encodeTemplate($vTemplate)) . '\' WHERE bn_id_nature = \'' . $this->dbService->escape($vForm['bn_id_nature']) . '\'');
            }

            unset($vField);
        }
        // $vForm is still a reference into $vForms here, and the loop below assigns to it
        unset($vForm, $vTemplate);

        $this->report('----------------------');

        $vFormsToProcess = array_unique($vFormsToProcess);

        $this->report('The entries of forms ' . implode(', ', $vFormsToProcess) . ' need to be updated');

        // getOne() answers null for a form that no longer exists, and every read below assumes
        // a form
        $vFormsToProcess = array_filter(array_map(function ($pID) use ($vFormManager) {
            return $vFormManager->getOne($pID);
        }, $vFormsToProcess));

        foreach ($vFormsToProcess as $vForm) {
            $this->report('----------------------');

            $this->report('Processing entries of form ' . $vForm['bn_label_nature'] . ' (' . $vForm['bn_id_nature'] . ')...');

            $vEntries = $this->dbService->loadAll('SELECT id, time, body FROM ' . $this->dbService->prefixTable('pages') .
                ' WHERE ' . $this->dbService->quoteIdentifier('type') . " = '" . PageType::ENTRY . "'");

            $vEntries = array_filter(
                array_map(
                    function ($vEntry) {
                        return ['id' => $vEntry['id'], 'time' => $vEntry['time'], 'body' => json_decode($vEntry['body'], true)];
                    },
                    $vEntries
                ),
                function ($vEntry) use ($vForm) {
                    return $vEntry['body']['id_typeannonce'] == $vForm['bn_id_nature'];
                }
            );

            $this->report('Found ' . count($vEntries) . ' entries');

            $this->report('----------------------');

            foreach ($vEntries as &$vEntry) {
                $this->report('- Processing entry ' . $vEntry['id'] . ' = ' . $vEntry['body']['id_fiche'] . ' (' . $vEntry['time'] . ')...');

                $vNeedUpdate = false;

                $vLatitude = null;
                $vLongitude = null;
                $vGeometries = null;

                if (!empty($vEntry['body']['carte_google'])) {
                    $vNeedUpdate = true;

                    $vValues = explode('|', (string)$vEntry['body']['carte_google']);
                    $vLatitude = $vValues[0];
                    $vLongitude = $vValues[1] ?? null;

                    $this->report('carte_google geolocation found : ' . $this->dumpGeolocation($vLatitude, $vLongitude));
                }

                if (!empty($vEntry['body']['bf_latitude'])) {
                    $vNeedUpdate = true;

                    $vLatitude = $vEntry['body']['bf_latitude'];

                    $this->report('bf_latitude field found : ' . $vLatitude);
                }

                if (!empty($vEntry['body']['bf_longitude'])) {
                    $vNeedUpdate = true;

                    $vLongitude = $vEntry['body']['bf_longitude'];

                    $this->report('bf_longitude field found : ' . $vLongitude);
                }

                if (!empty($vEntry['body']['geolocation'])) {
                    if (!empty($vEntry['body']['geolocation']['bf_latitude'])) {
                        $vLatitude = $vEntry['body']['geolocation']['bf_latitude'];
                    }
                    if (!empty($vEntry['body']['geolocation']['bf_longitude'])) {
                        $vLongitude = $vEntry['body']['geolocation']['bf_longitude'];
                    }
                    if (!empty($vEntry['body']['geolocation']['geometries'])) {
                        $vGeometries = $vEntry['body']['geolocation']['geometries'];
                    }

                    $vCheckResult = $this->checkGeometries($vGeometries);

                    $vIsArray = $vCheckResult['isArray'];
                    $vNeedUpdate = $vNeedUpdate || $vCheckResult['needUpdate'];

                    $this->report('geolocation field found : ' . $this->dumpGeolocation($vLatitude, $vLongitude, $vGeometries, $vIsArray));
                }

                $vBFGeolocation = null;

                if (!empty($vEntry['body']['bf_geolocation'])) {
                    $vBFGeolocation = $vEntry['body']['bf_geolocation'];

                    if (!empty($vEntry['body']['bf_geolocation']['latitude'])) {
                        $vLatitude = $vEntry['body']['bf_geolocation']['latitude'];
                    }
                    if (!empty($vEntry['body']['bf_geolocation']['longitude'])) {
                        $vLongitude = $vEntry['body']['bf_geolocation']['longitude'];
                    }
                    if (!empty($vEntry['body']['bf_geolocation']['geometries'])) {
                        $vGeometries = $vEntry['body']['bf_geolocation']['geometries'];
                    }

                    $vCheckResult = $this->checkGeometries($vGeometries);

                    $vIsArray = $vCheckResult['isArray'];

                    $vNeedUpdate = $vNeedUpdate || $vCheckResult['needUpdate'];

                    $this->report('bf_geolocation field found : ' . $this->dumpGeolocation($vLatitude, $vLongitude, $vGeometries, $vIsArray));
                } else {
                    $vNeedUpdate = true;
                }

                $vLatitude = $vLatitude ?? '';
                $vLongitude = $vLongitude ?? '';
                $vGeometries = $vGeometries ?? '';

                if ($vNeedUpdate) {
                    $vUpdatedEntries++;

                    unset($vEntry['body']['carte_google']);
                    unset($vEntry['body']['bf_latitude']);
                    unset($vEntry['body']['bf_longitude']);
                    unset($vEntry['body']['geolocation']);

                    $vBFGeolocation = $vBFGeolocation ?? [];

                    $vBFGeolocation['latitude'] = $vLatitude;
                    $vBFGeolocation['longitude'] = $vLongitude;
                    $vBFGeolocation['geometries'] = $vGeometries;

                    $this->report('updating entry ' . $vEntry['id'] . ' = ' . $vEntry['body']['id_fiche'] . ' (' . $vEntry['time'] . ')...');

                    $vEntry['body']['bf_geolocation'] = $vBFGeolocation;

                    $vBody = json_encode($vEntry['body']);
                    if ($vBody === false) {
                        // chop(false) is '', so this used to write an empty body over the entry
                        $this->report('Entry ' . $vEntry['id'] . ' could not be re-encoded and was left untouched');

                        continue;
                    }

                    $this->dbService->query('UPDATE ' . $this->dbService->prefixTable('pages') . ' SET body = \'' . $this->dbService->escape(chop($vBody)) . '\' WHERE id = \'' . $this->dbService->escape($vEntry['id']) . '\'');

                    $this->report('bf_geolocation = ' . $this->dumpGeolocation($vEntry['body']['bf_geolocation']['latitude'] ?? null, $vEntry['body']['bf_geolocation']['longitude'] ?? null, $vEntry['body']['bf_geolocation']['geometries'], false));
                } else {
                    $this->report('Entry ' . $vEntry['body']['id_fiche'] . ' (' . $vEntry['time'] . ') doesn\'t need to be updated');
                    $this->report('bf_geolocation = ' . $this->dumpGeolocation($vEntry['body']['bf_geolocation']['latitude'] ?? null, $vEntry['body']['bf_geolocation']['longitude'] ?? null, $vEntry['body']['bf_geolocation']['geometries'], false));
                }
            }

            unset($vEntry);
        }

        $this->report($vUpdatedForms . ' updated forms , ' . $vUpdatedEntries . ' updated entries');
    }
}
