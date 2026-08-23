<?php

namespace YesWiki\Import\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\ListManager;

class ImporterManager
{
    protected ParameterBagInterface $params;
    protected ContainerInterface $services;
    protected EntryManager $entryManager;
    protected FormManager $formManager;
    protected ListManager $listManager;

    public function __construct(
        ParameterBagInterface $params,
        ContainerInterface $services,
        EntryManager $entryManager,
        FormManager $formManager,
        ListManager $listManager
    ) {
        $this->params = $params;
        $this->services = $services;
        $this->entryManager = $entryManager;
        $this->formManager = $formManager;
        $this->listManager = $listManager;
    }

    /**
     * Every importer this wiki has, core's and any extension's, as [short name => class].
     *
     * @return array<string, class-string<Importer>>
     */
    public function getAvailableImporters(): array
    {
        if (!$this->services instanceof Container) {
            return [];
        }
        $importers = [];
        foreach ($this->services->getServiceIds() as $serviceId) {
            if (!is_subclass_of($serviceId, Importer::class)) {
                continue;
            }
            $parts = explode('\\', $serviceId);
            $shortName = substr((string)end($parts), 0, -strlen('Importer'));
            if ($shortName === '') {
                continue;
            }
            $importers[$shortName] = $serviceId;
        }

        return $importers;
    }

    private function findImporterClass(string $importer, string $source): ?Importer
    {
        $available = $this->getAvailableImporters();
        if (!empty($available[$importer])) {
            $className = $available[$importer];
        }
        if (!empty($className) && class_exists($className, false)) {
            /** @var Importer $importerInstance */
            $importerInstance = new $className(
                $source,
                $this->params,
                $this->services,
                $this->entryManager,
                $this,
                $this->formManager,
                $this->listManager
            );

            return $importerInstance;
        }

        return null;
    }

    /**
     * The config fields every importer gets on top of the ones it declares itself, so that a
     * setting meaningful for any source (when to sync it) doesn't have to be repeated in each
     * importer's getAdminFields().
     *
     * @return array<string, array<string, mixed>>
     */
    public static function commonAdminFields(): array
    {
        return [
            'syncOnMaintenance' => [
                'type' => 'checkbox',
                'required' => false,
                'label' => 'IMPORTER_FIELD_SYNCONMAINTENANCE',
                'help' => 'IMPORTER_FIELD_SYNCONMAINTENANCE_HELP',
            ],
            'syncIntervalInMin' => [
                'type' => 'number',
                'required' => false,
                'label' => 'IMPORTER_FIELD_SYNCINTERVALINMIN',
                'help' => 'IMPORTER_FIELD_SYNCINTERVALINMIN_HELP',
            ],
        ];
    }

    /**
     * All the admin fields of $importer: its own, plus the common ones.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getAdminFieldsFor(string $importer): array
    {
        $className = $this->getAvailableImporters()[$importer] ?? null;
        $ownFields = $className !== null ? $className::getAdminFields() : [];

        return array_merge($ownFields, self::commonAdminFields());
    }

    /**
     * Build a dataSources entry for $importer from the generic "{key}{importer}" input fields declared by that importer's getAdminFields().
     *
     * @param array<string, array<string, array<string, mixed>>> $importerFields
     * @param array<string, mixed>                               $input
     *
     * @return array<string, mixed>
     */
    public function collectSourceOptionsFromInput(string $importer, array $importerFields, array $input): array
    {
        $available = $this->getAvailableImporters();
        $className = $available[$importer] ?? null;
        $sourceOptions = ['importer' => $importer];
        foreach ($importerFields[$importer] ?? [] as $key => $field) {
            $postKey = $key . $importer;
            if (($field['type'] ?? null) === 'checkbox') {
                $value = !empty($input[$postKey]);
            } elseif (!empty($input[$postKey])) {
                $value = $input[$postKey];
            } else {
                continue;
            }
            if ($className !== null) {
                $value = $className::normalizeAdminFieldValue($key, $value);
            }
            if (strpos($key, 'auth_') === 0) {
                $sourceOptions['auth'][substr($key, 5)] = $value;
            } else {
                $sourceOptions[$key] = $value;
            }
        }
        if (!empty($input['formId'])) {
            $sourceOptions['formId'] = $input['formId'];
        }
        if ($className !== null) {
            $sourceOptions = $className::normalizeAdminOptions($sourceOptions);
        }

        return $sourceOptions;
    }

