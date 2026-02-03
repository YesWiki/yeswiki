<?php

use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Bazar\Service\SearchManager;
use YesWiki\Core\YesWikiMigration;

class BazarChangeModelForGeolocation extends YesWikiMigration
{
    protected $aReport;

    public function __construct()
    {
        $this->aReport = [];
    }

    protected function report($pMessage)
    {
        $this->aReport[] = $pMessage;
    }

    private function checkGeometries(&$pGeometries)
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
        } elseif ($pGeometries == '""' || $pGeometries == '[]' || $pGeometries == '"[]"') {
            $vNeedUpdate = true;
            $pGeometries = null;
        }

        return ['isArray' => $vIsArray, 'needUpdate' => $vNeedUpdate];
    }

    private function dumpGeolocation($pLatitude = null, $pLongitude = null, $pGeometries = null, $pGeometriesAsArray = null)
    {
        $vLatitude = (isset($pLatitude) && $pLatitude != '') ? $pLatitude : null;
        $vLongitude = (isset($pLongitude) && $pLongitude != '') ? $pLongitude : null;

        $vGeometries = (isset($pGeometries) && $pGeometries != '""' && $pGeometries != '') ? $pGeometries : null;

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
        // $this->dbService is available
        // $this->wiki is also available, so you can get other services if needed

        $this->report('----------------------');
        $this->report('--- Start ChangeModelForGeolocation migration');

        $vFormManager = $this->wiki->services->get(FormManager::class);
        $vEntryManager = $this->wiki->services->get(EntryManager::class);
        $vSearchManager = $this->wiki->services->get(SearchManager::class);

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
                        $vField[2] = 'Geolocation';

                        $vFormsToProcess[] = $vForm['bn_id_nature'];

                        $vNeedUpdate = true;
                    } elseif ($vField[1] !== 'bf_geolocation') {
                        $this->report('This field may be not OK but will not be handled');
                    } else {
                        $this->report('This field seems to be OK');
                    }

                    $vFormsToProcess[] = $vForm['bn_id_nature'];
                }
            }

            if ($vNeedUpdate) {
                $vUpdatedForms++;

                $this->report('Updating form ' . $vForm['bn_label_nature'] . ' (' . $vForm['bn_id_nature'] . ')...');

                $this->dbService->query('UPDATE ' . $this->dbService->prefixTable('nature') . ' SET bn_template = \'' . $this->dbService->escape($vFormManager->encodeTemplate($vTemplate)) . '\' WHERE bn_id_nature = \'' . $this->dbService->escape($vForm['bn_id_nature']) . '\'');
            }
        }

        $this->report('----------------------');

        $vFormsToProcess = array_unique($vFormsToProcess);

        $this->report('The entries of forms ' . implode(', ', $vFormsToProcess) . ' need to be updated');

        $vFormsToProcess = array_map(function ($pID) use ($vFormManager) {
            return $vFormManager->getOne($pID);
        }, $vFormsToProcess);

        foreach ($vFormsToProcess as $vForm) {
            $this->report('----------------------');

            $this->report('Processing entries of form ' . $vForm['bn_label_nature'] . ' (' . $vForm['bn_id_nature'] . ')...');

            $vEntries = $this->dbService->loadAll('SELECT id, time, body FROM' . $this->dbService->prefixTable('pages') .
                'WHERE tag IN (SELECT resource FROM ' . $this->dbService->prefixTable('triples') .
                'WHERE property="http://outils-reseaux.org/_vocabulary/type" AND value="fiche_bazar")');

            $vEntries = array_filter(
                array_map(
                    function ($vEntry) {
                        $this->report($vEntry['body']);
                        $this->report(json_decode($vEntry['body'], true));

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

                    $vValues = explode('|', $vEntry['body']['carte_google']);
                    $vLatitude = $vValues[0] ?? null;
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

                if (!empty($vEntry['body']['bf_geolocation'])) {
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

                    $this->report('updating entry ' . $vEntry['id'] . ' = ' . $vEntry['body']['id_fiche'] . ' (' . $vEntry['time'] . ')...');

                    $vEntry['body']['bf_geolocation'] = ['latitude' => $vLatitude, 'longitude' => $vLongitude, 'geometries' => $vGeometries];

                    $vBody = json_encode($vEntry['body']);

                    $this->dbService->query('UPDATE ' . $this->dbService->prefixTable('pages') . ' SET body = \'' . $this->dbService->escape(chop($vBody)) . '\' WHERE id = \'' . $this->dbService->escape($vEntry['id']) . '\'');

                    $this->report('bf_geolocation = ' . $this->dumpGeolocation($vEntry['body']['bf_geolocation']['latitude'] ?? null, $vEntry['body']['bf_geolocation']['longitude'] ?? null, $vEntry['body']['bf_geolocation']['geometries'], false));
                } else {
                    $this->report('Entry ' . $vEntry['body']['id_fiche'] . ' (' . $vEntry['time'] . ') doesn\'t need to be updated');
                    $this->report('bf_geolocation = ' . $this->dumpGeolocation($vEntry['body']['bf_geolocation']['latitude'] ?? null, $vEntry['body']['bf_geolocation']['longitude'] ?? null, $vEntry['body']['bf_geolocation']['geometries'], false));
                }
            }
        }

        $this->report($vUpdatedForms . ' updated forms , ' . $vUpdatedEntries . ' updated entries');

        //print_r ($this->aReport);

        //throw new Exception ("done");
    }
}
