<?php

namespace YesWiki\Content\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Field\CheckboxEntryField;
use YesWiki\Content\Field\CheckboxField;
use YesWiki\Content\Field\EnumField;
use YesWiki\Content\Field\FileField;
use YesWiki\Content\Field\ImageField;
use YesWiki\Content\Field\MapField;
use YesWiki\Content\Field\TagsField;
use YesWiki\Files\Service\AttachedFilePaths;
use YesWiki\Files\Service\RemoteFile;
use YesWiki\Files\Service\Storage;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Search\Service\SearchManager;

/**
 * @phpstan-type CsvHeader array{field: \YesWiki\Content\Field\BazarField, fullHeader: string}
 * @phpstan-type CsvHeaders array<string, CsvHeader>
 * @phpstan-type CsvDetection array{columnIndexes: array<string, int>, firstLine: array<string, mixed>, headers: CsvHeaders, originalHeadersKeys: list<string>}
 */
class CSVManager
{
    protected bool $debug;
    protected EntryManager $entryManager;
    protected FormManager $formManager;
    protected ContainerInterface $container;
    protected bool $importdone;

    /** @var list<string> */
    protected array $errormsg;

    protected UrlFormatter $urlFormatter;
    protected Storage $storage;

    /**
     * contructor.
     */
    public function __construct(
        EntryManager $entryManager,
        FormManager $formManager,
        ContainerInterface $container,
        UrlFormatter $urlFormatter,
        Storage $storage,
    ) {
        $this->storage = $storage;
        $this->urlFormatter = $urlFormatter;
        $this->entryManager = $entryManager;
        $this->formManager = $formManager;
        $this->container = $container;
        $this->debug = (bool)$this->container->get(\YesWiki\Kernel\Service\RuntimeConfig::class)->getValue('debug');
        $this->importdone = false;
        $this->errormsg = [];
    }

    /**
     * A stored `Y-m-d H:i:s` as `d/m/Y H:i:s`, or the raw value when it does not parse.
     */
    private static function formatStoredDate(mixed $stored): string
    {
        $date = is_string($stored) ? date_create_from_format('Y-m-d H:i:s', $stored) : false;

        return $date === false ? (string)$stored : date_format($date, 'd/m/Y H:i:s');
    }

    /**
     * get headers from a form, keyed by property name.
     *
     * Declared `?array` and never returning null, which made every caller's `$headers` nullable
     * and accounted for six baselined argument.type entries (ticket 40).
     *
     * @param array<string, mixed> $form
     *
     * @return CsvHeaders
     */
    private function getHeaders(array $form): array
    {
        $headers = [];
        foreach ($form['prepared'] as $field) {
            $propName = $field->getPropertyName();
            if (!empty($propName)) {
                // *** standard case ****
                $fullHeader = $field->getLabel();
                if (!empty($fullHeader)) {
                    if ($field->isRequired()) {
                        $fullHeader .= ' *';
                    }

                    $headers[$propName] = [
                        'field' => $field,
                        'fullHeader' => $fullHeader,
                    ];
                }
            }
        }

        return $headers;
    }

    /**
     * convert array to csv.
     *
     * Returns '' for no rows rather than null: the docblock always said `string`, the signature
     * said `?string`, and every caller trims the result -- so the nullability was fiction that
     * cost five baselined argument.type entries (ticket 40).
     *
     * @param array<int, array<int|string, mixed>>|null $data
     */
    public function arrayToCSV(?array $data): string
    {
        $csv = '';
        if (!empty($data)) {
            // output up to 50MB is kept in memory, if it becomes bigger it will automatically be written to a temporary file
            $csvResource = fopen('php://temp/maxmemory:' . (50 * 1024 * 1024), 'r+');
            if ($csvResource === false) {
                throw new \Exception('could not open a temporary stream to build the CSV');
            }

            foreach ($data as $line) {
                // output the column headings
                fputcsv($csvResource, $line, ',', '"', '\\');
            }
            rewind($csvResource);

            // read file
            $csv = stream_get_contents($csvResource);

            // close file to release tmp file and leave system to ulink it
            fclose($csvResource);
        }

        return (string)$csv;
    }

