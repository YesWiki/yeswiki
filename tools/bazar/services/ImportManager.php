<?php

namespace YesWiki\Bazar\Service;

use YesWiki\Bazar\Field\EnumField;
use YesWiki\Bazar\Field\ImageField;
use YesWiki\Bazar\Field\FileField;
use YesWiki\Bazar\Field\UserField;
use YesWiki\Bazar\Field\MapField;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Wiki;

class ImportManager
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
     * @return array|null csv; null is empty or error
     */
    public function getCSVfromFormId(?string $formId, ?string $keywords = null, bool $fakeMode = false):?array
    {
        if (!empty($formId)) {
            if ($form = $this->formManager->getOne($formId)) {
                $csv_raw = [];
                
                // get headers
                $headers = $this->getHeaders($form);

                // add header to csv_raw
                $csv_raw[] = array_values(array_merge(
                    $fakeMode ? [] : ['datetime_create','datetime_latest'],
                    array_map(function ($fieldHeader) {
                        return $fieldHeader['fullHeader'];
                    }, $headers)
                ));

                if (!$fakeMode) {
                    // get lines for each entry
                    $entries = $this->entryManager->search([
                        'formsIds'=>[$formId],
                        'keywords' => $keywords
                        ]);
                    foreach ($entries as $entry) {
                        $csv_line = $this->getCSVLineFromEntry($entry, $headers);
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
     * @return array|null $entry in csv or null if error
     */
    private function getCSVLineFromEntry(array $entry, array $headers): ?array
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
                } elseif ($header['field'] instanceof  EnumField) {
                    $value = $this->getLabelsFromEnumFieldOptions($value, $header['field']);
                }
            } elseif ($header['field'] instanceof  MapField
                && !empty($entry[$header['field']->getLatitudeField()])
                && !empty($entry[$header['field']->getLongitudeField()])
                ) {
                    // backward compatibility for MapField
                $value = $entry[$header['field']->getLatitudeField()].'|'.$entry[$header['field']->getLongitudeField()] ;
            }

            $line[] = $value ?? '';
        }

        return $line;
    }

    /**
     * getLabelsFromEnumFieldOptions
     * @param string $value
     * @param BazarEnumFieldField $field
     * @return mixed array|string|null
     */
    private function getLabelsFromEnumFieldOptions(string $value, EnumField $field)
    {
        if (!empty($value)) {
            $options = $field->getOptions();
            // explode values
            $values = explode(',', $value);
            if (is_array($values)) {
                $values = array_map(function ($tag) use ($options) {
                    return $options[$tag] ?? $tag;
                }, $values);
                $newValue = $this->arrayToCSV([$values]);
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
            $line[] = 'ligne '.$lineNumber.' - champ '.$columnNumber;
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
        if (!$this->importdone){
            // Pour les traitements particulier lors de l import
            $GLOBALS['_BAZAR_']['provenance'] = 'import';
            $createdEntries = [];
            foreach ($importedEntries as $entry) {
                $entry = unserialize(base64_decode($entry));
                $entry = array_map('strval', $entry);

                $entry['antispam'] = 1;
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
     */
    public function extractCSVfromCSVFile(?string $formId, $filesData)
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
                                    $this->getColumnIndexesForPropertyNames($firstLine,$headers)) {
                                    
                                    // next lines
                                    $extracted = [];
                                    while (($data = fgetcsv($handle, 0, ',')) !== false) {// init errors 
                                        $this->errormsg = [] ;
                                        $extractedData = $this->getEntryFromCSVLine($data,$headers,$columnIndexesForPropertyNames,$formId);
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
     * @return array|null [$propertyName => $index, ...], null if error
     */
    private function getColumnIndexesForPropertyNames(array $firstLine,array $headers): ?array
    {
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
        foreach ($headers as $propertyName => $header){
            if (isset($firstLine[$index])) {
                // backward compatibility for map
                if (($header['field'] instanceof MapField
                    && $firstLine[$index] == $header['field']->getLatitudeField()
                    && $firstLine[$index+1] == $header['field']->getLongitudeField()
                    ) || (
                        $header['field'] instanceof UserField
                    )){
                    // field on two columns
                    $columnIndexes[$propertyName] = [$index,$index+1];
                    ++$index;
                } else {
                    $columnIndexes[$propertyName] = $index;
                }
            }
            ++$index;
        }
        return !empty($columnIndexes) ? $columnIndexes : null;
    }

    /**
     * getEntryFromCSVLine
     * @param array $data array line from CSV file
     * @param array $headers from getHeaders
     * @param array $columnIndexesForPropertyNames from getcolumnIndexesForPropertyNames
     * @param string $formId
     * @return array|null entry
     */
    private function getEntryFromCSVLine(array $data, array $headers,array $columnIndexesForPropertyNames, string $formId):?array
    {
        $entry = [];
        foreach($columnIndexesForPropertyNames as $propertyName => $index){
            $field = $headers[$propertyName]['field'];
            if($field instanceof MapField) {
                $entry = $this->updateEntryWithMapFieldData($entry,$data,$index,$propertyName,$field);
            } elseif (!is_array($index)){
                // standard case
                $value = $this->getValueFromData($data,$index);
                if (!empty($value)) {
                    if ($field instanceof EnumField){
                        $value = $this->extractValueFromEnumFieldData($value,$field);
                    } elseif ($field instanceof ImageField){
                        // traitement des images (doivent être présentes dans le dossier files du wiki)
                        $value = $this->extractValueFromImageFieldData($value,$field);
                    } elseif ($field instanceof FileField){
                        // traitement des images (doivent être présentes dans le dossier files du wiki)
                        $value = $this->extractValueFromFileFieldData($value,$field);
                    }
                    $entry[$propertyName] = $value;
                }
            } elseif ($field instanceof UserField){     
                $entry = $this->updateEntryWithUserFieldData($entry,$data,$index,$propertyName,$field);           
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
        }

        return !empty($entry) ? $entry : null;
    }

    /**
     * extract value from data
     * @param array $data array line from CSV file
     * @param int $index
     * @return mixed value
     */
    private function getValueFromData(array $data,int $index)
    {
        if (isset($data[$index])){
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
     * updateEntry with MapField Data
     * @param array $entry before update
     * @param array $data array line from CSV file
     * @param int $index
     * @param string $propertyName
     * @param MapField $field
     * @return array $entry after update
     */
    private function updateEntryWithMapFieldData(array $entry, array $data,int $index, string $propertyName,MapField $field):array
    {
        if (!is_array($index)){
            // standard case for MapField
            $value = $this->getValueFromData($data,$index);
            $values = (empty($value)) ? null : explode('|', $value);
            if (empty($values[0]) || empty($values[1])) {
                $latitude = $values[0];
                $longitude = $values[1];
            }
        } else {
            // retrieve data from two columns
            $latitude = $this->getValueFromData($data,$index[0]);
            $longitude = $this->getValueFromData($data,$index[1]);
        }
        if (!empty($latitude) && !empty($longitude)){
            $entry[$propertyName] = $latitude . '|' . $longitude;
            $entry[$field->getLatitudeField()] = $latitude ;
            $entry[$field->getLongitudeField()] = $longitude;
        }
        return $entry;
    }

    /**
     * updateEntry with UserField Data
     * @param array $entry before update
     * @param array $data array line from CSV file
     * @param int $index
     * @param string $propertyName
     * @param UserField $field
     * @return array $entry after update
     */
    private function updateEntryWithUserFieldData(array $entry, array $data,int $index, string $propertyName,UserField $field):array
    {
        $nomwiki = $this->getValueFromData($data,$index[0]);
        if (!empty($nomwiki)) {
            $entry['nomwiki'] = $nomwiki;
        }
        $passwordMd5 = $this->getValueFromData($data,$index[1]);
        if (!empty($passwordMd5)) {
            $entry['mot_de_passe_wikini'] = $passwordMd5;
        }
        return $entry;
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
        foreach($options as $key => $val){
            if (!isset($flippedOptions[$val])){
                $flippedOptions[$val] = $key;
            }
        }

        // extract CSV
        $values = str_getcsv($value,',');

        // convert values to index
        $indexes = array_map(function ($option) use ($options,$flippedOptions) {
            if (isset($flippedOptions[$option])){
                // search if $option is a correct value then take assoiacted index
                return $flippedOptions[$option];
            } elseif (isset($options[$option])){
                //search if $option is an index
                return $option;
            } else {
                return null;
            }
        },$values);

        return implode(',',$indexes);
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
            $this->error = true;
        }

        return $value ;
    }
}
