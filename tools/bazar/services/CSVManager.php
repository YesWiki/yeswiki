<?php

namespace YesWiki\Bazar\Service;

use YesWiki\Bazar\Field\EnumField;
use YesWiki\Bazar\Field\ImageField;
use YesWiki\Bazar\Field\CheckboxField;
use YesWiki\Bazar\Field\CheckboxEntryField;
use YesWiki\Bazar\Field\FileField;
use YesWiki\Bazar\Field\TagsField;
use YesWiki\Bazar\Field\TextareaField;
use YesWiki\Bazar\Field\UserField;
use YesWiki\Bazar\Field\MapField;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Wiki;

class CSVManager
{
    protected $debug;
    protected $entryManager;
    protected $formManager;
    protected $wiki;
    protected $importdone;
    protected $errormsg;

    /**
     * contructor
     * @param EntryManager $entryManager
     * @param FormManager $formManager
     * @param Wiki $wiki
     */
    public function __construct(
        EntryManager $entryManager,
        FormManager $formManager,
        Wiki $wiki
    ) {
        $this->entryManager = $entryManager;
        $this->formManager = $formManager;
        $this->wiki = $wiki;
        $this->debug = ($this->wiki->GetConfigValue('debug') == 'yes');
        $this->importdone = false ;
        $this->errormsg = [] ;
    }

    /**
     * get headers from a form
     * @param array $form form from which headers shoudl be extracted
     * @return array ['propertyName1' => ['field' => field, 'fullHeader' => 'jjjjk'],
     *                     'propertyName2' => ['field' => field, 'fullHeader' => 'jjjjk']]
     *         null if error
     */
    private function getHeaders(array $form):?array
    {
        $headers = [];
        foreach ($form['prepared'] as $field) {
            $propName = $field->getPropertyName();
            if (!empty($propName)) {
                if ($field instanceof UserField) {
                    // TODO save userField data on one field
                    $fullHeader1 = 'NomWiki';
                    $fullHeader2 = 'Mot de passe';
                    if ($field->isRequired()) {
                        $fullHeader1 .= " *";
                        $fullHeader2 .= " *";
                    }
        
                    $headers['nomwiki'] = [
                        'field' => $field,
                        'fullHeader' => $fullHeader1,
                    ];
                    $headers['mot_de_passe_wikini'] = [
                        'field' => $field,
                        'fullHeader' => $fullHeader2,
                    ];
                } elseif ($field instanceof  MapField) {
                    // TODO save userField data on one field
                    // after refacto MapField
                    $latitudeHeader = $field->getLatitudeField();
                    $longitudeHeader = $field->getLongitudeField();
                    if ($field->isRequired()) {
                        $latitudeHeader .= " *";
                        $longitudeHeader .= " *";
                    }
        
                    $headers[$latitudeHeader] = [
                        'field' => $field,
                        'fullHeader' => $latitudeHeader,
                    ];
                    $headers[$longitudeHeader] = [
                        'field' => $field,
                        'fullHeader' => $longitudeHeader,
                    ];
                } else {

                    // *** standard case ****
                    $fullHeader = $field->getLabel();
                    if (!empty($fullHeader)) {
                        if ($field->isRequired()) {
                            $fullHeader .= " *";
                        }
        
                        $headers[$propName] = [
                            'field' => $field,
                            'fullHeader' => $fullHeader,
                        ];
                    }
                }
            }
        }

        return $headers;
    }

    /**
     * convert array to csv
     * @param array|null $data
     * @return string csv
     */
    public function arrayToCSV(?array $data): ?string
    {
        if (!empty($data)) {
            
            // create a file pointer connected to a tmp file
            $handle = tmpfile();
            $path = stream_get_meta_data($handle)['uri'];

            foreach ($data as $line) {
                // output the column headings
                fputcsv($handle, $line);
            }
            
            // read file
            fseek($handle, 0);
            $csv = (fread($handle, filesize($path)));
            // delete file
            fclose($handle);
        }

        return $csv ?? null;
    }

