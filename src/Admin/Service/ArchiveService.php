<?php

namespace YesWiki\Admin\Service;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Admin\Exception\StopArchiveException;
use YesWiki\Files\Service\LocalFiles;
use YesWiki\Files\Service\Storage;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Kernel\Service\ConsoleService;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\UrlFormatter;

class ArchiveService
{
    // In order to prevent including subwikis or other folders that have nothing
    // to do with the current wiki, specify the folders to includes
    public const FOLDERS_TO_INCLUDE = [
        'actions',
        'custom',
        'docs',
        'files',
        'handlers',
        'javascripts',
        'lang',
        'private',
        'src',
        'styles',
        'templates',
        'tests',
        'extensions',
        'themes',
        'vendor',
    ];

    // Some folders should not be included, like the existing backups
    public const FOLDERS_TO_EXCLUDE = [
        '.git',
        'node_modules',
        'private/backups',
    ];

    public const DEFAULT_PARAMS_TO_ANONYMIZE = [
        'db_host' => '',
        'db_database' => '',
        'db_user' => '',
        'db_password' => '',
        'contact_smtp_host' => '',
        'contact_smtp_user' => '',
        'contact_smtp_pass' => '',
        'api_allowed_keys' => [],
    ];
    public const PARAMS_KEY_IN_WAKKA = 'archive';
    public const KEY_FOR_FOLDERS_TO_INCLUDE = 'foldersToInclude';
    public const KEY_FOR_FOLDERS_TO_EXCLUDE = 'foldersToExclude';
    public const KEY_FOR_HIDE_CONFIG_VALUES = 'hideConfigValues';

    /** Where an archive being made reports on itself. */
    public const PROGRESS_FOLDER = 'cache/archive';
    public const ARCHIVE_SUFFIX = '_archive';
    public const ARCHIVE_ONLY_FILES_SUFFIX = '_archive_only_files';
    public const ARCHIVE_ONLY_DATABASE_SUFFIX = '_archive_only_db';
    public const PRIVATE_FOLDER_NAME_IN_ZIP = 'private/backups';
    public const SQL_FILENAME_IN_PRIVATE_FOLDER_IN_ZIP = 'content.sql';
    public const PRIVATE_FOLDER_README_DEFAULT_CONTENT = "# Description of the usage of folder private/backups\n\n" .
        "This folder is **reserved to backups**.\n\n" .
        "It **MUST NOT** be accessible from the internet.\n\n" .
        " - On Apache server, check that the file `.htaccess` is taken in count.\n" .
        " - On Nginx server or other, configure the server to **deny all** access on this folder\n";

    protected ConfigurationService $configurationService;
    protected ConsoleService $consoleService;
    protected DbService $dbService;
    protected ParameterBagInterface $params;
    protected HibernationService $hibernationService;

    protected UrlFormatter $urlFormatter;
    protected Storage $storage;

    private bool $needsAsync = true;

    public function __construct(
        ConfigurationService $configurationService,
        ConsoleService $consoleService,
        DbService $dbService,
        ParameterBagInterface $params,
        HibernationService $hibernationService,
        UrlFormatter $urlFormatter,
        Storage $storage,
        private readonly LocalFiles $localFiles,
    ) {
        $this->storage = $storage;
        $this->urlFormatter = $urlFormatter;
        $this->configurationService = $configurationService;
        $this->consoleService = $consoleService;
        $this->dbService = $dbService;
        $this->params = $params;
        $this->hibernationService = $hibernationService;
    }

    /**
     * archive data in zip file.
     *
     * @param string|OutputInterface    &$output
     * @param array<array-key, mixed>   $foldersToInclude
     * @param array<array-key, mixed>   $foldersToExclude
     * @param array<string, mixed>|null $hideConfigValuesParams the config keys to anonymize, null for the configured ones
     *
     * @return string the path of the archive, or '' when the run stopped before writing one
     *
     * @throws \Exception
     */
    public function archive(
        &$output,
        bool $savefiles = true,
        bool $savedatabase = true,
        array $foldersToInclude = [],
        array $foldersToExclude = [],
        ?array $hideConfigValuesParams = null,
        string $uid = ''
    ): string {
        $vStatus = $this->getArchivingStatus();

        $inputFile = '';
        $outputFile = '';

        if (!$vStatus['canArchive']) {
            $vMessages = $this->getCannotArchiveDetails($vStatus);

            $this->unsetWikiStatus();
            $this->writeOutput($output, 'STOP', true, $outputFile);

            throw new \Exception(_t('AU_CANNOT_ARCHIVE') . implode(', ', $vMessages));
        }
        $privatePath = $this->getPrivateFolder();

        if (!empty($uid)) {
            $info = $this->getInfoFromFile();
            if (isset($info[$uid])) {
                $inputFile = (string)$info[$uid]['input'];
                $outputFile = (string)$info[$uid]['output'];
            }
        }
        if (!empty($outputFile)) {
            $this->storage->write($outputFile, '');
        }

        $this->storage->write("$privatePath/tmpTestFile000.txt", 'test');
        $error = !$this->localPrivateFolderNotAvailableOnInternet($privatePath, 'tmpTestFile000.txt');
        if ($this->storage->fileExists("$privatePath/tmpTestFile000.txt")) {
            $this->storage->delete("$privatePath/tmpTestFile000.txt");
        }
        if ($error) {
            $this->writeOutput($output, '! Private folder available on the internet', true, $outputFile);
            $this->unsetWikiStatus();
            $this->writeOutput($output, 'STOP', true, $outputFile);

            return '';
        }

        $this->writeOutput($output, '=== Checking free space ===', true, $outputFile);
        $blacklistedRootFolders = $this->generateListRootFolders('black', $foldersToExclude);
        try {
            $this->assertEnoughtSpace($blacklistedRootFolders);
        } catch (\Throwable $th) {
            $this->writeOutput($output, 'There is not enough free space.', true, $outputFile);
            $this->writeOutput($output, "=> {$th->getMessage()}", true, $outputFile);
            $this->unsetWikiStatus();
            $this->writeOutput($output, 'STOP', true, $outputFile);
            throw $th;
        }
        $this->writeOutput($output, 'There is enough free space.', true, $outputFile);

        if ($this->checkIfNeedStop($inputFile)) {
            $this->unsetWikiStatus();
            $this->writeOutput($output, 'STOP', true, $outputFile);

            return '';
        }
        $onlyDb = false;
        // check options and prepare file suffix
        if (!$savefiles && !$savedatabase) {
            throw new \Exception("Invalid options : It is not possible to use 'savefiles = false' and 'savedatabase = false' options in same time.");
        } elseif (!$savefiles) {
            $fileSuffix = self::ARCHIVE_ONLY_DATABASE_SUFFIX;
            $onlyDb = true;
        } elseif (!$savedatabase) {
            $fileSuffix = self::ARCHIVE_ONLY_FILES_SUFFIX;
        } else {
            $fileSuffix = self::ARCHIVE_SUFFIX;
        }

        // prepare location of zip file

        $archiveFileName = (new \DateTime())->format('Y-m-d\\TH-i-s') . "$fileSuffix.zip";
        $location = $privatePath . '/' . $archiveFileName;
        if ($this->storage->fileExists($location)) {
            throw new \Exception('Zip file already existing !');
        }
        if ($this->hibernationService->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }

        try {
            // set wiki status
            $this->setWikiStatus();
            // get SQl
            $sqlContent = $savedatabase ? $this->getSQLContent($privatePath) : '';

            if ($this->checkIfNeedStop($inputFile)) {
                $this->unsetWikiStatus();
                $this->writeOutput($output, 'STOP', true, $outputFile);

                return '';
            }

            $this->writeOutput($output, '=== Creating zip archive ===', true, $outputFile);

            $written = $this->storage->withLocalTarget(
                $location,
                fn (string $local) => $this->createZip($local, $foldersToInclude, $blacklistedRootFolders, $output, $sqlContent, $onlyDb, $hideConfigValuesParams, $inputFile, $outputFile)
            );

            if ($written) {
                $this->writeOutput($output, "Archive \"$location\" successfully created !", true, $outputFile);

                // clean oldest files
                $this->cleanOldestFiles();

                $this->unsetWikiStatus();

                $this->writeOutput($output, 'END', true, $outputFile);
            } else {
                throw new StopArchiveException('Stop archive : not saved !');
            }
        } catch (StopArchiveException $ex) {
            $this->forget($location);
            $this->unsetWikiStatus();
            $this->writeOutput($output, 'STOP', true, $outputFile);

            return '';
        } catch (\Throwable $th) {
            $this->forget($location);
            $this->unsetWikiStatus();
            $this->writeOutput($output, 'STOP', true, $outputFile);

            throw $th;
        }

        return $location;
    }