    /**
     * Build the remote/local field lists for the mapping table when $importer is pointed at an existing local form: fields come from the importer's fixed getOwnFields() list, or (e.g.
     *
     * @param array<string, mixed> $sourceOptions
     * @param array<string, mixed> $localForm
     *
     * @return array{remote: list<array{key: string, label: string}>, local: list<array{key: string, label: string}>}|null
     */
    public function getFieldMapping(string $importer, array $sourceOptions, array $localForm): ?array
    {
        $available = $this->getAvailableImporters();
        $className = $available[$importer] ?? null;
        $ownFields = $className !== null ? $className::getOwnFields() : [];
        $remoteFields = !empty($ownFields) ? $ownFields : $this->fetchRemoteFormFields($sourceOptions);
        if (empty($remoteFields)) {
            return null;
        }

        return [
            'remote' => $remoteFields,
            'local' => $this->fieldsAsList($localForm['prepared'] ?? []),
        ];
    }

    /**
     * Log into the remote wiki and fetch its form's fields (key + label), to build the field-mapping table.
     *
     * @param array<string, mixed> $sourceOptions
     *
     * @return list<array{key: string, label: string}>|null
     */
    private function fetchRemoteFormFields(array $sourceOptions): ?array
    {
        if (empty($sourceOptions['url']) || empty($sourceOptions['remoteFormId'])) {
            return null;
        }
        $noSSLCheck = !empty($sourceOptions['noSSLCheck']);

        $loginResponse = $this->curl(
            rtrim($sourceOptions['url'], '/') . '/?api/login',
            ['Content-Type: application/x-www-form-urlencoded'],
            true,
            http_build_query([
                'username' => $sourceOptions['auth']['user'] ?? '',
                'password' => $sourceOptions['auth']['password'] ?? '',
            ]),
            $noSSLCheck,
            true
        );
        preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', (string)$loginResponse, $matches);
        $cookie = implode('; ', $matches[1]);

        $formResponse = $this->curl(
            rtrim($sourceOptions['url'], '/') . '/?api/forms/' . $sourceOptions['remoteFormId'],
            ['Cookie: ' . $cookie],
            false,
            [],
            $noSSLCheck
        );
        $remoteForm = json_decode((string)$formResponse, true);
        if (empty($remoteForm['bn_template'])) {
            return null;
        }

        $templateLines = $this->formManager->parseTemplate($remoteForm['bn_template']);

        return $this->fieldsAsList($this->formManager->prepareData(['template' => $templateLines]));
    }

    /**
     * @param array<mixed> $fields prepared fields (FormManager::prepareData())
     *
     * @return list<array{key: string, label: string}>
     */
    private function fieldsAsList(array $fields): array
    {
        $result = [];
        foreach ($fields as $field) {
            if ($field && !empty($field->getPropertyName())) {
                $result[] = ['key' => $field->getPropertyName(), 'label' => $field->getLabel()];
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $sourceOptions
     */
    public function syncSource(string $source, array $sourceOptions): string
    {
        $startTime = microtime(true);
        try {
            $importer = $this->findImporterClass($sourceOptions['importer'], $source);
            if (!$importer) {
                return 'Importer ' . $sourceOptions['importer'] . ' not found';
            }
            $data = $importer->getData();
            $data = $importer->mapData($data);
            $importer->syncFormModel();
            $importer->syncData($data);
        } catch (\Throwable $th) {
            return $th->getMessage() . ' ' . _t('IMPORTER_ELAPSED_TIME', ['duration' => $this->formatDuration($startTime)]);
        }

        return _t('SOURCE_SUCCESSFULLY_SYNCED', ['source' => $source])
            . ' ' . _t('IMPORTER_ELAPSED_TIME', ['duration' => $this->formatDuration($startTime)]);
    }

    private function formatDuration(float $startTime): string
    {
        return number_format(microtime(true) - $startTime, 2) . 's';
    }

    /**
     * @param list<string>             $headers
     * @param string|array<mixed>|null $postData
     *
     * @return string|false the response body, or false when curl could not run the request
     */
    public function curl(
        string $url,
        array $headers = [],
        bool $isPost = false,
        $postData = null,
        bool $noSSLCheck = false,
        bool $showHeader = false,
        int $timeoutInSec = 10
    ) {
        $ch = curl_init($url);
        if ($ch === false) {
            return false;
        }
        if ($showHeader) {
            curl_setopt($ch, CURLOPT_HEADER, true);
        }
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeoutInSec);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeoutInSec);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, $isPost);
        if ($postData) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        }
        if ($noSSLCheck) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $errors = curl_error($ch);
        if (!empty($errors)) {
            echo 'Erreur de connexion à "' . $url . '" : ' . $errors . "\n";
        }