    /**
     * get CSV of all entries from form
     * @param string|null $formId
     * @param string|null $keywords for EntryManager->search
     * @param bool $fakeMode to create a template
     * @param bool $keysInsteadOfValues to export keys insteadof values
     * @return array|null csv; null is empty or error
     */
    public function getCSVfromFormId(
        ?string $formId,
        ?string $keywords = null,
        bool $fakeMode = false,
        bool $keysInsteadOfValues = false
    ):?array {
        if (!empty($formId)) {
            if ($form = $this->formManager->getOne($formId)) {
                $csv_raw = [];
                
                // get headers
                $headers = $this->getHeaders($form);

                // add header to csv_raw
                $csv_raw[] = array_values(array_merge(
                    $fakeMode ? [] : ['datetime_create','datetime_latest'],
                    array_map(function ($fieldHeader) use ($keysInsteadOfValues) {
                        return $keysInsteadOfValues
                            ? $fieldHeader['field']->getPropertyName()
                            : $fieldHeader['fullHeader'];
                    }, $headers)
                ));

                if (!$fakeMode) {
                    // get lines for each entry
                    $entries = $this->entryManager->search([
                        'formsIds'=>[$formId],
                        'keywords' => $keywords
                        ]);
                    foreach ($entries as $entry) {
                        $csv_line = $this->getCSVLineFromEntry($entry, $headers, $keysInsteadOfValues);
                        if ($csv_line) {
                            $csv_raw[] = $csv_line;
                        }
                    }
                } else {
                    // emulate an 4 empty lines
                    for ($i = 1; $i < 4; ++$i) {
                        $csv_line = $this->getTemplateCSVLine($headers, $i);
                        if ($csv_line) {
                            $csv_raw[] = $csv_line;
                        }
                    }
                }
            }
        }

        return $csv_raw ?? null;
    }

    /**
     * getCSVLineFromEntry
     * @param array $entry
     * @param array $headers from $this->getHeaders
     * @param bool $keysInsteadOfValues to export keys insteadof values
     * @return array|null $entry in csv or null if error
     */
    private function getCSVLineFromEntry(array $entry, array $headers, bool $keysInsteadOfValues = false): ?array
    {
        // line
        $line = [];
        // create date and latest date
        $line[] = date_format(date_create_from_format('Y-m-d H:i:s', $entry['date_creation_fiche']), 'd/m/Y H:i:s');
        $line[] = date_format(date_create_from_format('Y-m-d H:i:s', $entry['date_maj_fiche']), 'd/m/Y H:i:s');

        foreach ($headers as $propertyName => $header) {
            $value = $entry[$propertyName] ?? null ;

            if ($value) {
                if ($propertyName == 'mot_de_passe_wikini') {
                    // secure password
                    $value = md5($value);
                } elseif (($header['field'] instanceof  ImageField) || ($header['field'] instanceof  FileField)) {
                    // ajoute l'URL de base aux images et fichiers
                    $value = $this->wiki->getBaseUrl() . '/' . BAZ_CHEMIN_UPLOAD . $value;
                } elseif ($header['field'] instanceof  EnumField
                    && !($header['field'] instanceof TagsField)
                    && !$keysInsteadOfValues) {
                    $value = $this->getLabelsFromEnumFieldOptions($value, $header['field'], $entry);
                } elseif ($header['field'] instanceof  TextareaField
                    && ($header['field']->getSyntax() == TextareaField::SYNTAX_WIKI)) {
                    // following lines needed to export via <pre> in javascript
                    $value = str_replace('<', htmlentities('<'), $value);
                    $value = str_replace('>', htmlentities('>'), $value);
                }
            }
            if ($header['field'] instanceof  MapField) {
                if (!empty($entry[$header['field']->getPropertyName()])) {
                    $value = $entry[$header['field']->getPropertyName()];
                    if (is_array($value)) {
                        // standard case
                        $latitude = $value[$header['field']->getLatitudeField()] ?? null;
                        $longitude = $value[$header['field']->getLongitudeField()] ?? null;
                    }
                } elseif (!empty($entry['carte_google'])) {
                    // retrocompatibility carte_google
                    $values = explode('|', $entry['carte_google']);
                    $latitude = $values[0] ?? null;
                    $longitude = $values[1] ?? null;
                } else {
                    // compatibility with very old data
                    $latitude = $entry[$header['field']->getLatitudeField()] ?? null;
                    $longitude = $entry[$header['field']->getLongitudeField()] ?? null;
                }
                if (!empty($latitude) && !empty($longitude)) {
                    switch ($propertyName) {
                        case $header['field']->getLatitudeField():
                            $value = $latitude ;
                            break;
                        case $header['field']->getLongitudeField():
                            $value = $longitude ;
                            break;
                        default:
                            break;
                    }
                }
            }

            $line[] = $value ?? '';
        }

        return $line;
    }