    /**
     * @param array<string, mixed> $pStatus as getArchivingStatus() answers it
     *
     * @return list<string> one message per reason this wiki cannot be archived right now
     */
    public function getCannotArchiveDetails($pStatus)
    {
        $vMessages = [];

        if ($pStatus['archiving']) {
            $vMessages[] = _t('AU_ALREADY_ARCHIVING');
        }
        if ($pStatus['hibernated']) {
            $vMessages[] = _t('AU_SITE_IS_HIBERNATED');
        }
        if (!$pStatus['privatePathWritable']) {
            $vMessages[] = _t('AU_PRIVATE_PATH_NOT_WRITABLE');
        }
        if (!$pStatus['notAvailableOnTheInternet']) {
            $vMessages[] = _t('AU_PRIVATE_PATH_AVAILABLE_ON_INTERNET');
        }
        if (!(!$pStatus['callAsync'] || $pStatus['canExec'])) {
            $vMessages[] = _t('AU_CANNOT_EXECUTE_BACKUP');
        }
        if (!$pStatus['enoughSpace']) {
            $vMessages[] = _t('AU_NOT_ENOUGHT_SPACE');
        }

        return $vMessages;
    }

    /**
     * Get YesWiki status
     * We must use the configuration service to have the value of wiki_status since it can be modified dynamically
     * and that ParameterBag is more static.
     *
     * @return string : the status
     */
    public function getWikiStatus()
    {
        $vConfig = $this->configurationService->getConfiguration(ConfigurationFileProvider::getConfigFileFromEnv());
        $vConfig->load();

        if (trim($vConfig['wiki_status'] ?? '') == '') {
            return 'running';
        }

        return trim($vConfig['wiki_status']);
    }

    /**
     * Test if YesWiki is read only
     * We must use the configuration service to have the value of wiki_status since it can be modified dynamically
     * and that ParameterBag is more static.
     *
     * @return bool : is read only
     */
    public function isReadOnly()
    {
        return in_array($this->getWikiStatus(), ['hibernate', 'archiving', 'updating']);
    }

    /**
     * check if a recent and valided backup is present.
     */
    /** @param mixed $token the token the update flow was handed back, when it was handed one */
    public function hasValidatedBackup($token): bool
    {
        if (empty($token) || !is_string($token)) {
            return false;
        }

        $privatePath = $this->getPrivateFolder();
        $info = $this->getInfoFromFile();
        $result = ($info[$token]['isForcedUpdate'] ?? false) === true;

        foreach ($info as $uid => $data) {
            if (($data['isForcedUpdate'] ?? false) === true) {
                $this->cleanUID($uid);
            }
        }

        return $result;
    }

    /** Archive without needing to start a background job, for a caller that is already one. */
    public function synchronously(): self
    {
        $this->needsAsync = false;

        return $this;
    }

