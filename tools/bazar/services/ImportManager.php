<?php

namespace YesWiki\Bazar\Service;

use YesWiki\Bazar\Field\EnumField;
use YesWiki\Bazar\Field\ImageField;
use YesWiki\Bazar\Field\FileField;
use YesWiki\Bazar\Field\UserField;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Wiki;

class ImportManager
{
    protected $debug;
    protected $entryManager;
    protected $formManager;
    protected $wiki;

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
     * @return array|null csv; null is empty or error
     */
    public function getCSVfromFormId(?string $formId, ?string $keywords = null):?array
    {
        if (!empty($formId)) {
            if ($form = $this->formManager->getOne($formId)) {
                $csv_raw = [];
                
                // get headers
                $headers = $this->getHeaders($form);

                // add header to csv_raw
                $csv_raw[] = array_values(array_merge(
                    ['datetime_create','datetime_latest'],
                    array_map(function ($fieldHeader) {
                        return $fieldHeader['fullHeader'];
                    }, $headers)
                ));

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
            // $newValue = implode(',', $values); // TODO test if this line is enough
            } else {
                $newValue = $value;
            }
        }

        return $newValue ?? null;
    }
}