    /**
     * getLabelsFromEnumFieldOptions
     * @param mixed $value
     * @param BazarEnumFieldField $field
     * @param array $entry
     * @return mixed array|string|null
     */
    private function getLabelsFromEnumFieldOptions($value, EnumField $field, array $entry)
    {
        // prevent errors when entries are saved with array in values for entry
        // (bug from old doryphore version but it is better not to block export)
        if (is_array($value)) {
            $reasonMessage = 'an array : '.json_encode($value)
                . ', which has been exported to string (not maintained). ';
            $value = implode(',', array_values($value));
        }
        
        if (!is_string($value)) {
            $reasonMessage = 'this : '.json_encode($value)
                    . ', which was replaced by null. ';
            $value = null;
        }
        if ($this->debug && !empty($reasonMessage)) {
            trigger_error('Error when exporting \''.$field->getPropertyName().'\''
            .' from entry \''.($entry['id_fiche'] ?? '<no id_fiche>').'\'.'.
            ' Waiting a string, giving ' . $reasonMessage
            .'You should edit and save this entry to prevent error.');
        }

        if (!empty($value)) {
            $options = $field->getOptions();
            // explode values
            $values = explode(',', $value);
            if (is_array($values)) {
                $values = array_map(function ($tag) use ($options) {
                    return $options[$tag] ?? $tag;
                }, $values);
                $newValue = trim($this->arrayToCSV([$values]));
            } else {
                $newValue = $value;
            }
        }

        return $newValue ?? null;
    }

    /**
     * getTempalteCSVLine
     *
     * @param array $headers from $this->getHeaders
     * @param int $lineNumber
     * @return array|null $entry in csv or null if error
     */
    private function getTemplateCSVLine(array $headers, int $lineNumber): ?array
    {
        // line
        $line = [];
        $columnNumber = 1;

        foreach ($headers as $propertyName => $header) {
            if ($header['field'] instanceof CheckboxField || $header['field'] instanceof CheckboxEntryField) {
                $options = $header['field']->getOptions();
                $nb = min(3, count($options)-1);
                $line[] = trim($this->arrayToCSV([// emulate CSV
                        array_map(function ($index) use ($lineNumber, $columnNumber, $options) {
                            return $options[array_keys($options)[$index]];
                        }, range(0, $nb))
                    ]));
            } elseif ($header['field'] instanceof TagsField) {
                $line[] = '"'.implode(',', array_map(function ($index) use ($lineNumber, $columnNumber) {
                    return 'ligne '.$lineNumber.' - champ '.$columnNumber.' - tag '.$index;
                }, [1,2,3])).'"';
            } elseif ($header['field'] instanceof EnumField) {
                $options = $header['field']->getOptions();
                $index = rand(0, count($options)-1);
                $line[] = trim($this->arrayToCSV([// emulate CSV
                        [//emulate a line
                            'ligne '.$lineNumber.' - champ '.$columnNumber.' - ex: '.
                            $options[array_keys($options)[$index]]
                        ]
                    ]));
            } else {
                $line[] = 'ligne '.$lineNumber.' - champ '.$columnNumber;
            }
            ++$columnNumber;
        }

        return $line;
    }