        return is_bool($response) ? false : $response;
    }

    /**
     * Download $sourceUrl into the wiki's upload directory and return the local file name to store in an entry's file/image field (empty string on failure).
     */
    public function downloadFile(
        string $sourceUrl,
        bool $noSSLCheck = false,
        int $timeoutInSec = 10,
        bool $replaceExisting = false,
        ?string $destFileName = null
    ): string {
        if (empty($sourceUrl)) {
            return '';
        }
        $urlFileName = rawurldecode(basename(parse_url($sourceUrl, PHP_URL_PATH) ?: $sourceUrl));
        $destFile = $this->sanitizeDownloadedFileName($destFileName ?? (sha1($sourceUrl) . '_' . $urlFileName));
        if (empty($destFile)) {
            echo 'Fichier "' . $sourceUrl . '" non téléchargé : nom ou extension de fichier non autorisé.' . "\n";

            return '';
        }
        $destPath = $this->uploadPath() . '/' . $destFile;
        if (file_exists($destPath) && !$replaceExisting) {
            return $destFile;
        }
        $tmpPath = $destPath . '.part';
        $fp = fopen($tmpPath, 'wb');
        if ($fp === false) {
            echo 'Impossible d\'écrire dans "' . $this->uploadPath() . '".' . "\n";

            return '';
        }
        $ch = curl_init($sourceUrl);
        if ($ch === false) {
            fclose($fp);
            @unlink($tmpPath);

            return '';
        }
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeoutInSec);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeoutInSec);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        if ($noSSLCheck) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }
        curl_exec($ch);
        $errors = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        clearstatcache(true, $tmpPath);
        if (!empty($errors) || ($httpCode >= 400) || filesize($tmpPath) === 0) {
            unlink($tmpPath);
            echo 'Téléchargement de "' . $sourceUrl . '" échoué'
                . (!empty($errors) ? ' : ' . $errors : ($httpCode ? ' (code http ' . $httpCode . ')' : '')) . '.' . "\n";

            return '';
        }
        rename($tmpPath, $destPath);
        chmod($destPath, 0755);

        return $destFile;
    }

    private function uploadPath(): string
    {
        $attachConfig = $this->params->has('attach_config') ? $this->params->get('attach_config') : [];
        $attachConfig = is_array($attachConfig) ? $attachConfig : [];
        $path = !empty($attachConfig['upload_path']) ? $attachConfig['upload_path'] : 'files';

        return rtrim((string)$path, '/');
    }

    /**
     * Keep a downloaded file's name usable as a plain file name inside the upload directory: it comes from a remote source, so it must not escape that directory nor land there with an extension the wiki would refuse on a regular upload (a server-side executable one above all).
     */
    private function sanitizeDownloadedFileName(string $fileName): string
    {
        $fileName = basename(str_replace('\\', '/', $fileName));
        $fileName = (string)preg_replace('/[^A-Za-z0-9._-]/', '_', $fileName);
        $fileName = trim($fileName, '.');
        if ($fileName === '' || strlen($fileName) > 200) {
            return '';
        }
        $extension = (string)preg_replace('/_+$/', '', strtolower((string)pathinfo($fileName, PATHINFO_EXTENSION)));
        $forbiddenExts = [
            'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phps', 'phtml', 'phar',
            'cgi', 'pl', 'py', 'sh', 'bash', 'exe', 'com', 'bat', 'htaccess', 'htpasswd',
        ];
        if (in_array($extension, $forbiddenExts, true)) {
            return '';
        }
        $authorizedExts = $this->params->has('authorized-extensions') ? $this->params->get('authorized-extensions') : null;
        if (is_array($authorizedExts) && $extension !== '' && !array_key_exists($extension, $authorizedExts)) {
            return '';
        }

        return $fileName;
    }
}
