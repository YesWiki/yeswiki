<?php

// specification to follow : https://icalendar.org/RFC-Specifications/iCalendar-RFC-5545/
// and : https://icalendar.org/RFC-Specifications/iCalendar-RFC-7986/
// https://icalendar.org/iCalendar-RFC-5545/3-6-1-event-component.html

namespace YesWiki\Bazar\Controller;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;
use YesWiki\Core\YesWikiController;
use \DateTime;

class IcalFormatter extends YesWikiController
{
    protected $params;
    protected $geoJSONFormatter;

    public function __construct(
        ParameterBagInterface $params,
        GeoJSONFormatter $geoJSONFormatter
    ) {
        $this->params = $params;
        $this->geoJSONFormatter = $geoJSONFormatter;
    }

    /**
     * format api response
     * @param array $entries
     * @param string $filename
     * @return Response
     */
    public function apiResponse(array $entries, string $filename = 'calendar'): Response
    {
        // start ob for trigger_error messages
        ob_start();

        $fileData = $this->formatToICAL($entries);
        // stop ob
        $obContent = ob_get_contents();
        ob_end_clean();

        if (empty($fileData)) {
            if (!empty($obContent)) {
                $code = Response::HTTP_INTERNAL_SERVER_ERROR;
                return new Response($obContent, $code);
            } else {
                $code = Response::HTTP_OK;
                return new Response('', $code);
            }
        } else {
            $code = Response::HTTP_OK;
            if (empty($filename)) {
                $filename = 'calendar';
            }
            if (!empty($obContent)) {
                $fileData = "X-COMMENT:".str_replace(["\n","\r"], ['\\n','\\r'], $obContent)."\r\n".$fileData;
            }
            //TODO cut lines in max 75 chars
            $headers = [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Credentials' => 'true',
                'Access-Control-Allow-Headers' => 'X-Requested-With, Location, Slug, Accept, Content-Type',
                'Access-Control-Expose-Headers' => 'Location, Slug, Accept, Content-Type',
                'Access-Control-Allow-Methods' => 'POST, GET, OPTIONS, DELETE, PUT, PATCH',
                'Access-Control-Max-Age' => '86400',
                'Content-Type' => 'text/Calendar' ,
                'Content-Disposition' => 'inline; filename='.$filename.'.ics' ,
            ];
            return new Response($fileData, $code, $headers);
        }
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
            $cache = [];
            foreach ($entriesWithIcal as $id => $extendedEntry) {
                $fileData .= $this->formatEvent($extendedEntry['entry'], $extendedEntry['ical'], $cache);
            }
        }

        if (!empty($fileData)) {
            $fileData = $this->addHeaderAndFooter($fileData);
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
        if (!empty($entry['bf_date_debut_evenement']) && !empty($entry['bf_date_fin_evenement'])) {
            return [
                'startDate' => $entry['bf_date_debut_evenement'],
                'endDate' => $entry['bf_date_fin_evenement'],
            ];
        }
        return [];
    }

    /**
     * add header and footer
     * @param string $fileData
     * @param string $fileData
     */
    private function addHeaderAndFooter(string $fileData):string
    {
        $header = "BEGIN:VCALENDAR\r\n";
        $header .= "VERSION:2.0\r\n";
        $header .= "PRODID:-//".$this->params->get("base_url")
            ."//YesWiki ".$this->params->get("yeswiki_version")
            ." ".$this->params->get("yeswiki_release")."//EN\r\n";
        //TODO add source

        $footer = "END:VCALENDAR\r\n";

        $fileData = $header . $fileData . $footer;
        return $fileData;
    }

    /**
     * get formatted event
     * @param array $entry
     * @param array $icalData
     * @param array &$cache
     * @return string
     */
    private function formatEvent(array $entry, array $icalData, array &$cache): string
    {
        $output = "BEGIN:VEVENT\r\n";
        // TODO use real UUID
        $output .="UUID:".$entry['url']."\r\n";
        $output .="URL:".$entry['url']."\r\n";
        $output .="DTSTART".$this->formatDate($icalData['startDate'])."\r\n";
        $output .="DTEND".$this->formatDate($icalData['endDate'])."\r\n";
        $output .="SUMMARY:".$entry['bf_titre']."\r\n";
        if (!empty($entry['bf_description'])) {
            $output .="DESCRIPTION:".$entry['bf_description']."\r\n";
        }
        $location = '';
        $location .= (!empty($entry['bf_adresse'])) ? $entry['bf_adresse'] .' ' : '';
        $location .= (!empty($entry['bf_code_postal'])) ? $entry['bf_code_postal'] .' ' : '';
        $location .= (!empty($entry['bf_ville'])) ? $entry['bf_ville'] .' ' : '';
        if (!empty($location)) {
            $output .="LOCATION:".$location."\r\n";
        }
        $geo = $this->geoJSONFormatter->getGeoData($entry, $cache);
        if (!empty($geo)) {
            $output .="GEO:".$geo['latitude'].";".$geo['longitude']."\r\n";
        }
        // TODO add image https://icalendar.org/New-Properties-for-iCalendar-RFC-7986/5-10-image-property.html
        // TODO add LAST-MODIFIED https://icalendar.org/New-Properties-for-iCalendar-RFC-7986/5-4-last-modified-property.html
        $output .="END:VEVENT\r\n";

        return $output;
    }

    /**
     * format date
     * @param string $date
     * @return string $formattedDate
     */
    private function formatDate(string $date): string
    {
        $dateObject = new DateTime($date);
        // TODO add 24h to end Date if all day
        // TODO format real localZone and note +2
        $localZone = $dateObject->format('e');
        $localFormattedDate = $dateObject->format('Ymd');
        $localFormattedTime = $dateObject->format('His');

        return ';TZID='.$localZone.':'.$localFormattedDate.'T'.$localFormattedTime;
    }
}