    /**
     * importEntry
     * @param array $importedEntries
     * @param string $formId
     * @return array|null $createdEntries
     */
    public function importEntry(array $importedEntries, string $formId): ?array
    {
        if (!$this->importdone) {
            // Pour les traitements particulier lors de l import
            $GLOBALS['_BAZAR_']['provenance'] = 'import';
            $createdEntries = [];
            foreach ($importedEntries as $entry) {
                $entry = unserialize(base64_decode($entry));
                $entry = array_map('strval', $entry);

                $entry['antispam'] = 1;
                if (isset($entry['id_fiche'])) {
                    // to prevent errors when several entries with same bf_titre
                    unset($entry['id_fiche']);
                }
                $entry = $this->entryManager->create($formId, $entry);

                if ($entry) {
                    $createdEntries[] = $entry;
                }
            }
            $this->importdone = true;
            return $createdEntries;
        }
        return null;
    }

    /**
     * extract CSV from csv file
     * @param string|null $formId
     * @param array|null [['entry' => $extractedData,'errormsg' => ['error1','error2']],...]
     * @param bool $detectColumnsOnHeaders
     */
    public function extractCSVfromCSVFile(?string $formId, $filesData, bool $detectColumnsOnHeaders = true)
    {
        if (!empty($formId)) {
            if ($form = $this->formManager->getOne($formId)) {
                
                // get headers
                $headers = $this->getHeaders($form);

                // import file
                if (!empty($filesData) && ($filesData['error'] == 0)) {
                    //Check if the file is csv
                    $filename = basename($filesData['name']);
                    $ext = substr($filename, strrpos($filename, '.') + 1);
                    if ($ext == 'csv') {
                        if (($handle = fopen($filesData['tmp_name'], 'r')) !== false) {
                            if (($firstLine = fgetcsv($handle, 0, ',')) !== false) {
                                if ($columnIndexesForPropertyNames =
                                    $this->getColumnIndexesForPropertyNames($firstLine, $headers, $detectColumnsOnHeaders)) {
                                    
                                    // next lines
                                    $extracted = [];
                                    while (($data = fgetcsv($handle, 0, ',')) !== false) {// init errors
                                        $this->errormsg = [] ;
                                        $extractedData = $this->getEntryFromCSVLine($data, $headers, $columnIndexesForPropertyNames, $formId);
                                        $extracted[] = [
                                            'entry' => $extractedData,
                                            'errormsg' => $this->errormsg
                                        ];
                                    }
                                }
                            }
                            fclose($handle);
                            return $extracted ?? null;
                        }
                    }
                }
            }
        }
        return null;
    }

    /**
     * get columnIndexes for propertyNames
     * @param array $firstLine of the CSV from fgetcsv
     * @param array $headers from getHeaders
     * @param bool $detectColumnsOnHeaders
     * @return array|null [$propertyName => $index, ...], null if error
     */
    private function getColumnIndexesForPropertyNames(array $firstLine, array $headers, bool $detectColumnsOnHeaders = false): ?array
    {
        if ($detectColumnsOnHeaders) {
            // init data
            $firstLineIndexed = [];
            foreach ($firstLine as $key => $val) {
                // usefull to preserve index with splice because not possible with numeric keys
                $firstLineIndexed['key_'.$key] = $val;
            }
            $data = [
                'columnIndexes' => [],
                'firstLine' => $firstLineIndexed,
                'headers' => $headers,
                'originalHeadersKeys' => array_keys($headers)
            ];
            $data = $this->detectDateTimeHeaders($data);
            $data = $this->detectHeadersOnFullHeader($data);
            $data = $this->detectHeadersOnLabels($data);
            $data = $this->detectHeadersOnLabelsWithStar($data);
            $data = $this->detectHeadersOnPropertyName($data);
            $data = $this->detectHeadersModifiedAfterOneDetected($data);
            $columnIndexes = $data['columnIndexes'];
            $columnIndexes = $this->removeDateTimeColumns($columnIndexes);
        } else {
            $index = 0 ;
            // remove date columns if existing
            if ($firstLine[$index] == 'datetime_create') {
                ++$index;
            }
            if ($firstLine[$index] == 'datetime_latest') {
                ++$index;
            }
            // sweep on headers
            $columnIndexes = [];
            foreach ($headers as $propertyName => $header) {
                if (isset($firstLine[$index])) {
                    $columnIndexes[$propertyName] = $index;
                }
                ++$index;
            }
        }
        return !empty($columnIndexes) ? $columnIndexes : null;
    }