    /**
     * get CSV of all entries from form.
     *
     * @param array<string, mixed>      $pParams  parameters for SearchManager::search:
     *                                            "query" (string|array) and "keywords" (string)
     * @param array<string, mixed>|null $pOptions "fakeMode" (bool) to create a template,
     *                                            "keysInsteadOfValues" (bool) to export keys
     * @param mixed                     $pFormID  a form id, or the `locals`/`externals` shape getTheID() reads
     *
     * @return list<list<mixed>>|null the CSV rows, header row first; null when there is no form
     */
    public function getCSVfromFormId(
        $pFormID,
        array $pParams,
        ?array $pOptions = null,
    ): ?array {
        $vBazarListService = $this->container->get(BazarListService::class);

        $vID = $vBazarListService->getTheID($pFormID);
        if (empty($vID)) {
            return null;
        }

        $vForms = $pOptions['forms'] ?? $vBazarListService->getForms(array_merge($pParams, ['id' => $pFormID]));

        $vForm = $vForms[$vID['key']];

        $vFakeMode = isset($pOptions) ? ($pOptions['fakeMode'] ?? false) : false;
        $vKeysInsteadOfValues = isset($pOptions) ? ($pOptions['keysInsteadOfValues'] ?? false) : false;

        if (empty($vForm)) {
            throw new \Exception('Cannot get form');
        }

        $csv_raw = [];

        // get headers
        $headers = $this->getHeaders($vForm);

        // add header to csv_raw
        $csv_raw[] = array_values(array_merge(
            $vFakeMode ? [] : ['datetime_create', 'datetime_latest'],
            $vKeysInsteadOfValues
                ? array_keys($headers)
                : array_map(function ($fieldHeader) {
                    return $fieldHeader['fullHeader'];
                }, $headers),
        ));

        if (!$vFakeMode) {
            $vSearchManager = $this->container->get(SearchManager::class);

            $request = $this->container->get(\YesWiki\Kernel\Service\CurrentRequest::class)->get();
            $vQuery = $vSearchManager->aggregateQueries($pParams['query'] ?? null, $request->query->all());
            // read from an $arg that was never defined here, so the caller's keywords were
            // silently dropped and only the request's ever reached the search (ticket 40)
            $vKeywords = $vSearchManager->aggregateKeywords($pParams['keywords'] ?? null, $request->get('q'), $request->get('keywords'));

            // get lines for each entry
            $vEntries = $vBazarListService->getEntries(array_merge($pParams, [
                'id' => $pFormID,
                'keywords' => $vKeywords,
                'queries' => $vQuery,
                'forms' => $vForms,
            ]));

            foreach ($vEntries as $vEntry) {
                $csv_raw[] = $this->getCSVLineFromEntry($vEntry, $headers, $vKeysInsteadOfValues);
            }
        } else {
            // emulate an 4 empty lines
            for ($i = 1; $i < 4; $i++) {
                $csv_line = $this->getTemplateCSVLine($headers, $i);
                if ($csv_line) {
                    $csv_raw[] = $csv_line;
                }
            }
        }

        return $csv_raw;
    }

    /**
     * getCSVLineFromEntry.
     *
     * @param array<string, mixed> $entry
     * @param CsvHeaders           $headers             from $this->getHeaders
     * @param bool                 $keysInsteadOfValues to export keys insteadof values
     *
     * @return list<mixed> $entry in csv
     */
    private function getCSVLineFromEntry(array $entry, array $headers, bool $keysInsteadOfValues = false): array
    {
        // line
        $line = [];
        // create date and latest date
        // date_create_from_format() returns false for a value it cannot parse, and
        // date_format(false, ...) is a TypeError -- so one entry with a malformed stored date
        // took the whole export down rather than exporting that row oddly (ticket 40)
        $line[] = self::formatStoredDate($entry['created_at'] ?? null);
        $line[] = self::formatStoredDate($entry['updated_at'] ?? null);

        foreach ($headers as $propertyName => $header) {
            $value = $entry[$propertyName] ?? null;

            if ($value) {
                // There used to be a `mot_de_passe_wikini` branch here re-hashing the value with
                // md5() and calling it "secure password". It was unreachable and would have been
                // wrong if it were not: `mot_de_passe_wikini` is a submission artifact stripped
                // before save (EntryManager, ADR-0010), so no stored entry carries it, and the
                // headers are built from form field property names, which it is not. Had it ever
                // fired it would have md5'd an already-hashed value -- a string that verifies
                // against nothing -- and exported a password hash into a CSV.
                if (($header['field'] instanceof ImageField) || ($header['field'] instanceof FileField)) {
                    // ajoute l'URL de base aux images et fichiers
                    $value = $this->storage->url(AttachedFilePaths::UPLOAD_DIR . $value);
                } elseif (
                    $header['field'] instanceof EnumField
                    && !($header['field'] instanceof TagsField)
                    && !$keysInsteadOfValues
                ) {
                    $value = $this->getLabelsFromEnumFieldOptions($value, $header['field'], $entry);
                }
            }
            if ($header['field'] instanceof MapField) {
                $vResult = [];

                if (!empty($entry[$header['field']->getPropertyName()])) {
                    $value = $entry[$header['field']->getPropertyName()];

                    if (is_array($value)) {
                        // standard case
                        $vResult['latitude'] = $value['latitude'] ?? $value['bf_latitude'] ?? null;
                        $vResult['longitude'] = $value['longitude'] ?? $value['bf_longitude'] ?? null;
                        $vResult['geometries'] = $value['geometries'] ?? null;
                    }
                } elseif (!empty($entry['carte_google'])) {
                    // retrocompatibility carte_google
                    $values = explode('|', (string)$entry['carte_google']);
                    $vResult['latitude'] = $values[0];
                    $vResult['longitude'] = $values[1] ?? null;
                } else {
                    // compatibility with very old data
                    $vResult['latitude'] = $entry['bf_latitude'] ?? null;
                    $vResult['longitude'] = $entry['bf_longitude'] ?? null;
                }

                $value = json_encode($vResult);
            }

            $line[] = $value ?? '';
        }

        return $line;
    }