    /**
     * retrieve the current status to archive.
     *
     * @return array<string, mixed> ['canArchive' => bool,'archiving' => bool, 'hibernated' => bool, 'privatePathWritable' => bool, 'canExec' => bool]
     */
    public function getArchivingStatus(): array
    {
        $archiving = false;
        $hibernated = false;
        $privatePathWritable = true;
        $notAvailableOnTheInternet = true;
        $enoughSpace = true;
        $canExec = false;
        $dB = false;
        $archiveParams = $this->getArchiveParams();
        $callAsync = (isset($archiveParams['call_archive_async']) && is_bool($archiveParams['call_archive_async']))
            ? $archiveParams['call_archive_async']
            : true;
        if ($this->isReadOnly()) {
            switch ($this->getWikiStatus()) {
                case 'archiving':
                    $archiving = true;
                    break;
                case 'hibernate':
                    $hibernated = true;
                    break;
                default:
                    break;
            }
        }
        try {
            $privatePath = $this->getPrivateFolder();
        } catch (\Exception $th) {
            $privatePathWritable = false;
            $privatePath = '';
        }
        if (!empty($privatePath)) {
            if (!$this->canWriteFolder($privatePath)) {
                $privatePathWritable = false;
            } else {
                $tmpFileName = "$privatePath/tmp.txt";

                try {
                    $this->storage->write($tmpFileName, 'test');
                    if ($this->storage->read($tmpFileName) !== 'test') {
                        throw new \Exception('Bad content');
                    }
                    $notAvailableOnTheInternet = $this->localPrivateFolderNotAvailableOnInternet($privatePath, basename($tmpFileName));
                } catch (\Throwable $th) {
                    $privatePathWritable = false;
                } finally {
                    $this->forget($tmpFileName);
                }
            }
        }

        try {
            $results = $this->consoleService->startConsoleSync('helloworld:hello', []);
            if (!empty($results)) {
                $result = $results[array_key_first($results)];
                if (
                    empty($result['stderr']) && !empty($result['stdout'])
                    && preg_match("/Hello !(?:\r|\n)+/", $result['stdout'])
                ) {
                    $canExec = true;
                }
            }
        } catch (\Throwable $th) {
            $canExec = false;
        }

        if ($canExec) {
            $dB = $this->testDb();
        }
        try {
            $this->assertEnoughtSpace();
        } catch (\Throwable $th) {
            $enoughSpace = false;
        }

        $canArchive = (
            !$archiving
            && !$hibernated
            && $privatePathWritable
            && $notAvailableOnTheInternet
            && (
                !$callAsync
                || $canExec
                || !$this->needsAsync
            )
            && $enoughSpace
        );

        return compact(['canArchive', 'archiving', 'hibernated', 'privatePathWritable', 'canExec', 'callAsync', 'notAvailableOnTheInternet', 'enoughSpace', 'dB']);
    }

    /**
     * get a token to force update.
     *
     * @return string $token
     */
    public function getForcedUpdateToken(): string
    {
        $status = $this->getArchivingStatus();
        $privatePath = $this->getPrivateFolder();
        $uidData = $this->getUID();
        $info = $this->getInfoFromFile();
        $uid = $uidData['uid'];
        if (empty($uid) || !isset($info[$uid])) {
            return '';
        }

        $info[$uid]['isForcedUpdate'] = true;
        $this->setInfoToFile($info);

        return $uid;
    }

    /**
     * start archive async via CLI or directly if sync.
     *
     * @param array<array-key, mixed> $foldersToInclude
     * @param array<array-key, mixed> $foldersToExclude
     *
     * @return string uid
     */
    public function startArchive(
        bool $savefiles = true,
        bool $savedatabase = true,
        array $foldersToInclude = [],
        array $foldersToExclude = [],
        bool $callAsync = true
    ): string {
        $privatePath = $this->getPrivateFolder();
        $uidData = $this->getUID();
        if ($callAsync) {
            $args = [];
            if (!$savefiles) {
                $args[] = '-d';
            }
            if (!$savedatabase) {
                $args[] = '-f';
            }
            if (!empty($foldersToInclude)) {
                $args[] = '-i';
                $args[] = implode(',', $foldersToInclude);
            }
            if (!empty($foldersToExclude)) {
                $args[] = '-x';
                $args[] = implode(',', $foldersToExclude);
            }

            $args[] = '-u';
            $args[] = $uidData['uid'];
            $process = $this->consoleService->startConsoleAsync(
                'core:archive',
                $args
            );
            if (!empty($process)) {
                $this->updatePIDForUID((string)$process->getPid(), $uidData['uid']);

                return $uidData['uid'];
            }
            $this->cleanUID($uidData['uid']);

            return '';
        }
        $output = '';
        $location = $this->archive($output, $savefiles, $savedatabase, $foldersToInclude, $foldersToExclude, null, $uidData['uid']);
        if (empty($location)) {
            $this->cleanUID($uidData['uid']);

            return '';
        }

        return $uidData['uid'];
    }

    /**
     * get the list of archives in a array with information for each one.
     *
     * @return list<array<string, mixed>> newest first
     */
    public function getArchives(): array
    {
        $archives = [];
        $privatePath = $this->getPrivateFolder();
        foreach ($this->storage->files($privatePath) as $path) {
            $filename = basename($path);
            if (preg_match("/^(\d{4})-(\d{2})-(\d{2})T(\d{2})-(\d{2})-(\d{2})_archive(?:_(only_files|only_db))?\.zip$/", $filename, $matches)) {
                list(, $year, $month, $day, $hours, $minutes, $seconds) = $matches;
                $archives[] = [
                    'filename' => $filename,
                    'date' => "$year-$month-{$day}T$hours-$minutes-$seconds",
                    'year' => $year,
                    'month' => $month,
                    'day' => $day,
                    'hours' => $hours,
                    'minutes' => $minutes,
                    'seconds' => $seconds,
                    'type' => $matches[7] ?? 'full',
                    'size' => $this->storage->fileSize("$privatePath/$filename"),
                    'link' => $this->urlFormatter->href('', "api/archives/$filename"),
                ];
            }
        }
        usort($archives, function ($a, $b) {
            return strnatcmp($b['date'], $a['date']);
        });

        return $archives;
    }

    /**
     * get the path to an archive filename.
     *
     * @return string $filepath
     */
    public function getFilePath(string $filename): string
    {
        $privatePath = $this->getPrivateFolder();
        $filename = basename($filename);
        if (substr($filename, -4) != '.zip') {
            return '';
        }
        $filePath = "$privatePath/$filename";

        return $this->storage->fileExists($filePath) ? $filePath : '';
    }

    /**
     * Restore a backup archive (database and/or files).
     *
     * @throws \Exception
     */
    public function restoreArchive(string $filename, bool $restoreFiles = true, bool $restoreDatabase = true): void
    {
        $filePath = $this->getFilePath($filename);
        if (empty($filePath)) {
            throw new \Exception("Archive not found: $filename");
        }

        $this->storage->withLocalCopy($filePath, function (string $local) use ($filename, $restoreFiles, $restoreDatabase): void {
            $this->restoreFromLocalArchive($local, $filename, $restoreFiles, $restoreDatabase);
        });
    }

