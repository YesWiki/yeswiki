<?php

// specification to follow : https://icalendar.org/RFC-Specifications/iCalendar-RFC-5545/
// and : https://icalendar.org/RFC-Specifications/iCalendar-RFC-7986/
// https://icalendar.org/iCalendar-RFC-5545/3-6-1-event-component.html

namespace YesWiki\Bazar\Controller;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;
use YesWiki\Bazar\Field\DateField;
use YesWiki\Bazar\Controller\EntryController;
use YesWiki\Core\Service\Performer;
use YesWiki\Core\YesWikiController;
use \DateInterval;
use \DateTime;
use \DateTimeZone;

class IcalFormatter extends YesWikiController
{
    public const MAX_CHARS_BY_LINE = 74;

    protected $params;
    protected $geoJSONFormatter;
    protected $entryController;
    protected $performer;

    public function __construct(
        ParameterBagInterface $params,
        GeoJSONFormatter $geoJSONFormatter,
        EntryController $entryController,
        Performer $performer
    ) {
        $this->params = $params;
        $this->geoJSONFormatter = $geoJSONFormatter;
        $this->entryController = $entryController;
        $this->performer = $performer;
    }

    /**
     * format api response
     * @param array $entries
     * @param mixed $formId
     * @param array|null $get
     * @param string $filename
     * @return Response
     */
    public function apiResponse(array $entries, $formId = null, ?array $get = null, string $filename = ''): Response
    {
        // start ob for trigger_error messages
        ob_start();

        if (!empty($formId) && is_array($formId)) {
            $formId = $formId[array_key_first($formId)];
        }
        if (is_array($formId) || strval(intval($formId)) !== strval($formId)) {
            $formId = null;
        }
        if (empty($filename)) {
            $filename = (empty($formId)) ? 'calendar' : 'calendar-form-'.$formId;
        }
        if (!empty($get['datefilter'])) {
            $entries = $this->entryController->filterEntriesOnDate($entries, $get['datefilter']);
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
                $comment = $this->splitAtnthChar(self::MAX_CHARS_BY_LINE, "X-COMMENT:".str_replace(["\n","\r"], ['\\n','\\r'], $obContent)."\r\n");
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
        $header .= $this->splitAtnthChar(self::MAX_CHARS_BY_LINE, "PRODID:-//".$this->params->get("base_url")
            ."//YesWiki ".$this->params->get("yeswiki_version")
            ." ".$this->params->get("yeswiki_release")."//EN\r\n");
        if (!empty($formId) && intval($formId) == $formId) {
            $header .= $this->splitAtnthChar(self::MAX_CHARS_BY_LINE, "SOURCE:".$this->wiki->Href('forms/'.$formId.'/entries/ical', 'api')."\r\n");
        } else {
            $header .= $this->splitAtnthChar(self::MAX_CHARS_BY_LINE, "SOURCE:".$this->wiki->Href('entries/ical', 'api')."\r\n");
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
        $output .=$this->chunck_split_except_last("UID:".$entry['url'], self::MAX_CHARS_BY_LINE, "\r\n", " ");
        $output .=$this->chunck_split_except_last("URL:".$entry['url'], self::MAX_CHARS_BY_LINE, "\r\n", " ");
        $output .="DTSTAMP".$this->formatDate('')."\r\n";
        $output .="DTSTART".$this->formatDate($icalData['startDate'])."\r\n";
        $output .="DTEND".$this->formatDate($icalData['endDate'])."\r\n";
        $output .="CREATED".$this->formatDate($entry['date_creation_fiche'])."\r\n";
        $output .="DATE-MOD".$this->formatDate($entry['date_maj_fiche'])."\r\n";
        $output .=$this->splitAtnthChar(self::MAX_CHARS_BY_LINE, "SUMMARY:".$entry['bf_titre']."\r\n");
        $output .=$this->splitAtnthChar(self::MAX_CHARS_BY_LINE, "NAME:".$entry['bf_titre']."\r\n");
        $decription = (!empty($entry['bf_description'])) ?
            $this->renderAndStripTags($entry['bf_description'])."\r\n"
            :'';
        $decription .= "Source: ".$entry['url'];
        $output .=$this->splitAtnthChar(self::MAX_CHARS_BY_LINE, "DESCRIPTION:".str_replace(["\r","\n"], ['\\r','\\n'], $decription)."\r\n");
        $location = '';
        $location .= (!empty($entry['bf_adresse'])) ? $entry['bf_adresse'] .' ' : '';
        $location .= (!empty($entry['bf_code_postal'])) ? $entry['bf_code_postal'] .' ' : '';
        $location .= (!empty($entry['bf_ville'])) ? $entry['bf_ville'] .' ' : '';
        if (!empty($location)) {
            $output .=$this->splitAtnthChar(self::MAX_CHARS_BY_LINE, "LOCATION:".$location."\r\n");
        }
        $geo = $this->geoJSONFormatter->getGeoData($entry, $cache);
        if (!empty($geo)) {
            $output .=$this->splitAtnthChar(self::MAX_CHARS_BY_LINE, "GEO:".$geo['latitude'].";".$geo['longitude']."\r\n");
        }
        if (!empty($entry['imagebf_image'])) {
            $baseUrl = $this->getBaseURL();
            $url = $baseUrl . 'files/' . $entry['imagebf_image'];
            $output .=$this->chunck_split_except_last("IMAGE;VALUE=URI;DISPLAY=BADGE:".$url, self::MAX_CHARS_BY_LINE, "\r\n", " ");
            $output .=$this->chunck_split_except_last("ATTACH:".$url, self::MAX_CHARS_BY_LINE, "\r\n", " "); // duplicate on attach to be compatible with more calendar client
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
     * split at nth char
     * @param int $length
     * @param string $input
     * @return string $output
     */
    private function splitAtnthChar(int $length, string $input):string
    {
        // cut lines at nth char whithout breaking words (except long words)
        $output = wordwrap($input, $length, " \r\n ", false);
        // split now long words
        $output = wordwrap($output, $length, "\r\n ", true);
        // prevent errors when cutting between \r\n
        // replace "\r\n \r\n" to prevent empty lines
        $output = preg_replace("/(?:\\r ?\\r\\n \\n|\\r\\n \\r\\n )/", "\r\n ", $output);
        // remove last " \r\n" to prevent empty lines
        $output = preg_replace("/\\r\\n (?:\\r\\n)?$/", "\r\n", $output);
        return $output;
    }

    private function chunck_split_except_last(string $input, int $length = 76, string $escape = "\r\n", string $additionnalSeparator = " ")
    {
        $output = $this->chunk_split_unicode($input, $length, $escape.$additionnalSeparator);
        return substr($output, 0, -strlen($additionnalSeparator));
    }

    private function chunk_split_unicode(string $input, int $length = 76, string $escape = "\r\n")
    {
        $tmp = array_chunk(
            preg_split("//u", $input, -1, PREG_SPLIT_NO_EMPTY),
            $length
        );
        $str = "";
        foreach ($tmp as $t) {
            $str .= join("", $t) . $escape;
        }
        return $str;
    }

    /**
     * render and strip tags
     * @param string $input
     * @return string $output
     */
    private function renderAndStripTags(string $input):string
    {
        // render description
        $renderedInput = $this->performer->run('wakka', 'formatter', ['text' => $input]) ;
        $cleanedRendered = strip_tags($renderedInput, '<a>');
        // extract links
        $output = preg_replace('/<a.*href="([^"]*)".*>(.*)<\/a>/m', '$2 ($1)', $cleanedRendered);

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