    /**
     * getLabelsFromEnumFieldOptions.
     *
     * @param mixed                $value the stored value, expected to be a comma-separated string
     * @param array<string, mixed> $entry
     *
     * @return string|null
     */
    private function getLabelsFromEnumFieldOptions($value, EnumField $field, array $entry)
    {
        // prevent errors when entries are saved with array in values for entry
        // (bug from old doryphore version but it is better not to block export)
        if (is_array($value)) {
            $reasonMessage = 'an array : ' . json_encode($value)
                . ', which has been exported to string (not maintained). ';
            $value = implode(',', array_values($value));
        }

        if (!is_string($value)) {
            $reasonMessage = 'this : ' . json_encode($value)
                . ', which was replaced by null. ';
            $value = null;
        }
        if ($this->debug && !empty($reasonMessage)) {
            trigger_error('Error when exporting \'' . $field->getPropertyName() . '\''
                . ' from entry \'' . ($entry['tag'] ?? '<no tag>') . '\'.'
                . ' Waiting a string, giving ' . $reasonMessage
                . 'You should edit and save this entry to prevent error.');
        }

        if (!empty($value)) {
            $options = $field->getOptions();
            // explode values
            $values = array_map(function ($tag) use ($options) {
                return $options[$tag] ?? $tag;
            }, explode(',', $value));
            $newValue = trim($this->arrayToCSV([$values]));
        }

        return $newValue ?? null;
    }

    /**
     * getTempalteCSVLine.
     *
     * @param CsvHeaders $headers from $this->getHeaders
     *
     * @return list<string> $entry in csv
     */
    private function getTemplateCSVLine(array $headers, int $lineNumber): array
    {
        // line
        $line = [];
        $columnNumber = 1;

        foreach ($headers as $propertyName => $header) {
            // CheckboxEntryField extends CheckboxField, so it is already covered here
            if ($header['field'] instanceof CheckboxField) {
                $options = $header['field']->getOptions();
                $nb = min(3, count($options));
                if (!empty($options)) {
                    $line[] = trim($this->arrayToCSV([ // emulate CSV
                        array_map(function ($index) use ($options) {
                            return $options[array_keys($options)[$index]];
                        }, range(0, $nb - 1)),
                    ]));
                } else {
                    $line[] = 'ligne ' . $lineNumber . ' - champ ' . $columnNumber;
                }
            } elseif ($header['field'] instanceof TagsField) {
                $line[] = '"' . implode(',', array_map(function ($index) use ($lineNumber, $columnNumber) {
                    return 'ligne ' . $lineNumber . ' - champ ' . $columnNumber . ' - tag ' . $index;
                }, [1, 2, 3])) . '"';
            } elseif ($header['field'] instanceof EnumField) {
                $options = $header['field']->getOptions();
                $index = rand(1, count($options)) - 1;
                $line[] = trim($this->arrayToCSV([ // emulate CSV
                    [ // emulate a line
                        'ligne ' . $lineNumber . ' - champ ' . $columnNumber .
                            (empty($options) ? '' : ' - ex: ' . $options[array_keys($options)[$index]]),
                    ],
                ]));
            } else {
                $line[] = 'ligne ' . $lineNumber . ' - champ ' . $columnNumber;
            }
            $columnNumber++;
        }

        return $line;
    }