    /**
     * splice array from key
     * @param array &$line
     * @param string $key
     */
    private function array_splice_from_key(array &$line, string $key)
    {
        $index = array_search($key, array_keys($line));
        array_splice($line, $index, 1);
    }

    /**
     * get column indexes for datetimes
     * @param array $data
     * @return array
     */
    private function detectDateTimeHeaders(array $data): array
    {
        foreach (['datetime_create','datetime_latest'] as $value) {
            $first_found_key = array_search($value, $data['firstLine'], true);
            if ($first_found_key !== false) {
                $this->array_splice_from_key($data['firstLine'], $first_found_key);
                // update columnindexes
                $data['columnIndexes'][$value] = (int) substr($first_found_key, strlen('key_'));
            }
        }
        return $data;
    }

    /**
     * remove column indexes for datetimes
     * @param array $columns
     * @return array
     */
    private function removeDateTimeColumns(array $columns): array
    {
        foreach (['datetime_create','datetime_latest'] as $value) {
            if (in_array($value, array_keys($columns))) {
                $this->array_splice_from_key($columns, $value);
            }
        }
        return $columns;
    }

    /**
     * get column indexes on condition
     * @param array $data
     * @return array
     */
    private function detectHeaders(array $data, $condition): array
    {
        $foundPropertyNames = [];
        foreach ($data['headers'] as $propertyName => $header) {
            $first_found_key = array_search($condition($propertyName, $header), $data['firstLine'], true);
            if ($first_found_key !== false) {
                // remove from firstLine
                $this->array_splice_from_key($data['firstLine'], $first_found_key);
                // to remove already found headers
                $foundPropertyNames[] = $propertyName;
                // update columnindexes
                $data['columnIndexes'][$propertyName] = (int) substr($first_found_key, strlen('key_'));
            }
        }
        // filter headers
        foreach ($foundPropertyNames as $propertyName) {
            $this->array_splice_from_key($data['headers'], $propertyName);
        };
        return $data;
    }


    /**
     * get column indexes on fullHeaders
     * @param array $data
     * @return array
     */
    private function detectHeadersOnFullHeader(array $data): array
    {
        return $this->detectHeaders($data, function ($propertyName, $header) {
            return $header['fullHeader'];
        });
    }

    /**
     * get column indexes on labels
     * @param array $data
     * @return array
     */
    private function detectHeadersOnLabels(array $data): array
    {
        return $this->detectHeaders($data, function ($propertyName, $header) {
            return $header['field']->getLabel();
        });
    }

    /**
     * get column indexes on labels with stars
     * @param array $data
     * @return array
     */
    private function detectHeadersOnLabelsWithStar(array $data): array
    {
        return $this->detectHeaders($data, function ($propertyName, $header) {
            return $header['field']->getLabel() . ' *';
        });
    }

    /**
     * get column indexes on propertyName
     * @param array $data
     * @return array
     */
    private function detectHeadersOnPropertyName(array $data): array
    {
        return $this->detectHeaders($data, function ($propertyName, $header) {
            return $propertyName;
        });
    }
    /**
     * get column indexes if modified after one detected columns
     * @param array $data
     * @return array
     */
    private function detectHeadersModifiedAfterOneDetected(array $data): array
    {
        // not found indexes
        $notFoundIndexes = array_map(function ($key) {
            return (int)substr($key, strlen('key_'));
        }, array_keys($data['firstLine']));
        // detect modified fields after one detected
        foreach ($notFoundIndexes as $index) {
            $propertyNameForPreviousIndex = array_search($index-1, $data['columnIndexes'], true);
            if ($index == 0 || $propertyNameForPreviousIndex !== false) {
                if ($index == 0 ||$propertyNameForPreviousIndex == 'datetime_latest') {
                    $keyIndexForPreviousPropertyName = -1;
                } else {
                    $keyIndexForPreviousPropertyName = array_search($propertyNameForPreviousIndex, $data['originalHeadersKeys'], true);
                }
                $waitedPropertyName = $data['originalHeadersKeys'][$keyIndexForPreviousPropertyName + 1] ?? null;
                if (in_array($waitedPropertyName, array_keys($data['headers']))) {
                    // remove from firstLine
                    $this->array_splice_from_key($data['firstLine'], 'key_'.$index);
                    // update columnindexes
                    $data['columnIndexes'][$waitedPropertyName] = $index;
                    // remove already found headers
                    $this->array_splice_from_key($data['headers'], $waitedPropertyName);
                }
            }
        }
        return $data;
    }