    /** ZipArchive cannot be handed anything but a real path, so this is what a lease exists for. */
    private function restoreFromLocalArchive(string $filePath, string $filename, bool $restoreFiles, bool $restoreDatabase): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new \Exception("Cannot open archive: $filename");
        }

        try {
            $onlyFiles = str_ends_with($filename, '_archive_only_files.zip');
            $onlyDb = str_ends_with($filename, '_archive_only_db.zip');

            if ($restoreDatabase && !$onlyFiles) {
                $sqlContent = $zip->getFromName(self::PRIVATE_FOLDER_NAME_IN_ZIP . '/' . self::SQL_FILENAME_IN_PRIVATE_FOLDER_IN_ZIP);
                if ($sqlContent === false) {
                    throw new \Exception('SQL file not found in archive');
                }
                $this->restoreDatabase($sqlContent);
            }

            if ($restoreFiles && !$onlyDb) {
                $this->restoreFilesFromZip($zip);
            }
        } finally {
            $zip->close();
        }
    }

    /** Drop the wiki's tables and replay the dump into them (ticket 17: DbService owns the replay). */
    protected function restoreDatabase(string $sqlContent): void
    {
        $this->dbService->restoreStagedFromDump($sqlContent);
    }

    /**
     * Extract wiki files from zip, skipping private/backups/ and wakka.config.php.
     */
    /**
     * Files that say where this wiki is and what it may reach.
     *
     * @return list<string>
     */
    public static function localOnlyFiles(): array
    {
        return [ConfigurationFileProvider::getConfigFileFromEnv(), 'private/.env'];
    }

    /**
     * Configuration keys that say where this wiki is and what it may reach.
     *
     * @return list<string>
     */
    public static function localOnlyKeys(): array
    {
        return [
            'base_url',
            'db_driver',
            'db_host',
            'db_port',
            'db_database',
            'db_user',
            'db_password',
            'db_charset',
            'table_prefix',
        ];
    }

    /**
     * This wiki's configuration with the remote one's taken over it, except what must stay local.
     *
     * @param array<string, mixed> $local  what this wiki has now
     * @param array<string, mixed> $remote what the archive holds
     *
     * @return array<string, mixed>
     */
    public static function mergedSettings(array $local, array $remote): array
    {
        $keep = self::localOnlyKeys();

        foreach ($remote as $key => $value) {
            if (!in_array($key, $keep, true)) {
                $local[$key] = $value;
            }
        }

        return $local;
    }

    public static function isLocalOnly(string $name): bool
    {
        $name = ltrim(str_replace('\\', '/', $name), './');

        foreach (self::localOnlyFiles() as $local) {
            if ($name === ltrim(str_replace('\\', '/', $local), './') || $name === basename($local)) {
                return true;
            }
        }

        return false;
    }

    protected function restoreFilesFromZip(\ZipArchive $zip): void
    {
        $wikiRoot = YESWIKI_INSTANCE_DIR;
        $skipPrefix = self::PRIVATE_FOLDER_NAME_IN_ZIP . '/';

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false) {
                continue;
            }
            if (strpos($name, $skipPrefix) === 0 || self::isLocalOnly($name) || strpos($name, '..') !== false) {
                continue;
            }
            if (str_ends_with($name, '/')) {
                $dir = $wikiRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
                if (!$this->localFiles->isDirectory($dir)) {
                    $this->localFiles->makeDirectory($dir);
                }
                continue;
            }
            $zip->extractTo($wikiRoot, $name);
        }
    }

    /**
     * delete archives.
     *
     * @param array<array-key, mixed> $filesnames
     *
     * @return array<string, bool> $results = ['filename' => bool]
     */
    public function deleteArchives(array $filesnames): array
    {
        $privatePath = $this->getPrivateFolder();
        $filesnames = array_filter($filesnames, 'is_string');
        $filesnames = array_map('basename', $filesnames);
        $results = [
            'main' => true,
        ];
        foreach ($filesnames as $filename) {
            $results[$filename] = (substr($filename, -4) == '.zip') && $this->storage->fileExists("$privatePath/$filename");
            if ($results[$filename]) {
                $results[$filename] = $this->forget("$privatePath/$filename");
            }
            if (!$results[$filename]) {
                $results['main'] = false;
            }
        }

        return $results;
    }

    /**
     * get uid status.
     *
     * @return array<string, mixed> ['found'=> bool,'running' => bool,'finished'=>bool,'output' => string]
     */
    public function getUIDStatus(string $uid, bool $forceStarted = false): array
    {
        $results = [
            'started' => false,
            'running' => false,
            'finished' => false,
            'stopped' => false,
            'output' => '',
        ];
        $privateFolder = $this->getPrivateFolder();
        $info = $this->getInfoFromFile();
        foreach ($info as $infoUid => $infoData) {
            if ($infoUid != $uid) {
                $this->cleanUID($infoUid);
            }
        }
        $info = $this->getInfoFromFile();
        if (!isset($info[$uid])) {
            return $results;
        } elseif (!$forceStarted && empty($info[$uid]['pid'])) {
            $this->cleanUID($uid);
        } else {
            $results['started'] = true;
            list(
                'running' => $running,
                'finished' => $finished,
                'stopped' => $stopped,
                'output' => $output
            ) = $this->getRunningUIDdata($uid, $info[$uid]);
            $results['running'] = $running;
            $results['finished'] = $finished;
            $results['stopped'] = $stopped;
            if (!$running) {
                $output = preg_replace("/(^Archive \\\")(.*)(\\\" successfully created !(?:\s*END)?\s*$)/m", '$1---$3', $output);
            }
            $results['output'] = $output;
            if (!$results['running']) {
                $this->cleanUID($uid);
            }
        }

        return $results;
    }

    /**
     * put data in file to stop archive.
     */
    public function stopArchive(string $uid): bool
    {
        if (empty($uid)) {
            return false;
        }
        $info = $this->getInfoFromFile();
        if (
            !isset($info[$uid])
            || empty($info[$uid]['input'])
            || !$this->storage->fileExists((string)$info[$uid]['input'])
        ) {
            return false;
        }
        $this->storage->write((string)$info[$uid]['input'], 'STOP');

        return true;
    }

    /**
     * check if need to stop archive.
     */
    protected function checkIfNeedStop(string $inputFile = ''): bool
    {
        if (empty($inputFile) || !$this->storage->fileExists($inputFile)) {
            return false;
        }
        $content = $this->storage->read($inputFile);

        if (empty($content)) {
            return false;
        }

        return preg_match('/^STOP.*/', $content) === 1;
    }

    /**
     * Refuse to archive something that is not a wiki, asking each root for what it owns.
     *
     * @throws \Exception
     */
    protected function assertArchivableFrom(string $instanceDir, string $programDir): void
    {
        if (!$this->localFiles->exists($instanceDir . '/index.php')) {
            throw new \Exception("no index.php in \"$instanceDir\": that is not a wiki instance");
        }
        if (!$this->storage->exists(ConfigurationFileProvider::getConfigFileFromEnv())) {
            throw new \Exception('the wiki has no configuration file: nothing to archive');
        }
        foreach (['composer.json', 'composer.lock'] as $manifest) {
            if (!$this->localFiles->exists($programDir . '/' . $manifest)) {
                throw new \Exception("no $manifest in \"$programDir\": that is not a yeswiki program tree");
            }
        }
    }

    /**
     * create the zip file.
     *
     * @param array<array-key, mixed>   $foldersToInclude
     * @param list<string>              $blacklistedRootFolders
     * @param string|OutputInterface    &$output
     * @param array<string, mixed>|null $hideConfigValuesParams
     *
     * @return bool : true on success, false on failure
     */
    protected function createZip(
        string $zipPath,
        array $foldersToInclude,
        array $blacklistedRootFolders,
        &$output,
        string $sqlContent,
        bool $onlyDb = false,
        ?array $hideConfigValuesParams = null,
        string $inputFile = '',
        string $outputFile = ''
    ) {
        $this->assertArchivableFrom(YESWIKI_INSTANCE_DIR, YESWIKI_PROGRAM_DIR);
        $pathToArchive = YESWIKI_INSTANCE_DIR;
        $dirs = [$pathToArchive];
        $dirnamePathLen = strlen($pathToArchive);

        $whitelistedRootFolders = $this->generateListRootFolders('white', $foldersToInclude);

        $zip = new \ZipArchive();

        $vCanceled = false;

        $resource = $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        if ($resource !== true) {
            return false;
        }

        if (method_exists($zip, 'registerCancelCallback')) {
            $zip->registerCancelCallback(function () use ($inputFile, &$vCanceled) {
                $vNeedStop = $this->checkIfNeedStop($inputFile);

                if ($vNeedStop) {
                    $vCanceled = true;

                    return -1;
                }

                return 0;
            });
        }

        if (method_exists($zip, 'registerProgressCallback')) {
            $zip->registerProgressCallback(0.1, function ($r) use (&$output, $outputFile) {
                $this->writeOutput($output, 'Zip file creation : ' . strval(round($r * 100, 0)) . ' %', true, $outputFile);
            });
        }

        if (!$vCanceled && !$onlyDb) {
            $zip->addEmptyDir('cache');

            while (count($dirs)) {
                $dir = current($dirs);
                $dir = (string)preg_replace("/(?:\/|\\\\|([^\/\\\\]))$/", '$1', $dir);
                $baseDirName = (string)preg_replace('/\\\\/', '/', substr($dir, $dirnamePathLen));
                $baseDirName = (string)preg_replace("/^\//", '', $baseDirName);
                if (empty($baseDirName) || $this->shouldIncludeFolder($baseDirName, $whitelistedRootFolders, $blacklistedRootFolders)) {
                    if (!empty($baseDirName)) {
                        $this->writeOutput($output, "Adding folder \"$baseDirName\"", true, $outputFile);
                        $zip->addEmptyDir($baseDirName);
                    }

                    foreach ($this->localFiles->entriesIn($dir) as $file) {
                        $localName = $dir . DIRECTORY_SEPARATOR . $file;
                        $relativeName = (empty($baseDirName) ? '' : "$baseDirName/") . $file;
                        if (empty($baseDirName) && $file == ConfigurationFileProvider::getConfigFileFromEnv()) {
                            $zip->addFromString($relativeName, $this->getWakkaConfigSanitized($whitelistedRootFolders, $blacklistedRootFolders, $hideConfigValuesParams));
                        } elseif ($this->localFiles->isFile($localName)) {
                            $zip->addFile($localName, $relativeName);
                        } elseif ($this->localFiles->isDirectory($localName)) {
                            if ($this->shouldIncludeFolder($relativeName, $whitelistedRootFolders, $blacklistedRootFolders)) {
                                $dirs[] = $dir . DIRECTORY_SEPARATOR . $file;
                            }
                            if ($this->checkIfNeedStop($inputFile)) {
                                $this->writeOutput($output, '== The archive processus need to be stopped ==', true, $outputFile);
                                $vCanceled = true;
                                break;
                            }
                        }
                    }
                }

                if ($vCanceled) {
                    break;
                }

                array_shift($dirs);
            }
        }

        if (!$vCanceled && !empty($sqlContent)) {
            $this->writeOutput($output, 'Adding SQL file', true, $outputFile);
            $zip->addEmptyDir(self::PRIVATE_FOLDER_NAME_IN_ZIP);
            $zip->addFromString(
                self::PRIVATE_FOLDER_NAME_IN_ZIP . '/' . self::SQL_FILENAME_IN_PRIVATE_FOLDER_IN_ZIP,
                $sqlContent
            );
            $this->writeOutput($output, 'Adding .htaccess file in folder ' . self::PRIVATE_FOLDER_NAME_IN_ZIP, true, $outputFile);

            $zip->addFromString(
                self::PRIVATE_FOLDER_NAME_IN_ZIP . '/.htaccess',
                "DENY FROM ALL\n"
            );

            $zip->addFromString(
                self::PRIVATE_FOLDER_NAME_IN_ZIP . '/README.md',
                self::PRIVATE_FOLDER_README_DEFAULT_CONTENT
            );
        }

        $vClosed = false;
        $vError = false;

        if (!$vCanceled) {
            $this->writeOutput($output, 'Generating zip file', true, $outputFile);

            $vResult = $zip->close();

            $vClosed = true;

            if ($vResult) {
                $this->writeOutput($output, 'Archive was created successfully', true, $outputFile);

                return true;
            } elseif (!$vCanceled) {
                $this->writeOutput($output, 'There was a problem closing archive', true, $outputFile);
            }
        }

        if ($vCanceled) {
            $this->writeOutput($output, 'Archive creation canceled', true, $outputFile);
        }

        if (!$vClosed) {
            $zip->unchangeAll();

            if ($zip->close()) {
                $this->writeOutput($output, 'Archive was closed successfully', true, $outputFile);
            } else {
                $this->writeOutput($output, 'There was a problem closing archive', true, $outputFile);
            }
        }

        $this->localFiles->remove($zipPath);

        return false;
    }

    /**
     * test if folder should be included.
     *
     * @param list<string> $whitelistedRootFolders
     * @param list<string> $blacklistedRootFolders
     */
    protected function shouldIncludeFolder(
        string $relativeFolderName,
        array $whitelistedRootFolders,
        array $blacklistedRootFolders
    ): bool {
        if (
            in_array($relativeFolderName, $blacklistedRootFolders)
            || in_array(basename($relativeFolderName), $blacklistedRootFolders)
        ) {
            return false;
        }

        return count(array_filter($whitelistedRootFolders, function ($folder) use ($relativeFolderName) {
            return strpos($relativeFolderName, $folder) === 0;
        })) > 0;
    }

    /**
     * @param array<array-key, mixed> $list
     *
     * @return list<string> the relative paths of $list, without duplicates
     */
    private function sanitizeFileList(array $list): array
    {
        $outputList = [];
        foreach ($list as $filePath) {
            if (is_string($filePath)) {
                $filePath = trim($filePath);
                if (!empty($filePath) && !preg_match('/^(?:\\/|\\\\)|[A-Za-z]:\\\\|(?:\\/|\\\\|^)\\.\\.(?:\\/|\\\\|$)/', $filePath)) {
                    $formattedFilePath = (string)preg_replace("/(\/|\\\\)$/", '', $filePath);
                    if (!in_array($formattedFilePath, $outputList)) {
                        $outputList[] = $formattedFilePath;
                    }
                }
            }
        }

        return $outputList;
    }

    /** Where a wiki's archives are, which is not a setting: one place, Protected, and in the bucket when there is one. */
    private function getPrivateFolder(): string
    {
        return self::PRIVATE_FOLDER_NAME_IN_ZIP;
    }

    /**
     * @return array<string, mixed>
     */
    public function getArchiveParams(): array
    {
        if ($this->params->has(self::PARAMS_KEY_IN_WAKKA)) {
            $archiveParams = $this->params->get(self::PARAMS_KEY_IN_WAKKA);
        }

        return (empty($archiveParams) || !is_array($archiveParams)) ? [] : $archiveParams;
    }

    private function canWriteFolder(string $path): bool
    {
        return $this->storage->isWritable($path);
    }

    /** Add a line to a progress log, which is the one thing Storage has no verb for. */
    private function append(string $path, string $text): bool
    {
        try {
            $kept = $this->storage->fileExists($path) ? $this->storage->read($path) : '';
            $this->storage->write($path, $kept . $text);
        } catch (\Throwable $th) {
            return false;
        }

        return true;
    }

    /** Remove a file if it is there, and say whether it is gone. */
    private function forget(string $path): bool
    {
        try {
            if ($this->storage->fileExists($path)) {
                $this->storage->delete($path);
            }
        } catch (\Throwable $th) {
            return false;
        }

        return true;
    }

    /** Whether the backups are out of a reader's reach. */
    private function localPrivateFolderNotAvailableOnInternet(string $localPath, string $testFileName): bool
    {
        if ($this->storage->isRemote($localPath)) {
            return true;
        }
        $isAbsolutePath = (
            in_array(substr($localPath, 0, 1), ['/', DIRECTORY_SEPARATOR])
            || (
                DIRECTORY_SEPARATOR == '\\'

                    && preg_match('/^[A-Za-z]:.*$/', $localPath)
            )
        );
        $basePath = YESWIKI_INSTANCE_DIR;
        $realLocalPath = $isAbsolutePath
            ? $this->localFiles->realPath($localPath)
            : $this->localFiles->realPath($basePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $localPath));

        if ($realLocalPath === false) {
            return true;
        }
        if (!str_starts_with($realLocalPath, $basePath)) {
            return true;
        }
        if (!$this->storage->fileExists("$localPath/$testFileName")) {
            throw new \Exception("\"$localPath/$testFileName\" must exist for tests !");
        }
        $url = (string)preg_replace("/\??$/", '', $this->configString('base_url'));
        $url .= str_replace(DIRECTORY_SEPARATOR, '/', "$localPath/$testFileName");
        $ct = stream_context_set_default([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
            'http' => [
                'method' => 'HEAD',
            ],
        ]);
        $headers = @get_headers($url, true, $ct);

        $status = $headers === false ? null : ($headers[0] ?? null);

        return !is_string($status) || !strstr($status, '200 OK');
    }

    /**
     * /** A configuration value as a string: the parameter bag types every value as a union. */
    private function configString(string $key): string
    {
        $value = $this->params->get($key);

        return is_scalar($value) ? (string)$value : '';
    }

    /**
     * write text to the output.
     *
     * @param string|OutputInterface &$output
     */
    private function writeOutput(&$output, string $text, bool $newline = true, string $outputFile = ''): void
    {
        if (!empty($outputFile) && $this->storage->fileExists($outputFile)) {
            if (!$this->append($outputFile, $text . ($newline ? "\n" : ''))) {
                throw new \Exception('Cannot write to output file. Please check file system access rights');
            }
        }
        if ($output instanceof OutputInterface) {
            $output->write($text, $newline);
        } elseif (is_string($output)) {
            $output .= $text . ($newline ? "\n" : '');
        } else {
            throw new \Exception('"$output" should be string or OutputInterface !');
        }
    }

    /**
     * sanitize yeswiki.config.php before saving it.
     *
     * @param array<array-key, mixed>   $foldersToInclude
     * @param array<array-key, mixed>   $foldersToExclude
     * @param array<string, mixed>|null $hideConfigValuesParams
     */
    private function getWakkaConfigSanitized(array $foldersToInclude, array $foldersToExclude, ?array $hideConfigValuesParams = null): string
    {
        $config = $this->configurationService->getConfiguration(ConfigurationFileProvider::getConfigFileFromEnv());
        $config->load();
        if (
            !isset($config[self::PARAMS_KEY_IN_WAKKA])
            || !is_array($config[self::PARAMS_KEY_IN_WAKKA])
        ) {
            $data = [];
        } else {
            $data = $config[self::PARAMS_KEY_IN_WAKKA];
        }
        if (!empty($foldersToInclude)) {
            $data[self::KEY_FOR_FOLDERS_TO_INCLUDE] = $foldersToInclude;
        }
        if (!empty($foldersToExclude)) {
            $data[self::KEY_FOR_FOLDERS_TO_EXCLUDE] = $foldersToExclude;
        }
        if (!is_null($hideConfigValuesParams)) {
            $data[self::KEY_FOR_HIDE_CONFIG_VALUES] = $hideConfigValuesParams;
        } elseif (!isset($data[self::KEY_FOR_HIDE_CONFIG_VALUES]) || !is_array($data[self::KEY_FOR_HIDE_CONFIG_VALUES])) {
            $data[self::KEY_FOR_HIDE_CONFIG_VALUES] = self::DEFAULT_PARAMS_TO_ANONYMIZE;
        }
        $config[self::PARAMS_KEY_IN_WAKKA] = $data;

        $config = $this->setDefaultValuesRecursive($config[self::PARAMS_KEY_IN_WAKKA][self::KEY_FOR_HIDE_CONFIG_VALUES], $config);
        unset($config['wiki_status']);

        return $this->configurationService->getContentToWrite($config);
    }

    /**
     * @param array<array-key, mixed> $defaultValues
     *
     * @return mixed $values with every key $defaultValues names reset to its default
     */
    private function setDefaultValuesRecursive(array $defaultValues, mixed $values)
    {
        foreach ($defaultValues as $key => $value) {
            if (is_scalar($value)) {
                if (isset($values[$key])) {
                    $values[$key] = $value;
                }
            } elseif (is_array($value)) {
                if (isset($values[$key])) {
                    $values[$key] = $this->setDefaultValuesRecursive($value, $values[$key]);
                }
            }
        }

        return $values;
    }

    protected function setWikiStatus(): void
    {
        $config = $this->configurationService->getConfiguration(ConfigurationFileProvider::getConfigFileFromEnv());
        $config->load();
        $config['wiki_status'] = 'archiving';
        $this->configurationService->write($config);
    }

    protected function unsetWikiStatus(): void
    {
        $config = $this->configurationService->getConfiguration(ConfigurationFileProvider::getConfigFileFromEnv());
        $config->load();
        unset($config['wiki_status']);
        $this->configurationService->write($config);
    }

    /**
     * test db export connection.
     */
    protected function testDb(): bool
    {
        try {
            $results = $this->consoleService->startConsoleSync('core:exportdb', [
                '--test',
            ]);
            if (empty($results)) {
                return false;
            }
            $result = $results[array_key_first($results)];

            return empty($result['stderr']) && !empty($result['stdout']) && preg_match("/^OK\s*$/i", $result['stdout']);
        } catch (\Throwable $th) {
        }

        return false;
    }

    /**
     * extract sql content.
     *
     * @return string $sqlContent
     *
     * @throws \Exception
     * @throws \Throwable
     */
    protected function getSQLContent(string $privatePath): string
    {
        $resultFile = self::PROGRESS_FOLDER . '/' . self::SQL_FILENAME_IN_PRIVATE_FOLDER_IN_ZIP;
        try {
            $errorMessage = '';
            if ($this->testDb()) {
                $results = $this->consoleService->startConsoleSync('core:exportdb', [
                    "--filepath=$resultFile",
                ]);

                if ($this->storage->fileExists($resultFile)) {
                    $sqlContent = $this->storage->read($resultFile);
                    $this->forget($resultFile);

                    if (!empty($sqlContent)) {
                        return $sqlContent;
                    }
                }

                if (!empty($results)) {
                    $result = $results[array_key_first($results)];
                    if (!empty($result['stderr'])) {
                        $errorMessage .= "Error using mysqldump :\n{$result['stderr']}\n";
                    }
                }
            }
            $results = $this->dbService->dumper()->dump();
            if (empty($results['sql'])) {
                throw new \Exception($errorMessage . (empty($results['error']) ? 'SQL not exported via BackupMethod' : $results['error']));
            }

            return $results['sql'];
        } catch (\Throwable $th) {
            $this->forget($resultFile);

            throw $th;
        }
    }

    /**
     * check if there is enought free space before archive (size of files + custom + 300 Mo).
     *
     * @param list<string> $blacklistedRootFolders
     *
     * @throws \Exception
     */
    protected function assertEnoughtSpace(array $blacklistedRootFolders = []): void
    {
        if (empty($blacklistedRootFolders)) {
            $blacklistedRootFolders = self::FOLDERS_TO_EXCLUDE;
        }
        $estimateZipSize = 0;
        if (!in_array('files', $blacklistedRootFolders)) {
            $estimateZipSize += $this->folderSize('files');
        }
        if (!in_array('custom', $blacklistedRootFolders)) {
            $estimateZipSize += $this->folderSize('custom');
        }
        $estimateZipSize += 300 * 1024 * 1024;

        $freeSpace = $this->localFiles->freeSpace(YESWIKI_INSTANCE_DIR);
        if ($freeSpace < $estimateZipSize) {
            throw new \Exception('Not enough free space for a new archive!');
        }
    }

    /**
     * recursive method.
     *
     * @return int $bytes
     */
    private function folderSize(string $folderPath): int
    {
        $bytes = 0;
        foreach ($this->localFiles->entriesIn($folderPath) as $name) {
            if ($this->localFiles->isFile("$folderPath/$name")) {
                $bytes += $this->localFiles->size("$folderPath/$name");
            } elseif ($this->localFiles->isDirectory("$folderPath/$name")) {
                $bytes += $this->folderSize("$folderPath/$name");
            }
        }

        return $bytes;
    }

    /**
     * remove oldest files to keep only 10 files.
     */
    private function cleanOldestFiles(): void
    {
        $archivesToDelete = $this->archivesToDelete();
        if (!empty($archivesToDelete)) {
            $this->deleteArchives($archivesToDelete);
        }
    }

    private function getMaxNbFiles(): int
    {
        $archiveParams = $this->getArchiveParams();

        return (empty($archiveParams['max_nb_files'])
            || !is_scalar($archiveParams['max_nb_files'])
            || intval($archiveParams['max_nb_files']) < 3)
            ? 10
            : intval($archiveParams['max_nb_files']);
    }

    /**
     * extract list of archives to delete.
     *
     * @return list<string> $files the filenames to delete
     */
    public function archivesToDelete(bool $beforeArchive = false): array
    {
        $archives = $this->getArchives();
        $maxNBFiles = $this->getMaxNbFiles();
        $nbFilesToRemove = count($archives) - $maxNBFiles + ($beforeArchive ? 1 : 0);
        if ($nbFilesToRemove > 0) {
            $indexesToRemove = range($maxNBFiles, count($archives) - 1);
            $archivesIndexesMoreThan2days = $this->getIndexesMoreThanxdays($archives, 2);
            $archivesIndexesMoreThan1day = $this->getIndexesMoreThanxdays($archives, 1);

            $notDeletedArchivesMoreThan2Days = array_diff($archivesIndexesMoreThan2days, $indexesToRemove);
            if (!empty($archivesIndexesMoreThan2days) && empty($notDeletedArchivesMoreThan2Days)) {
                $indexesToRemove = array_diff($indexesToRemove, [min($archivesIndexesMoreThan2days)]);
                if (empty($indexesToRemove)) {
                    $indexesToRemove = [min($archivesIndexesMoreThan2days) - 1];
                } else {
                    array_unshift($indexesToRemove, min($indexesToRemove) - 1);
                }
            }
            $archivesIndexesBetween1and2days = array_diff($archivesIndexesMoreThan1day, $archivesIndexesMoreThan2days);
            $notDeletedArchivesBetween1and2days = array_diff($archivesIndexesBetween1and2days, $indexesToRemove);
            if (!empty($archivesIndexesBetween1and2days) && empty($notDeletedArchivesBetween1and2days)) {
                $indexesToRemove = array_diff($indexesToRemove, [min($archivesIndexesBetween1and2days)]);
                if (empty($indexesToRemove)) {
                    $indexesToRemove = [min($archivesIndexesBetween1and2days) - 1];
                } else {
                    array_unshift($indexesToRemove, min($indexesToRemove) - 1);
                }
            }
            $archivesToDelete = [];
            foreach ($indexesToRemove as $index) {
                $archivesToDelete[] = $archives[$index]['filename'];
            }

            return $archivesToDelete;
        }

        return [];
    }

    /**
     * @param list<array<string, mixed>> $archives as getArchives() answers them
     *
     * @return list<int> the positions in $archives of the archives older than $days days
     */
    private function getIndexesMoreThanxdays(array $archives, int $days): array
    {
        if ($days < 1) {
            return [];
        }
        $indexes = [];
        $nowMinusXDays = (new \DateTime())->sub(new \DateInterval("P{$days}D"));
        foreach ($archives as $key => $archive) {
            // check the the last file is aged more than x days
            $fileDateTime = (new \DateTime())
                ->setDate($archive['year'], $archive['month'], $archive['day'])
                ->setTime($archive['hours'], $archive['minutes'], $archive['seconds'], 0);
            if (
                $fileDateTime->diff($nowMinusXDays)->invert == 0 // current file date is before - x days
            ) {
                $indexes[] = $key;
            }
        }

        return $indexes;
    }

    /**
     * What is running, and where each run says so.
     *
     * @return array<string, mixed> the decoded info.json, keyed by uid
     */
    private function getInfoFromFile(): array
    {
        $file = self::PROGRESS_FOLDER . '/info.json';
        if (!$this->storage->fileExists($file)) {
            return [];
        }
        $content = json_decode($this->storage->read($file), true);

        return (empty($content) || !is_array($content)) ? [] : $content;
    }

    /** @param array<string, mixed> $content */
    private function setInfoToFile(array $content): void
    {
        $this->storage->write(self::PROGRESS_FOLDER . '/info.json', (string)json_encode($content));
    }

    /**
     * get a unique id for the current PID with input and output files created.
     *
     * @return array{uid: string, input: string|false, output: string|false}
     */
    private function getUID(): array
    {
        $info = $this->getInfoFromFile();
        $usedIDS = array_keys($info);
        do {
            $uid = uniqid();
        } while (in_array($uid, $usedIDS));

        $input = self::PROGRESS_FOLDER . "/input-$uid.log";
        $output = self::PROGRESS_FOLDER . "/output-$uid.log";
        $this->storage->write($input, '');
        $this->storage->write($output, '');

        $info[$uid] = [
            'input' => $input,
            'output' => $output,
        ];
        $this->setInfoToFile($info);

        return compact(['uid', 'input', 'output']);
    }

    /**
     * savePID for uid in info.json.
     */
    private function updatePIDForUID(string $pid, string $uid): void
    {
        $info = $this->getInfoFromFile();
        if (isset($info[$uid])) {
            $info[$uid]['pid'] = $pid;
            $this->setInfoToFile($info);
        }
    }

    /**
     * clean uid info in info.json.
     */
    private function cleanUID(string $uid): void
    {
        $info = $this->getInfoFromFile();
        if (isset($info[$uid])) {
            foreach (['input', 'output'] as $key) {
                $file = (string)($info[$uid][$key] ?? '');
                if ($file !== '' && $this->storage->fileExists($file)) {
                    $this->storage->delete($file);
                }
            }
            unset($info[$uid]);
            $this->setInfoToFile($info);
        }
    }

    /** check id current uid is running.
     * @param array<string, mixed> $info this uid's entry in info.json
     *
     * @return array<string, mixed> ['running' => bool, 'finished' => bool, 'stopped' => bool,'output' => string]
     */
    private function getRunningUIDdata(string $uid, array $info): array
    {
        if (empty($info['output']) || !$this->storage->fileExists((string)$info['output'])) {
            return ['running' => false, 'finished' => false, 'stopped' => false, 'output' => ''];
        }
        $output = $this->storage->read((string)$info['output']);

        $running = !empty(trim($output));
        $finished = !$running
            ? false
            : (preg_match("/(END|STOP)\s*$/", $output) ? true : false);
        $stopped = preg_match("/(STOP)\s*$/", $output) ? true : false;
        if ($finished) {
            $running = false;
        }

        return compact(['running', 'finished', 'stopped', 'output']);
    }

    /**
     * generate ---ListedRootFolder from DEFAULT, params and yeswiki.config.
     *
     * @param string                  $type       "white"|"black"
     * @param array<array-key, mixed> $fromParams
     *
     * @return list<string>
     */
    private function generateListRootFolders(string $type, array $fromParams): array
    {
        $list = ($type == 'white') ? self::FOLDERS_TO_INCLUDE : self::FOLDERS_TO_EXCLUDE;
        foreach ($this->sanitizeFileList($fromParams) as $folderName) {
            if (!in_array($folderName, $list)) {
                $list[] = $folderName;
            }
        }
        $archiveParams = $this->getArchiveParams();
        $key = ($type == 'white') ? self::KEY_FOR_FOLDERS_TO_INCLUDE : self::KEY_FOR_FOLDERS_TO_EXCLUDE;
        if (
            !empty($archiveParams[$key])
            && is_array($archiveParams[$key])
        ) {
            foreach ($this->sanitizeFileList($archiveParams[$key]) as $path) {
                if (!in_array($path, $list)) {
                    $list[] = $path;
                }
            }
        }

        return $list;
    }
}