    /**
     * importEntry.
     *
     * @param list<string> $importedEntries each a base64-encoded serialized entry
     *
     * @return list<array<string, mixed>>|null $createdEntries, null when an import already ran
     */
    public function importEntry(array $importedEntries, string $formId): ?array
    {
        if ($this->importdone) {
            return null;
        }

        // Scoped to the import rather than left set: a flag that says "this is an import" and
        // is never unset makes every later write in the process an import too (ADR-0024).
        $createdEntries = $this->container->get(ImportContext::class)->during(function () use ($importedEntries, $formId): array {
            $created = [];
            foreach ($importedEntries as $entry) {
                $entry = unserialize(base64_decode($entry), ['allowed_classes' => false]);
                $entry = array_map('strval', $entry);

                $entry['antispam'] = 1;
                if (isset($entry['tag'])) {
                    // to prevent errors when several entries with same bf_titre
                    unset($entry['tag']);
                }
                $entry = $this->entryManager->create($formId, $entry);

                if ($entry) {
                    $created[] = $entry;
                }
            }

            return $created;
        });
        $this->importdone = true;

        return $createdEntries;
    }

    /**
     * extract CSV from csv file.
     *
     * @param mixed                     $pFormId   a form id, or the shape getTheID() reads
     * @param array<string, mixed>|null $filesData one $_FILES entry
     * @param array<string, mixed>|null $pForm     the form the CSV describes
     *
     * @return list<array{entry: array<string, mixed>, errormsg: list<string>}>|null
     */
    public function extractCSVfromCSVFile($pFormId, $filesData, bool $detectColumnsOnHeaders = true, $pForm = null)
    {
        $vBazarListService = $this->container->get(BazarListService::class);

        $vID = $vBazarListService->getTheID($pFormId);

        if (!empty($vID) && $pForm != null) {
            // get headers
            $headers = $this->getHeaders($pForm);

            // import file
            if (!empty($filesData) && ($filesData['error'] == 0)) {
                // Check if the file is csv
                $filename = basename($filesData['name']);
                $ext = substr($filename, strrpos($filename, '.') + 1);
                if ($ext == 'csv') {
                    $handle = $this->storage->readForeignStream($filesData['tmp_name']);
                    if (($firstLine = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
                        if ($columnIndexesForPropertyNames
                            = $this->getColumnIndexesForPropertyNames($firstLine, $headers, $detectColumnsOnHeaders)
                        ) {
                            // next lines
                            $extracted = [];
                            while (($data = fgetcsv($handle, 0, ',', '"', '\\')) !== false) { // init errors
                                $this->errormsg = [];
                                $extractedData = $this->getEntryFromCSVLine($data, $headers, $columnIndexesForPropertyNames, $vID['id']);
                                $extracted[] = [
                                    'entry' => $extractedData,
                                    'errormsg' => $this->errormsg,
                                ];
                            }
                        }
                    }
                    fclose($handle);

                    return $extracted ?? null;
                }
            }
        }

        return null;
    }

    /**
     * get columnIndexes for propertyNames.
     *
     * @param list<string|null> $firstLine of the CSV from fgetcsv
     * @param CsvHeaders        $headers   from getHeaders
     *
     * @return array<string, int>|null [$propertyName => $index, ...], null if error
     */
    private function getColumnIndexesForPropertyNames(array $firstLine, array $headers, bool $detectColumnsOnHeaders = false): ?array
    {
        if ($detectColumnsOnHeaders) {
            // init data
            $firstLineIndexed = [];
            foreach ($firstLine as $key => $val) {
                // usefull to preserve index with splice because not possible with numeric keys
                $firstLineIndexed['key_' . $key] = $val;
            }
            $data = [
                'columnIndexes' => [],
                'firstLine' => $firstLineIndexed,
                'headers' => $headers,
                'originalHeadersKeys' => array_keys($headers),
            ];
            $data = $this->detectDateTimeHeaders($data);
            $data = $this->detectHeadersOnFullHeader($data);
            $data = $this->detectHeadersOnLabels($data);
            $data = $this->detectHeadersOnLabelsWithStar($data);
            $data = $this->detectHeadersOnPropertyName($data);
            $data = $this->detectHeadersModifiedAfterOneDetected($data);
            $columnIndexes = $data['columnIndexes'];
        } else {
            $index = 0;
            // sweep on headers
            $columnIndexes = [];
            foreach ($headers as $propertyName => $header) {
                if (isset($firstLine[$index])) {
                    $columnIndexes[$propertyName] = $index;
                }
                $index++;
            }
        }

        return !empty($columnIndexes) ? $columnIndexes : null;
    }

    /**
     * splice array from key.
     *
     * @param array<string, mixed> $line
     */
    private function array_splice_from_key(array &$line, string $key): void
    {
        $index = array_search($key, array_keys($line));
        // array_search() returns false when the key is absent, and array_splice($line, false)
        // is array_splice($line, 0) -- it would drop the FIRST column instead of none (ticket 40)
        if ($index === false) {
            return;
        }
        array_splice($line, $index, 1);
    }

    /**
     * get column indexes for datetimes.
     *
     * @param CsvDetection $data
     *
     * @return CsvDetection
     */
    private function detectDateTimeHeaders(array $data): array
    {
        foreach (['datetime_create', 'datetime_latest'] as $value) {
            $first_found_key = array_search($value, $data['firstLine'], true);
            if ($first_found_key !== false) {
                // the keys of `firstLine` are `key_<n>` strings; array_search() is declared
                // `int|string|false` because it cannot know that
                $first_found_key = (string)$first_found_key;
                $this->array_splice_from_key($data['firstLine'], $first_found_key);
                // update columnindexes
                $data['columnIndexes'][$value] = (int)substr($first_found_key, strlen('key_'));
            }
        }

        return $data;
    }

    /**
     * get column indexes on condition.
     *
     * @param CsvDetection                       $data
     * @param callable(string, CsvHeader): mixed $condition what to look for in the CSV's first line
     *
     * @return CsvDetection
     */
    private function detectHeaders(array $data, $condition): array
    {
        $foundPropertyNames = [];
        foreach ($data['headers'] as $propertyName => $header) {
            $first_found_key = array_search($condition($propertyName, $header), $data['firstLine'], true);
            if ($first_found_key !== false) {
                // `key_<n>` string keys again; see detectHeadersFromFirstLine()
                $first_found_key = (string)$first_found_key;
                // remove from firstLine
                $this->array_splice_from_key($data['firstLine'], $first_found_key);
                // to remove already found headers
                $foundPropertyNames[] = $propertyName;
                // update columnindexes
                $data['columnIndexes'][$propertyName] = (int)substr($first_found_key, strlen('key_'));
            }
        }
        // filter headers
        foreach ($foundPropertyNames as $propertyName) {
            $this->array_splice_from_key($data['headers'], $propertyName);
        }

        return $data;
    }

    /**
     * get column indexes on fullHeaders.
     *
     * @param CsvDetection $data
     *
     * @return CsvDetection
     */
    private function detectHeadersOnFullHeader(array $data): array
    {
        return $this->detectHeaders($data, function ($propertyName, $header) {
            return $header['fullHeader'];
        });
    }

    /**
     * get column indexes on labels.
     *
     * @param CsvDetection $data
     *
     * @return CsvDetection
     */
    private function detectHeadersOnLabels(array $data): array
    {
        return $this->detectHeaders($data, function ($propertyName, $header) {
            return $header['field']->getLabel();
        });
    }

    /**
     * get column indexes on labels with stars.
     *
     * @param CsvDetection $data
     *
     * @return CsvDetection
     */
    private function detectHeadersOnLabelsWithStar(array $data): array
    {
        return $this->detectHeaders($data, function ($propertyName, $header) {
            return $header['field']->getLabel() . ' *';
        });
    }

    /**
     * get column indexes on propertyName.
     *
     * @param CsvDetection $data
     *
     * @return CsvDetection
     */
    private function detectHeadersOnPropertyName(array $data): array
    {
        return $this->detectHeaders($data, function ($propertyName, $header) {
            return $propertyName;
        });
    }

    /**
     * get column indexes if modified after one detected columns.
     *
     * @param CsvDetection $data
     *
     * @return CsvDetection
     */
    private function detectHeadersModifiedAfterOneDetected(array $data): array
    {
        // not found indexes
        $notFoundIndexes = array_map(function ($key) {
            return (int)substr((string)$key, strlen('key_'));
        }, array_keys($data['firstLine']));
        // detect modified fields after one detected
        foreach ($notFoundIndexes as $index) {
            $propertyNameForPreviousIndex = array_search($index - 1, $data['columnIndexes'], true);
            if ($index == 0 || $propertyNameForPreviousIndex !== false) {
                if ($index == 0 || $propertyNameForPreviousIndex == 'datetime_latest') {
                    $keyIndexForPreviousPropertyName = -1;
                } else {
                    // array_search() answers false when the name is absent, and false + 1 is a
                    // TypeError -- treat "not found" as "before the first header" (ticket 40)
                    $found = array_search($propertyNameForPreviousIndex, $data['originalHeadersKeys'], true);
                    $keyIndexForPreviousPropertyName = $found === false ? -1 : $found;
                }
                $waitedPropertyName = $data['originalHeadersKeys'][$keyIndexForPreviousPropertyName + 1] ?? null;
                if ($waitedPropertyName !== null && array_key_exists($waitedPropertyName, $data['headers'])) {
                    // remove from firstLine
                    $this->array_splice_from_key($data['firstLine'], 'key_' . $index);
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
     * getEntryFromCSVLine.
     *
     * @param list<string|null>  $data                          array line from CSV file
     * @param CsvHeaders         $headers                       from getHeaders
     * @param array<string, int> $columnIndexesForPropertyNames from getcolumnIndexesForPropertyNames
     *
     * @return array<string, mixed> entry
     */
    private function getEntryFromCSVLine(array $data, array $headers, array $columnIndexesForPropertyNames, string $formId): array
    {
        $entry = [];
        $skipFields = ['datetime_create', 'datetime_latest'];
        foreach ($columnIndexesForPropertyNames as $propertyName => $index) {
            if (!in_array($propertyName, $skipFields)) {
                $field = $headers[$propertyName]['field'];
            } else {
                $field = ''; // fake entry for skipped fields
            }

            if (intval($index) == $index) {
                // standard case

                $value = $this->getValueFromData($data, $index);
                if (!empty($value)) {
                    if (
                        $field instanceof EnumField
                            && !($field instanceof TagsField)
                    ) {
                        // for tags not needed to get keys because these are the same
                        // and do not filter on existing tags but allow alls tags
                        $value = $this->extractValueFromEnumFieldData($value, $field);
                    } elseif ($field instanceof ImageField) {
                        // traitement des images (doivent être présentes dans le dossier files du wiki)
                        $value = $this->extractValueFromImageFieldData($value, $field);
                    } elseif ($field instanceof FileField) {
                        // traitement des images (doivent être présentes dans le dossier files du wiki)
                        $value = $this->extractValueFromFileFieldData($value, $field);
                    } elseif (in_array($propertyName, ['datetime_latest', 'datetime_create'])) {
                        $datetime = \DateTime::createFromFormat(
                            'd/m/Y H:i:s',
                            $value,
                            new \DateTimeZone($this->container->get(\YesWiki\Kernel\Service\RuntimeConfig::class)['timezone']),
                        );
                        // a column that does not parse as a date leaves the entry without one,
                        // which the created_at/updated_at fallbacks below fill in with now()
                        if ($datetime === false) {
                            continue;
                        }
                        $value = $datetime->getTimestamp();
                    }
                    $entry[$propertyName] = $value;
                }
            }
        }
        // append entry's data -- no tag here: EntryManager's pipeline computes the
        // title from the form's entry_title_template and generates the slug tag
        // (ADR-0010); bf_titre is just a field the template may reference
        $entry['form_id'] = $formId;
        $entry['created_at'] = date('Y-m-d H:i:s', $entry['datetime_create'] ?? time());
        $entry['updated_at'] = date('Y-m-d H:i:s', $entry['datetime_latest'] ?? time());
        if ($this->container->get(AclService::class)->isAdmin()) {
            $entry['status'] = 1;
        } else {
            $entry['status'] = $this->container->get(\YesWiki\Kernel\Service\RuntimeConfig::class)['BAZ_ETAT_VALIDATION'];
        }
        foreach ($skipFields as $field) {
            if (isset($entry[$field])) {
                unset($entry[$field]);
            }
        }

        return $entry;
    }

    /**
     * extract value from data.
     *
     * @param list<string|null> $data array line from CSV file
     *
     * @return mixed value
     */
    private function getValueFromData(array $data, int $index)
    {
        if (isset($data[$index])) {
            $value = $data[$index];
            $value = str_replace(
                [
                    '&sbquo;', '&fnof;', '&bdquo;',
                    '&hellip;', '&dagger;', '&Dagger;',
                    '&circ;', '&permil;', '&Scaron;',
                    '&lsaquo;', '&OElig;', '&lsquo;',
                    '&rsquo;', '&ldquo;', '&rdquo;',
                    '&bull;', '&ndash;', '&mdash;',
                    '&tilde;', '&trade;', '&scaron;',
                    '&rsaquo;', '&oelig;', '&Yuml;',
                ],
                [
                    chr(130), chr(131), chr(132),
                    chr(133), chr(134), chr(135),
                    chr(136),
                    chr(137), chr(138), chr(139),
                    chr(140), chr(145), chr(146),
                    chr(147),
                    chr(148), chr(149), chr(150),
                    chr(151), chr(152), chr(153),
                    chr(154),
                    chr(155), chr(156), chr(159),
                ],
                $value,
            );
        }

        return $value ?? null;
    }

    /**
     * extractValueFromEnumFieldData.
     *
     * @param string $value, CSV saved in value
     *
     * @return string $newValue
     */
    private function extractValueFromEnumFieldData(string $value, EnumField $field): string
    {
        // get Options
        $options = array_map('trim', $field->getOptions());
        $flippedOptions = [];
        // not using array_flip because it takes the last duplicated index, we prefer the first one
        foreach ($options as $key => $val) {
            $key = trim((string)$key);
            $val = trim($val);

            if (!isset($flippedOptions[$val])) {
                $flippedOptions[$val] = $key;
            }
        }

        // extract CSV and check if multiple values are present : they should be quoted
        if (preg_match('/"[^"]+"/', $value)) {
            $values = str_getcsv($value, ',', '"', '\\');
        } else {
            $values = [$value];
        }

        // convert values to index
        $indexes = array_map(function ($option) use ($options, $flippedOptions) {
            $option = trim((string)$option);
            if (isset($flippedOptions[$option])) {
                // search if $option is a correct value then take assoiacted index
                return $flippedOptions[$option];
            } elseif (isset($options[$option])) {
                // search if $option is an index
                return $option;
            }

            return null;
        }, $values);

        return implode(',', $indexes);
    }

    /**
     * extractValueFromImageFieldData.
     *
     * @param string $value, CSV saved in value
     *
     * @return string $newValue
     */
    private function extractValueFromImageFieldData(string $value, ImageField $field): string
    {
        // TODO refactor this part if needed because only copied
        $imageorig = trim($value);
        $nomimage = RemoteFile::filenameFor($imageorig);

        // reject the download outright if the destination extension is not an authorized image extension
        // (renameUrlToSanitizedFilename only strips path/traversal characters, not the extension)
        $imageExtPreg = $this->container->get(ParameterBagInterface::class)->get('attach_config')['ext_images'];
        if (!preg_match("/({$imageExtPreg})$/i", $nomimage)) {
            $this->errormsg[] = _t('BAZ_BAD_IMAGE_FILE_EXTENSION');

            return $value;
        }

        // test si c'est url vers l'image
        $fileCopied = RemoteFile::download($imageorig, AttachedFilePaths::UPLOAD_DIR . $nomimage);
        if ($fileCopied) {
            $value = $nomimage;
        } elseif ($this->storage->exists(AttachedFilePaths::UPLOAD_DIR . $imageorig)) {
            if (preg_match('/(gif|jpeg|png|jpg)$/i', $nomimage)) {
                // on enleve les accents sur les noms de fichiers, et les espaces
                $nomimage = preg_replace(
                    '/&([a-z])[a-z]+;/i',
                    '$1',
                    $imageorig,
                );
                $nomimage = str_replace(' ', '_', (string)$nomimage);
                $value = $nomimage;
                $chemin_destination = AttachedFilePaths::UPLOAD_DIR . $nomimage;

                // verification de la presence de ce fichier
                if (!$this->storage->exists($chemin_destination)) {
                    $this->storage->move(AttachedFilePaths::UPLOAD_DIR . $imageorig, $chemin_destination);
                }
            } else {
                $this->errormsg[] = _t('BAZ_BAD_IMAGE_FILE_EXTENSION');
            }
        } else {
            $this->errormsg[]
                = _t('BAZ_IMAGE_FILE_NOT_FOUND')
                . ' : ' . $imageorig;
        }

        return $value;
    }

    /**
     * extractValueFromFileFieldData.
     *
     * @param string $value, CSV saved in value
     *
     * @return string $newValue
     */
    private function extractValueFromFileFieldData(string $value, FileField $field): string
    {
        $fileUrl = trim($value);
        $file = RemoteFile::filenameFor($fileUrl);

        // reject the download outright if the destination extension is not in the upload allowlist
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $authorizedExtensions = array_keys($this->container->get(ParameterBagInterface::class)->get('authorized-extensions'));
        if ($extension === '' || !in_array($extension, $authorizedExtensions, true)) {
            $this->errormsg[] = _t('BAZ_NOT_AUTHORIZED_FILE');

            return $value;
        }

        // test si c'est url vers l'image
        $fileCopied = RemoteFile::download($fileUrl, AttachedFilePaths::UPLOAD_DIR . $file);
        if ($fileCopied) {
            $value = $file;
        } elseif ($this->storage->exists(AttachedFilePaths::UPLOAD_DIR . $fileUrl)) {
            $value = $file;
            $chemin_destination = AttachedFilePaths::UPLOAD_DIR . $file;
            // verification de la presence de ce fichier
            if (!$this->storage->exists($chemin_destination)) {
                $this->storage->move(AttachedFilePaths::UPLOAD_DIR . $fileUrl, $chemin_destination);
            }
        } else {
            $this->errormsg[] = _t('BAZ_FILE_NOT_FOUND') . ' : ' . $fileUrl;
        }

        return $value;
    }

    /**
     * convert CSV raw to string to display in <pre>.
     *
     * @param list<list<mixed>>|null $data
     *
     * @return string $csvToDisplay
     */
    public function arrayToCSVToDisplay(?array $data): ?string
    {
        // format file
        $csv = $this->arrayToCSV($data);

        // replace '<' and '> by html entities to prevent error in <pre> displaying
        $csvToDisplay = str_replace('<', htmlentities('<'), $csv);
        $csvToDisplay = str_replace('>', htmlentities('>'), $csvToDisplay);

        return $csvToDisplay;
    }

    /**
     * @param mixed $pFormID a form id, or the shape getTheID() returns
     */
    public function buildExportFilename($pFormID): string
    {
        $vFilename = 'export-fiche-';

        if (is_array($pFormID)) {
            $vFilename .= $pFormID['key'];
        } else {
            $vFilename .= $pFormID;
        }

        $vFilename .= '.csv';

        return $vFilename;
    }

    /**
     * send CSV file or archive.
     *
     * @params $pFormIDs : forms ids
     * @params <array> $pParams for search. ex : [ "query" => ..., "keywords" => ..., "field" => ..., "order" => ... ]
     *
     * @param mixed                $pFormIDs forms ids
     * @param array<string, mixed> $pParams  for search. ex : [ "query" => ..., "keywords" => ..., "field" => ..., "order" => ... ]
     */
    public function sendCsvOrZip($pFormIDs, array $pParams, string $zipFileName = 'yeswiki-csv-exports.zip'): void
    {
        $vBazarListService = $this->container->get(BazarListService::class);

        $vFormIDs = $vBazarListService->getIDs($pFormIDs);

        $csvFiles = [];

        foreach ($vFormIDs['locals'] as $vFormID) {
            $vFilename = $this->buildExportFilename($vFormID);

            $csvFiles[$vFilename] = $this->arrayToCSV(
                $this->getCSVfromFormId(['locals' => [$vFormID], 'externals' => []], $pParams),
            );
        }

        $fileCount = count($csvFiles);

        if ($fileCount === 0) {
            exit('Error: No file data was provided.');
        }

        if ($fileCount === 1) {
            $fileName = key($csvFiles);
            $csvContent = reset($csvFiles);

            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Content-Length: ' . strlen($csvContent));
            header('Connection: close');

            echo $csvContent;
            exit;
        }

        // more than one file left: the two counts above both exit
        if (!class_exists('ZipArchive')) {
            exit('Error: The ZipArchive PHP extension is not installed or enabled.');
        }

        $archive = $this->storage->withTemporaryFile('zip', function (string $tempZipFile) use ($csvFiles): string {
            $zip = new \ZipArchive();
            if ($zip->open($tempZipFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                exit('Error: Cannot create ZIP archive.');
            }
            foreach ($csvFiles as $filename => $csvString) {
                $zip->addFromString($filename, $csvString);
            }
            $zip->close();

            return $this->storage->readForeign($tempZipFile);
        });

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zipFileName . '"');
        header('Content-Length: ' . strlen($archive));
        header('Connection: close');

        echo $archive;
        exit;
    }
}