    /**
     * getEntryFromCSVLine
     * @param array $data array line from CSV file
     * @param array $headers from getHeaders
     * @param array $columnIndexesForPropertyNames from getcolumnIndexesForPropertyNames
     * @param string $formId
     * @return array|null entry
     */
    private function getEntryFromCSVLine(array $data, array $headers, array $columnIndexesForPropertyNames, string $formId):?array
    {
        $entry = [];
        foreach ($columnIndexesForPropertyNames as $propertyName => $index) {
            $field = $headers[$propertyName]['field'];
            if (intval($index) == $index) {
                // standard case
                $value = $this->getValueFromData($data, $index);
                if (!empty($value)) {
                    if ($field instanceof EnumField
                        && !($field instanceof TagsField)) {
                        // for tags not needed to get keys because these are the same
                        // and do not filter on existing tags but allow alls tags
                        $value = $this->extractValueFromEnumFieldData($value, $field);
                    } elseif ($field instanceof ImageField) {
                        // traitement des images (doivent être présentes dans le dossier files du wiki)
                        $value = $this->extractValueFromImageFieldData($value, $field);
                    } elseif ($field instanceof FileField) {
                        // traitement des images (doivent être présentes dans le dossier files du wiki)
                        $value = $this->extractValueFromFileFieldData($value, $field);
                    } elseif ($field instanceof  TextareaField
                        && ($field->getSyntax() == TextareaField::SYNTAX_WIKI)) {
                        $value = str_replace(htmlentities('>'), '>', $value);
                        $value = str_replace(htmlentities('<'), '<', $value);
                    }
                    $entry[$propertyName] = $value;
                }
            }
        }

        // append entry's data
        if (!empty($entry['bf_titre'])) {
            $entry['id_fiche'] = genere_nom_wiki($entry['bf_titre']);
            $entry['id_typeannonce'] = $formId;
            $entry['date_creation_fiche'] = date('Y-m-d H:i:s', time());
            $entry['date_maj_fiche'] = date('Y-m-d H:i:s', time());
            if ($this->wiki->UserIsAdmin()) {
                $entry['statut_fiche'] = 1;
            } else {
                $entry['statut_fiche'] = $this->wiki->config['BAZ_ETAT_VALIDATION'];
            }
        } else {
            $this->errormsg[] = 'Empty $entry[\'bf_titre\'] in '.get_class($this).', line '.__LINE__;
            return null ;
        }
        return !empty($entry) ? $entry : null;
    }

    /**
     * extract value from data
     * @param array $data array line from CSV file
     * @param int $index
     * @return mixed value
     */
    private function getValueFromData(array $data, int $index)
    {
        if (isset($data[$index])) {
            $value = $data[$index];
            $value = str_replace(
                array(
                    '&sbquo;', '&fnof;', '&bdquo;',
                    '&hellip;', '&dagger;', '&Dagger;',
                    '&circ;', '&permil;', '&Scaron;',
                    '&lsaquo;', '&OElig;', '&lsquo;',
                    '&rsquo;', '&ldquo;', '&rdquo;',
                    '&bull;', '&ndash;', '&mdash;',
                    '&tilde;', '&trade;', '&scaron;',
                    '&rsaquo;', '&oelig;', '&Yuml;',
                ),
                array(chr(130), chr(131), chr(132),
                    chr(133), chr(134), chr(135),
                    chr(136),
                    chr(137), chr(138), chr(139),
                    chr(140), chr(145), chr(146),
                    chr(147),
                    chr(148), chr(149), chr(150),
                    chr(151), chr(152), chr(153),
                    chr(154),
                    chr(155), chr(156), chr(159),
                ),
                $value
            );
        }

        return $value ?? null ;
    }

