<?php

// specification to follow : https://icalendar.org/RFC-Specifications/iCalendar-RFC-5545/
// and : https://icalendar.org/RFC-Specifications/iCalendar-RFC-7986/
// https://icalendar.org/iCalendar-RFC-5545/3-6-1-event-component.html

namespace YesWiki\Bazar\Controller;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;
use YesWiki\Bazar\Field\DateField;
use YesWiki\Core\YesWikiController;
use \DateInterval;
use \DateTime;
use \DateTimeZone;

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
     * @param mixed $formId
     * @param string $filename
     * @return Response
     */
    public function apiResponse(array $entries, $formId = null, string $filename = ''): Response
    {
        // start ob for trigger_error messages
        ob_start();

        if (!empty($formId) && is_array($formId)) {
            $formId = $formId[array_key_first($formId)];
        }
        if (intval($formId) != $formId) {
            $formId = null;
        }
        if (empty($filename)) {
            $filename = (empty($formId)) ? 'calendar' : 'calendar-form-'.$formId;
        }
        $fileData = $this->formatToICAL($entries, $formId);
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
                $comment = $this->splitAt74thChar("X-COMMENT:".str_replace(["\n","\r"], ['\\n','\\r'], $obContent)."\r\n");
                $fileData = str_replace("BEGIN:VCALENDAR\r\n", "BEGIN:VCALENDAR\r\n".$comment, $fileData);
            }
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
     * @param mixed $formId
     * @return string $fileData
     */
    public function formatToICAL(array $entries, $formId = null): string
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
            $fileData = $this->addHeaderAndFooter($fileData, $formId);
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
            $endData = $entry['bf_date_fin_evenement'];
            // 24 h for end date if all day
            if (isset($entry['bf_date_fin_evenement_allday']) && $entry['bf_date_fin_evenement_allday'] == "1") {
                $endData = (new DateTime($entry['bf_date_fin_evenement']))->add(new DateInterval('P1D'))->format('Y-m-d H:i:s');
            }
            return [
                'startDate' => $entry['bf_date_debut_evenement'],
                'endDate' => $endData,
            ];
        }
        return [];
    }

    /**
     * add header and footer
     * @param string $fileData
     * @param mixed $formId
     * @return string $fileData
     */
    private function addHeaderAndFooter(string $fileData, $formId = null):string
    {
        $header = "BEGIN:VCALENDAR\r\n";
        $header .= "VERSION:2.0\r\n";
        $header .= $this->splitAt74thChar("PRODID:-//".$this->params->get("base_url")
            ."//YesWiki ".$this->params->get("yeswiki_version")
            ." ".$this->params->get("yeswiki_release")."//EN\r\n");
        if (!empty($formId) && intval($formId) == $formId) {
            $header .= $this->splitAt74thChar("SOURCE:".$this->wiki->Href('forms/'.$formId.'/entries/ical', 'api')."\r\n");
        } else {
            $header .= $this->splitAt74thChar("SOURCE:".$this->wiki->Href('entries/ical', 'api')."\r\n");
        }

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
        // TODO use real UID with random hex followed by @base URL
        $output .="UID:".$entry['url']."\r\n";
        $output .="URL:".$entry['url']."\r\n";
        $output .="DTSTAMP".$this->formatDate('')."\r\n";
        $output .="DTSTART".$this->formatDate($icalData['startDate'])."\r\n";
        $output .="DTEND".$this->formatDate($icalData['endDate'])."\r\n";
        $output .="CREATED".$this->formatDate($entry['date_creation_fiche'])."\r\n";
        $output .="DATE-MOD".$this->formatDate($entry['date_maj_fiche'])."\r\n";
        $output .=$this->splitAt74thChar("SUMMARY:".$entry['bf_titre']."\r\n");
        $output .=$this->splitAt74thChar("NAME:".$entry['bf_titre']."\r\n");
        if (!empty($entry['bf_description'])) {
            // $output .=$this->splitAt74thChar("DESCRIPTION;ENCODING=BASE64:".str_replace(["\r","\n"], ["=0D",'=0A'], $entry['bf_description'])."\r\n");
            // $output .=$this->splitAt74thChar("DESCRIPTION;ENCODING=BASE64:".base64_encode($entry['bf_description'])."\r\n");
            $output .=$this->splitAt74thChar("DESCRIPTION:".str_replace(["\r","\n"], ['\\r','\\n'], $entry['bf_description'])."\r\n");
        }
        $location = '';
        $location .= (!empty($entry['bf_adresse'])) ? $entry['bf_adresse'] .' ' : '';
        $location .= (!empty($entry['bf_code_postal'])) ? $entry['bf_code_postal'] .' ' : '';
        $location .= (!empty($entry['bf_ville'])) ? $entry['bf_ville'] .' ' : '';
        if (!empty($location)) {
            $output .=$this->splitAt74thChar("LOCATION:".$location."\r\n");
        }
        $geo = $this->geoJSONFormatter->getGeoData($entry, $cache);
        if (!empty($geo)) {
            $output .=$this->splitAt74thChar("GEO:".$geo['latitude'].";".$geo['longitude']."\r\n");
        }
        if (!empty($entry['imagebf_image'])) {
            $baseUrl = $this->getBaseURL();
            $url = $baseUrl . 'files/' . $entry['imagebf_image'];
            $output .=$this->splitAt74thChar("IMAGE;VALUE=URI;DISPLAY=BADGE:".$url."\r\n");
        }
        // image https://icalendar.org/New-Properties-for-iCalendar-RFC-7986/5-10-image-property.html
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
        if (empty($date)) {
            $date = null;
        }
        $dateObject = new DateTime($date);
        $dateObject->setTimezone(new DateTimeZone('UTC'));
        $localFormattedDate = $dateObject->format('Ymd');
        $localFormattedTime = $dateObject->format('His');

        return ':'.$localFormattedDate.'T'.$localFormattedTime.'Z';
    }

    /**
     * split at 75th char
     * @param string $input
     * @return string $output
     */
    private function splitAt74thChar(string $input):string
    {
        $output = chunk_split($input, 74, "\r\n ");
        $output = substr($output, 0, -strlen("\r\n "));
        if (substr($output, -strlen("\r\n \r\n")) == "\r\n \r\n") {
            $output = substr($output, 0, -strlen(" \r\n"));
        }
        return $output;
    }

    /** test if form is ICAL
     * @param array $form
     * @return bool
     */
    public function isICALForm(?array $form = null):bool
    {
        if (empty($form['prepared'] ?? null)) {
            return false;
        }
        $filteredFields = array_values(array_map(function ($field) {
            return $field->getPropertyName();
        }, array_filter($form['prepared'], function ($field) {
            return ($field instanceof DateField);
        })));
        return !empty($filteredFields)
            && in_array('bf_date_debut_evenement', $filteredFields)
            && in_array('bf_date_fin_evenement', $filteredFields);
    }

    /** get base Url
     * @return string
     */
    private function getBaseURL():string
    {
        $baseUrl = $this->params->get('base_url');
        if (substr($baseUrl, -1) == "?") {
            $baseUrl = substr($baseUrl, 0, -1);
        }
        return $baseUrl;
    }
}
