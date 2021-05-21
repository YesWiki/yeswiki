<?php

namespace YesWiki\Bazar\Controller;

use YesWiki\Core\YesWikiController;

class IcalFormatter extends YesWikiController
{
    public function __construct(
    ) {
    }

    /**
     * get data grom entries in GeoJSON format
     * @param array $entries
     * @return string $fileData
     */
    public function formatToICAL(array $entries): string
    {
        $entriesWithIcal = array_filter(array_map(function ($entry) {
            $ical = $this->getICALData($entry);
            if (empty($ical)) {
                return [];
            } else {
                return [
                    'entry' => $entry,
                    'ical' => $ical
                    ];
            }
        }, $entries), function ($entry) {
            return !empty($entry) ;
        });

        $fileData = '';
        if (!empty($entriesWithIcal)) {
            foreach ($entriesWithIcal as $id => $extendedEntry) {
                $entry = $extendedEntry['entry'];
            }
        }

        return $fileData;
    }

    
    /**
     * extract getICALData
     * @param array $entry
     * @return array [''=>,''=>] or []
     */
    private function getICALData(array $entry):array
    {
        return [];
    }
}