    /**
     * extractValueFromEnumFieldData
     * @param string $value, CSV saved in value
     * @param EnumField $field
     * @return string $newValue
     */
    private function extractValueFromEnumFieldData(string $value, EnumField $field): string
    {
        // get Options
        $options = $field->getOptions();
        $flippedOptions = [];
        // not usinf array_flip because it takes the last duplicated index, we prefer the first one
        foreach ($options as $key => $val) {
            if (!isset($flippedOptions[$val])) {
                $flippedOptions[$val] = $key;
            }
        }

        // extract CSV
        $values = str_getcsv($value, ',');

        // convert values to index
        $indexes = array_map(function ($option) use ($options, $flippedOptions) {
            if (isset($flippedOptions[$option])) {
                // search if $option is a correct value then take assoiacted index
                return $flippedOptions[$option];
            } elseif (isset($options[$option])) {
                //search if $option is an index
                return $option;
            } else {
                return null;
            }
        }, $values);

        return implode(',', $indexes);
    }

    /**
     * extractValueFromImageFieldData
     * @param string $value, CSV saved in value
     * @param ImageField $field
     * @return string $newValue
     */
    private function extractValueFromImageFieldData(string $value, ImageField $field): string
    {
        // TODO refactor this part if needed because only copied
        $imageorig = trim($value);
        $nomimage = renameUrlToSanitizedFilename($imageorig);
        // test si c'est url vers l'image
        $fileCopied = copyUrlToLocalFile($imageorig, BAZ_CHEMIN_UPLOAD.$nomimage);
        if ($fileCopied) {
            $value = $nomimage;
        } elseif (file_exists(BAZ_CHEMIN_UPLOAD.$imageorig)) {
            if (preg_match('/(gif|jpeg|png|jpg)$/i', $nomimage)) {
                //on enleve les accents sur les noms de fichiers, et les espaces
                $nomimage = preg_replace(
                    '/&([a-z])[a-z]+;/i',
                    '$1',
                    $imageorig
                );
                $nomimage = str_replace(' ', '_', $nomimage);
                $value = $nomimage;
                $chemin_destination = BAZ_CHEMIN_UPLOAD.$nomimage;

                //verification de la presence de ce fichier
                if (!file_exists($chemin_destination)) {
                    rename(
                        BAZ_CHEMIN_UPLOAD.
                        $imageorig,
                        $chemin_destination
                    );
                    chmod($chemin_destination, 0755);
                }
            } else {
                $this->errormsg[] = _t('BAZ_BAD_IMAGE_FILE_EXTENSION');
            }
        } else {
            $this->errormsg[] =
            _t('BAZ_IMAGE_FILE_NOT_FOUND').
            ' : '.$imageorig;
        }

        return $value ;
    }

    /**
     * extractValueFromFileFieldData
     * @param string $value, CSV saved in value
     * @param FileField $field
     * @return string $newValue
     */
    private function extractValueFromFileFieldData(string $value, FileField $field): string
    {
        // TODO refactor this part if needed because only copied

        $fileUrl = trim($value);
        $file = renameUrlToSanitizedFilename($fileUrl);
        // test si c'est url vers l'image
        $fileCopied = copyUrlToLocalFile($fileUrl, BAZ_CHEMIN_UPLOAD.$file);
        if ($fileCopied) {
            $value = $file;
        } elseif (file_exists(BAZ_CHEMIN_UPLOAD.$fileUrl)) {
            $value = $file;
            $chemin_destination = BAZ_CHEMIN_UPLOAD.$file;
            //verification de la presence de ce fichier
            if (!file_exists($chemin_destination)) {
                rename(
                    BAZ_CHEMIN_UPLOAD.$fileUrl,
                    $chemin_destination
                );
                chmod($chemin_destination, 0755);
            }
        } else {
            $this->errormsg[] = _t('BAZ_FILE_NOT_FOUND').' : '.$fileUrl;
        }

        return $value ;
    }
}
