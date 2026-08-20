<?php

namespace YesWiki\Core\Service;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Process\Process;
use YesWiki\Core\Exception\StopArchiveException;
use YesWiki\Security\Controller\SecurityController;
use YesWiki\Wiki;

class ArchiveService
{
    // In order to prevent including subwikis or other folders that have nothing
    // to do with the current wiki, specify the folders to includes
    public const FOLDERS_TO_INCLUDE = [
        'actions',
        'custom',
        'docs',
        'files',
        'lang',
        'formatters',
        'handlers',
        'includes',
        'javascripts',
        'lang',
        'private',
        'setup',
        'styles',
        'templates',
        'tests',
        'themes',
        'tools',
        'vendor',
    ];

    // Some folders should not be included, like the existing backups
    public const FOLDERS_TO_EXCLUDE = [
        '.git',
        'node_modules',
        'private/backups',
    ];

    public const DEFAULT_PARAMS_TO_ANONYMIZE = [
        'mysql_host' => '',
        'mysql_database' => '',
        'mysql_user' => '',
        'mysql_password' => '',
        'contact_smtp_host' => '',
        'contact_smtp_user' => '',
        'contact_smtp_pass' => '',
        'api_allowed_keys' => [],
    ];
    public const PARAMS_KEY_IN_WAKKA = 'archive';
    public const KEY_FOR_PRIVATE_FOLDER = 'privatePath';
    public const KEY_FOR_FOLDERS_TO_INCLUDE = 'foldersToInclude';
    public const KEY_FOR_FOLDERS_TO_EXCLUDE = 'foldersToExclude';
    public const KEY_FOR_HIDE_CONFIG_VALUES = 'hideConfigValues';
    protected const DEFAULT_FOLDER_NAME_IN_TMP = 'yeswiki_archive';
    public const ARCHIVE_SUFFIX = '_archive';
    public const ARCHIVE_ONLY_FILES_SUFFIX = '_archive_only_files';
    public const ARCHIVE_ONLY_DATABASE_SUFFIX = '_archive_only_db';
    public const PRIVATE_FOLDER_NAME_IN_ZIP = 'private/backups';
    public const SQL_FILENAME_IN_PRIVATE_FOLDER_IN_ZIP = 'content.sql';
    public const INFO_FILENAME_IN_PRIVATE_FOLDER_IN_ZIP = BaseUrlRewriter::INFO_FILENAME;
    public const PRIVATE_FOLDER_README_DEFAULT_CONTENT = "# Description of the usage of folder private/backups\n\n" .
        "This folder is **reserved to backups**.\n\n" .
        "It **MUST NOT** be accessible from the internet.\n\n" .
        " - On Apache server, check that the file `.htaccess` is taken in count.\n" .
        " - On Nginx server or other, configure the server to **deny all** access on this folder\n";

    protected $configurationService;
    protected $consoleService;
    protected $dbService;
    protected $params;
    protected $securityController;
    protected $wiki;

    public function __construct(
        ConfigurationService $configurationService,
        ConsoleService $consoleService,
        DbService $dbService,
        ParameterBagInterface $params,
        SecurityController $securityController,
        Wiki $wiki
    ) {
        $this->configurationService = $configurationService;
        $this->consoleService = $consoleService;
        $this->dbService = $dbService;
        $this->params = $params;
        $this->securityController = $securityController;
        $this->wiki = $wiki;
    }

    /**
     * archive data in zip file.
     *
     * @param string|OutputInterface &$output
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
    ) {
        $vStatus = $this->getArchivingStatus();

        $inputFile = '';
        $outputFile = '';

        if (!$vStatus['canArchive']) {
            // if we cannot archive, we need to stop the process and inform the user so that he can handle the problem
            $vMessages = $this->getCannotArchiveDetails($vStatus);

            $this->unsetWikiStatus();
            $this->writeOutput($output, 'STOP', true, $outputFile);

            throw new \Exception(_t('AU_CANNOT_ARCHIVE') . implode(', ', $vMessages));
        }
        $privatePath = $this->getPrivateFolder();

        if (!empty($uid)) {
            $info = $this->getInfoFromFile($privatePath);
            if (isset($info[$uid])) {
                $inputFile = $info[$uid]['input'];
                $outputFile = $info[$uid]['output'];
            }
        }
        if (!empty($outputFile)) {
            if (@file_put_contents($outputFile, '') === false) {
                throw new \Exception('Cannot write to archive output file. Please check file system access rights');
            }
        }

        // checking folder not available on the internet
        if (@file_put_contents("$privatePath/tmpTestFile000.txt", 'test') === false) {
            throw new \Exception('Cannot write to test file. Please check file system access rights');
        }
        $error = !$this->localPrivateFolderNotAvailableOnInternet($privatePath, 'tmpTestFile000.txt');
        if (file_exists("$privatePath/tmpTestFile000.txt")) {
            unlink("$privatePath/tmpTestFile000.txt");
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

        if ($this->checkIfNeedStop($inputFile)) {
            $this->unsetWikiStatus();
            $this->writeOutput($output, 'STOP', true, $outputFile);

            return '';
        }
        // prepare location of zip file

        $archiveFileName = (new \DateTime())->format('Y-m-d\\TH-i-s') . "$fileSuffix.zip";
        $location = $privatePath . DIRECTORY_SEPARATOR . $archiveFileName;
        if (file_exists($location)) {
            throw new \Exception('Zip file already existing !');
        }
        if (file_exists($location)) {
            throw new \Exception('Zip file already existing !');
        }
        if ($this->securityController->isWikiHibernated()) {
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

            if ($this->createZip($location, $foldersToInclude, $blacklistedRootFolders, $output, $sqlContent, $onlyDb, $hideConfigValuesParams, $inputFile, $outputFile)) {
                $this->writeOutput($output, "Archive \"$location\" successfully created !", true, $outputFile);

                // clean oldest files
                $this->cleanOldestFiles();

                $this->unsetWikiStatus();

                $this->writeOutput($output, 'END', true, $outputFile);
            } else {
                throw new StopArchiveException('Stop archive : not saved !');
            }
        } catch (StopArchiveException $ex) {
            @unlink($location);
            $this->unsetWikiStatus();
            $this->writeOutput($output, 'STOP', true, $outputFile);

            return '';
        } catch (\Throwable $th) {
            @unlink($location);
            $this->unsetWikiStatus();
            $this->writeOutput($output, 'STOP', true, $outputFile);

            throw $th;
        }

        return $location;
    }

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
    public function hasValidatedBackup($token): bool
    {
        if (empty($token) || !is_string($token)) {
            return false;
        }

        $privatePath = $this->getPrivateFolder();
        $info = $this->getInfoFromFile($privatePath);
        $result = ($info[$token]['isForcedUpdate'] ?? false) === true;

        foreach ($info as $uid => $data) {
            if (($data['isForcedUpdate'] ?? false) === true) {
                $this->cleanUID($uid, $privatePath);
            }
        }

        return $result;
    }

    /**
     * retrieve the current status to archive.
     *
     * @return array ['canArchive' => bool,'archiving' => bool, 'hibernated' => bool, 'privatePathWritable' => bool, 'canExec' => bool]
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
                if (file_exists($tmpFileName)) {
                    unlink($tmpFileName);
                }
                try {
                    if (@file_put_contents($tmpFileName, 'test') === false) {
                        throw new \Exception('Cannot write to tmp file. Please check file system access rights');
                    }
                    if (!file_exists($tmpFileName)) {
                        throw new \Exception('Not writable folder');
                    }
                    $content = @file_get_contents($tmpFileName);

                    if ($content === false) {
                        throw new \Exception('Cannot read tmp file. Please check file system access rights');
                    }

                    if ($content != 'test') {
                        throw new \Exception('Bad content');
                    }
                    $notAvailableOnTheInternet = $this->localPrivateFolderNotAvailableOnInternet($privatePath, basename($tmpFileName));
                    unlink($tmpFileName);
                } catch (\Throwable $th) {
                    $privatePathWritable = false;
                    if (file_exists($tmpFileName)) {
                        unlink($tmpFileName);
                    }
                }
            }
        }

        // test console
        try {
            $results = $this->consoleService->startConsoleSync('helloworld:hello', []);
            if (!empty($results)) {
                $result = $results[array_key_first($results)];
                if (
                    !empty($result['stdout'])
                    && preg_match("/Hello !(?:\r|\n)+/", $result['stdout'])
                ) {
                    $canExec = true;
                }
            }
        } catch (\Throwable $th) {
            $canExec = false;
        }

        // test db
        if ($canExec) {
            $dB = $this->testDb();
        }
        // free space
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
        $uidData = $this->getUID($privatePath);
        $info = $this->getInfoFromFile($privatePath);
        $uid = $uidData['uid'] ?? '';
        if (empty($uid) || !isset($info[$uid])) {
            return '';
        }

        $info[$uid]['isForcedUpdate'] = true;
        $this->setInfoToFile($info, $privatePath);

        return $uid;
    }

    /**
     * start archive async via CLI or directly if sync.
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
        $uidData = $this->getUID($privatePath);
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
                $this->updatePIDForUID($process->getPid(), $uidData['uid'], $privatePath);

                return $uidData['uid'];
            }
            $this->cleanUID($uidData['uid'], $privatePath);

            return '';
        }
        $output = '';
        $location = $this->archive($output, $savefiles, $savedatabase, $foldersToInclude, $foldersToExclude, null, $uidData['uid']);
        if (empty($location)) {
            $this->cleanUID($uidData['uid'], $privatePath);

            return '';
        }

        return $uidData['uid'];
    }

    /**
     * get the list of archives in a array with information for each one.
     *
     * Reading the source address opens each archive, so only the screens offering a restore ask for it.
     */
    public function getArchives(bool $withSourceBaseUrl = false): array
    {
        $archives = [];
        $privatePath = $this->getPrivateFolder();
        $files = scandir($privatePath);
        foreach ($files as $filename) {
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
                    'size' => filesize("$privatePath/$filename"),
                    'link' => $this->wiki->Href('', "api/archives/$filename"),
                    'sourceBaseUrl' => ($withSourceBaseUrl && ($matches[7] ?? 'full') !== 'only_files')
                        ? $this->getArchiveSourceBaseUrl($filename)
                        : '',
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
        // sanitize $filename
        $filename = basename($filename);
        if (substr($filename, -4) != '.zip') {
            return '';
        }
        $filePath = "$privatePath/$filename";

        return (file_exists($filePath) && is_file($filePath)) ? $filePath : '';
    }

    /**
     * Restore a backup archive (database and/or files).
     *
     * @throws \Exception
     */
    public function restoreArchive(
        string $filename,
        bool $restoreFiles = true,
        bool $restoreDatabase = true,
        bool $rewriteUrls = true
    ): void {
        $filePath = $this->getFilePath($filename);
        if (empty($filePath)) {
            throw new \Exception("Archive not found: $filename");
        }

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
                $info = $this->readRestoreInfo($zip);
                $this->assertSameTablePrefix($info);
                $substitutions = $rewriteUrls
                    ? BaseUrlRewriter::substitutions($info['base_url'] ?? '', $this->params->get('base_url'))
                    : [];
                $this->restoreDatabase(BaseUrlRewriter::rewrite($sqlContent, $substitutions));
            }

            if ($restoreFiles && !$onlyDb) {
                $this->restoreFilesFromZip($zip);
            }
        } finally {
            $zip->close();
        }
    }

    /**
     * Drop prefixed tables and re-import SQL dump via a dedicated connection.
     *
     * @throws \Exception
     */
    protected function restoreDatabase(string $sqlContent): void
    {
        $tablesPrefix = trim($this->dbService->prefixTable(''));
        if (empty($tablesPrefix)) {
            throw new \Exception('Table prefix is empty — refusing to drop all tables');
        }

        $this->dbService->query('SET FOREIGN_KEY_CHECKS=0');
        $tables = $this->dbService->loadAll('show tables');
        if (is_array($tables)) {
            foreach ($tables as $tableInfo) {
                $tableName = array_values($tableInfo)[0];
                if (strpos($tableName, $tablesPrefix) === 0) {
                    $this->dbService->query('DROP TABLE IF EXISTS `' . $tableName . '`');
                }
            }
        }
        $this->dbService->query('SET FOREIGN_KEY_CHECKS=1');

        // Dedicated connection for multi_query to avoid leaving the shared DbService
        // link with unread result sets.
        $conn = mysqli_connect(
            $this->params->get('mysql_host'),
            $this->params->get('mysql_user'),
            $this->params->get('mysql_password'),
            $this->params->get('mysql_database')
        );
        if (!$conn) {
            throw new \Exception('Cannot open database connection for restore');
        }
        mysqli_set_charset($conn, 'utf8mb4');

        // Strip bare semicolons (empty statements from empty tables in old backups).
        $sqlContent = preg_replace('/^\s*;\s*$/m', '', $sqlContent);
        $sqlContent = trim($sqlContent);

        try {
            if (!mysqli_multi_query($conn, $sqlContent)) {
                throw new \Exception('SQL restore failed: ' . mysqli_error($conn));
            }
            do {
                if ($r = mysqli_store_result($conn)) {
                    mysqli_free_result($r);
                }
            } while (mysqli_more_results($conn) && mysqli_next_result($conn));

            if ($errno = mysqli_errno($conn)) {
                throw new \Exception("SQL restore error (errno $errno): " . mysqli_error($conn));
            }
        } finally {
            mysqli_close($conn);
        }
    }

    /**
     * Extract wiki files from zip, skipping private/backups/ and wakka.config.php.
     */
    protected function restoreFilesFromZip(\ZipArchive $zip): void
    {
        $wikiRoot = realpath(getcwd());
        $skipPrefix = self::PRIVATE_FOLDER_NAME_IN_ZIP . '/';
        $skipFile = ConfigurationFileProvider::getConfigFileFromEnv();

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (strpos($name, $skipPrefix) === 0 || $name === $skipFile || strpos($name, '..') !== false) {
                continue;
            }
            if (str_ends_with($name, '/')) {
                $dir = $wikiRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                continue;
            }
            $zip->extractTo($wikiRoot, $name);
        }
    }

    /**
     * A dump names its own tables, so restoring it under another prefix would drop this wiki
     * and recreate the archive's tables where nothing looks for them.
     *
     * @param array<string,mixed> $info
     *
     * @throws \Exception
     */
    protected function assertSameTablePrefix(array $info): void
    {
        $sourcePrefix = $info['table_prefix'] ?? '';
        $targetPrefix = trim($this->dbService->prefixTable(''));
        if (is_string($sourcePrefix) && $sourcePrefix !== '' && $sourcePrefix !== $targetPrefix) {
            throw new \Exception("Archive was taken from tables prefixed '$sourcePrefix' but this wiki uses '$targetPrefix'. " . 'Set table_prefix to the archive value before restoring.');
        }
    }

    /**
     * Describe the wiki the archive was taken from, so a restore can adapt links to another address.
     */
    protected function buildRestoreInfo(): string
    {
        return json_encode([
            'base_url' => $this->params->get('base_url'),
            'table_prefix' => $this->params->get('table_prefix'),
            'yeswiki_version' => $this->params->get('yeswiki_version'),
            'yeswiki_release' => $this->params->get('yeswiki_release'),
            'date' => (new \DateTime())->format('c'),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return array<string,mixed>
     */
    protected function readRestoreInfo(\ZipArchive $zip): array
    {
        $content = $zip->getFromName(self::PRIVATE_FOLDER_NAME_IN_ZIP . '/' . self::INFO_FILENAME_IN_PRIVATE_FOLDER_IN_ZIP);
        if ($content === false) {
            return [];
        }
        $info = json_decode($content, true);

        return is_array($info) ? $info : [];
    }

    /**
     * base_url of the wiki an archive was taken from, empty when the archive predates the restore info file.
     */
    public function getArchiveSourceBaseUrl(string $filename): string
    {
        $filePath = $this->getFilePath($filename);
        if (empty($filePath)) {
            return '';
        }
        $zip = new \ZipArchive();
        if ($zip->open($filePath) !== true) {
            return '';
        }
        try {
            $baseUrl = $this->readRestoreInfo($zip)['base_url'] ?? '';

            return is_string($baseUrl) ? $baseUrl : '';
        } finally {
            $zip->close();
        }
    }

    /**
     * delete archives.
     *
     * @return array $results = ['filename' => bool]
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
            $results[$filename] = (substr($filename, -4) == '.zip') && file_exists("$privatePath/$filename") && is_file("$privatePath/$filename");
            if ($results[$filename]) {
                $results[$filename] = unlink("$privatePath/$filename");
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
     * @return array ['found'=> bool,'running' => bool,'finished'=>bool,'output' => string]
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
        $info = $this->getInfoFromFile($privateFolder);
        // clean others uids because it should not be ever existing
        foreach ($info as $infoUid => $infoData) {
            if ($infoUid != $uid) {
                $this->cleanUID($infoUid, $privateFolder);
            }
        }
        // refresh from file
        $info = $this->getInfoFromFile($privateFolder);
        if (!isset($info[$uid])) {
            return $results;
        } elseif (!$forceStarted && empty($info[$uid]['pid'])) {
            $this->cleanUID($uid, $privateFolder);
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
                $this->cleanUID($uid, $privateFolder);
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
            || !is_file($info[$uid]['input'])
        ) {
            return false;
        }
        if (@file_put_contents($info[$uid]['input'], 'STOP') === false) {
            throw new \Exception('Cannot write to archive info file. Please check file system access rights');
        }

        return true;
    }

    /**
     * check if need to stop archive.
     */
    protected function checkIfNeedStop(string $inputFile = ''): bool
    {
        if (empty($inputFile) || !is_file($inputFile)) {
            return false;
        }
        $content = @file_get_contents($inputFile);

        if ($content === false) {
            throw new \Exception('Cannot read archive input file. Please check file system access rights');
        }

        if (empty($content)) {
            return false;
        }

        return preg_match('/^STOP.*/', $content);
    }

    /**
     * create the zip file.
     *
     * @param string|OutputInterface &$output
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
        if (!file_exists('index.php') || !file_exists(ConfigurationFileProvider::getConfigFileFromEnv()) || !file_exists('composer.json') || !file_exists('composer.lock')) {
            throw new \Exception('Can only be started from main directory');
        }
        $pathToArchive = getcwd();

        $pathToArchive = preg_replace("/(\/|\\\\)$/", '', $pathToArchive);
        $dirs = [$pathToArchive];
        $dirnamePathLen = strlen($pathToArchive);

        $whitelistedRootFolders = $this->generateListRootFolders('white', $foldersToInclude);

        // open file
        $zip = new \ZipArchive();

        $vCanceled = false;

        $resource = $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        if ($resource !== true) {
            return;
        }

        // register cancel callback if available
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

        // register progress callback if available
        if (method_exists($zip, 'registerProgressCallback')) {
            $zip->registerProgressCallback(0.1, function ($r) use (&$output, $outputFile) {
                $this->writeOutput($output, 'Zip file creation : ' . strval(round($r * 100, 0)) . ' %', true, $outputFile);
            });
        }

        if (!$vCanceled && !$onlyDb) {
            // add empty cache folder
            $zip->addEmptyDir('cache');

            while (count($dirs)) {
                $dir = current($dirs);
                $dir = preg_replace("/(?:\/|\\\\|([^\/\\\\]))$/", '$1', $dir);
                $baseDirName = preg_replace('/\\\\/', '/', substr($dir, $dirnamePathLen));
                $baseDirName = preg_replace("/^\//", '', $baseDirName);
                if (empty($baseDirName) || (!empty($baseDirName) && $this->shouldIncludeFolder($baseDirName, $whitelistedRootFolders, $blacklistedRootFolders))) {
                    if (!empty($baseDirName)) {
                        $this->writeOutput($output, "Adding folder \"$baseDirName\"", true, $outputFile);
                        $zip->addEmptyDir($baseDirName);
                    }

                    $dh = opendir($dir);
                    while (false !== ($file = readdir($dh))) {
                        if ($file != '.' && $file != '..') {
                            $localName = $dir . DIRECTORY_SEPARATOR . $file;
                            $relativeName = (empty($baseDirName) ? '' : "$baseDirName/") . $file;
                            if (empty($baseDirName) && $file == ConfigurationFileProvider::getConfigFileFromEnv()) {
                                $zip->addFromString($relativeName, $this->getWakkaConfigSanitized($whitelistedRootFolders, $blacklistedRootFolders, $hideConfigValuesParams));
                            } elseif (is_file($localName)) {
                                $zip->addFile($localName, $relativeName);
                            } elseif (is_dir($localName)) {
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

                    closedir($dh);
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

            $this->writeOutput($output, 'Adding restore info file', true, $outputFile);
            $zip->addFromString(
                self::PRIVATE_FOLDER_NAME_IN_ZIP . '/' . self::INFO_FILENAME_IN_PRIVATE_FOLDER_IN_ZIP,
                $this->buildRestoreInfo()
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

        unlink($zipPath);

        return false;
    }

    /**
     * test if folder should be included.
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

    private function sanitizeFileList(array $list): array
    {
        $outputList = [];
        foreach ($list as $filePath) {
            if (is_string($filePath)) {
                $filePath = trim($filePath);
                // remove path containing '/../' to be sure to keep in root folder of the wiki
                // or begining by '/' or 'c:\' to be sure to keep relative to root folder of website
                if (!empty($filePath) && !preg_match('/^(?:\\/|\\\\)|[A-Za-z]:\\\\|(?:\\/|\\\\|^)\\.\\.(?:\\/|\\\\|$)/', $filePath)) {
                    $formattedFilePath = preg_replace("/(\/|\\\\)$/", '', $filePath);
                    if (!in_array($formattedFilePath, $outputList)) {
                        $outputList[] = $formattedFilePath;
                    }
                }
            }
        }

        return $outputList;
    }

    private function getPrivateFolder(): string
    {
        $archiveParams = $this->getArchiveParams();

        $folderPath = (
            empty($archiveParams[self::KEY_FOR_PRIVATE_FOLDER])
            || !is_string($archiveParams[self::KEY_FOR_PRIVATE_FOLDER])
        )
            ? self::PRIVATE_FOLDER_NAME_IN_ZIP
            : $archiveParams[self::KEY_FOR_PRIVATE_FOLDER];

        if ($folderPath != '%TMP') {
            if (
                is_dir($folderPath)
            ) {
                return preg_replace("/(\/|\\\\)$/", '', $folderPath);
            }
            throw new \Exception(self::PARAMS_KEY_IN_WAKKA . '[' . self::KEY_FOR_PRIVATE_FOLDER . '] is not a directory.');
        } else {
            $sanitizeWebsiteName = preg_replace(
                '/-+$/',
                '',
                preg_replace(
                    "/[.\/\\{}\[\]#?&=!;:\\\$<>]/",
                    '-',
                    preg_replace(
                        "/^https?:\/\//",
                        '',
                        $this->params->get('base_url')
                    )
                )
            );
            $tmp = sys_get_temp_dir();
            $slash = DIRECTORY_SEPARATOR;
            $dirName = "yeswiki-$sanitizeWebsiteName";
            $this->createFolder("$tmp{$slash}", $dirName);

            return "$tmp{$slash}$dirName";
        }
    }

    private function createFolder(string $basePath, string $path)
    {
        if (file_exists($basePath . $path) && !is_dir($basePath . $path)) {
            throw new \Exception("Folder \"$path\" in \"$basePath\" should be a directory !");
        } elseif (!file_exists($basePath . $path)) {
            mkdir($basePath . $path);
        }
    }

    public function getArchiveParams(): array
    {
        if ($this->params->has(self::PARAMS_KEY_IN_WAKKA)) {
            $archiveParams = $this->params->get(self::PARAMS_KEY_IN_WAKKA);
        }

        return (empty($archiveParams) || !is_array($archiveParams)) ? [] : $archiveParams;
    }

    private function canWriteFolder(string $path): bool
    {
        return is_dir($path) && is_writable($path);
    }

    private function localPrivateFolderNotAvailableOnInternet(string $localPath, string $testFileName): bool
    {
        $isAbsolutePath = (
            in_array(substr($localPath, 0, 1), ['/', DIRECTORY_SEPARATOR])
            || (
                DIRECTORY_SEPARATOR == '\\'

                    && preg_match('/^[A-Za-z]:.*$/', $localPath)
            )
        );
        $basePath = realpath(getcwd());
        $realLocalPath = $isAbsolutePath
            ? realpath($localPath)
            : realpath($basePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $localPath));
        $isLocal = (substr($realLocalPath, 0, strlen($basePath)) == $basePath);

        if (!$isLocal) {
            return true;
        }
        if (!file_exists("$localPath/$testFileName")) {
            throw new \Exception("\"$localPath/$testFileName\" must exist for tests !");
        }
        $url = preg_replace("/\??$/", '', $this->params->get('base_url'));
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

        return !$headers || !empty($headers[0]) && !strstr(get_headers($url, true, $ct)[0], '200 OK');
    }

    /**
     * write text to the output.
     *
     * @param string|OutputInterface &$output
     */
    private function writeOutput(&$output, string $text, bool $newline = true, string $outputFile = '')
    {
        if (!empty($outputFile) && is_file($outputFile)) {
            if (@file_put_contents($outputFile, $text . ($newline ? "\n" : ''), FILE_APPEND) === false) {
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
     * sanitize wakka.config.php before saving it.
     */
    private function getWakkaConfigSanitized(array $foldersToInclude, array $foldersToExclude, ?array $hideConfigValuesParams = null): string
    {
        // get wakka.config.php content
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
        // remove current wiki_status
        unset($config['wiki_status']);

        return $this->configurationService->getContentToWrite($config);
    }

    private function setDefaultValuesRecursive(array $defaultValues, $values)
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

    protected function setWikiStatus()
    {
        $config = $this->configurationService->getConfiguration(ConfigurationFileProvider::getConfigFileFromEnv());
        $config->load();
        $config['wiki_status'] = 'archiving';
        $this->configurationService->write($config);
    }

    protected function unsetWikiStatus()
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
            if (empty($results) || !is_array($results)) {
                return false;
            }
            $result = $results[array_key_first($results)];

            return !empty($result['stdout']) && preg_match("/^OK\s*$/i", $result['stdout']);
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
        $resultFile = $privatePath . '/' . self::SQL_FILENAME_IN_PRIVATE_FOLDER_IN_ZIP;
        try {
            $errorMessage = '';
            if ($this->testDb()) {
                $results = $this->consoleService->startConsoleSync('core:exportdb', [
                    "--filepath=$resultFile",
                ]);

                // get content
                if (file_exists($resultFile)) {
                    $sqlContent = @file_get_contents($resultFile);

                    if ($sqlContent === false) {
                        throw new \Exception('Cannot read sql content file. Please check file system access rights');
                    }

                    @unlink($resultFile);

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
            // backup
            $results = $this->dbService->getSQLContentBackupMethod();
            if (empty($results['sql'])) {
                throw new \Exception($errorMessage . (empty($results['error']) ? 'SQL not exported via BackupMethod' : $results['error']));
            }

            return $results['sql'];
        } catch (\Throwable $th) {
            if (file_exists($resultFile)) {
                unlink($resultFile);
            }
            throw $th;
        }
    }

    /**
     * check if there is enought free space before archive (size of files + custom + 300 Mo).
     *
     * @throws \Exception
     */
    protected function assertEnoughtSpace(array $blacklistedRootFolders = [])
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
        $estimateZipSize += 300 * 1024 * 1024; // 300Mb for the rest of te wiki

        $freeSpace = disk_free_space(realpath(getcwd()));
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
        $contents = array_filter(scandir($folderPath), function ($path) {
            return !in_array($path, ['.', '..']);
        });
        $bytes = 0;
        foreach ($contents as $name) {
            if (is_file("$folderPath/$name")) {
                $bytes += filesize("$folderPath/$name");
            } elseif (is_dir("$folderPath/$name")) {
                $bytes += $this->folderSize("$folderPath/$name");
            }
        }

        return $bytes;
    }

    /**
     * remove oldest files to keep only 10 files.
     */
    private function cleanOldestFiles()
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
     * @return array $files
     */
    public function archivesToDelete(bool $beforeArchive = false): array
    {
        $archives = $this->getArchives();
        $maxNBFiles = $this->getMaxNbFiles();
        $nbFilesToRemove = count($archives) - $maxNBFiles + ($beforeArchive ? 1 : 0);
        if ($nbFilesToRemove > 0) {
            // there are files to remove
            // keep at least one file more than 1 day and other more than 2 days to prevent
            // full deletion if attack on api
            $indexesToRemove = range($maxNBFiles, count($archives) - 1);
            if (!empty($indexesToRemove)) {
                $archivesIndexesMoreThan2days = $this->getIndexesMoreThanxdays($archives, 2);
                $archivesIndexesMoreThan1day = $this->getIndexesMoreThanxdays($archives, 1);

                $notDeletedArchivesMoreThan2Days = array_diff($archivesIndexesMoreThan2days, $indexesToRemove);
                if (!empty($archivesIndexesMoreThan2days) && empty($notDeletedArchivesMoreThan2Days)) {
                    // we should kept the most recent 2 days old
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
                    // we should kept the most recent 1 day old
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
        }

        return [];
    }

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
     * get content of info.json file from privatePath.
     */
    private function getInfoFromFile(string $privateFolder = '')
    {
        if (empty($privateFolder)) {
            $privateFolder = $this->getPrivateFolder();
        }
        if (!file_exists("$privateFolder/info.json")) {
            if (@file_put_contents("$privateFolder/info.json", '{}') === false) {
                throw new \Exception('Cannot write to archive info file. Please check file system access rights');
            }
        }
        $fileContent = @file_get_contents("$privateFolder/info.json");

        if ($fileContent === false) {
            throw new \Exception('Cannot read archive info file. Please check file system access rights');
        }

        $content = json_decode($fileContent, true);

        return (empty($content) || !is_array($content)) ? [] : $content;
    }

    /**
     * set content to info.json file from privatePath.
     */
    private function setInfoToFile($content, string $privateFolder = '')
    {
        if (empty($privateFolder)) {
            $privateFolder = $this->getPrivateFolder();
        }

        if (@file_put_contents("$privateFolder/info.json", json_encode($content)) === false) {
            throw new \Exception('Cannot set archive info to file. Please check file system access rights');
        }
    }

    /**
     * get a unique id for the current PID with input and output files created.
     *
     * @return array|null ['uid' => string, 'input' => string, 'output' => string]
     */
    private function getUID(string $privateFolder = ''): ?array
    {
        if (empty($privateFolder)) {
            $privateFolder = $this->getPrivateFolder();
        }
        $info = $this->getInfoFromFile($privateFolder);
        $usedIDS = array_keys($info);
        do {
            $uid = uniqid();
        } while (in_array($uid, $usedIDS));

        // create files
        $input = "$privateFolder/input-$uid.log";
        $output = "$privateFolder/output-$uid.log";
        if (@file_put_contents($input, '') === false) {
            throw new \Exception('Cannot write to archive input file. Please check file system access rights');
        }
        if (@file_put_contents($output, '') === false) {
            throw new \Exception('Cannot write to archive output file. Please check file system access rights');
        }

        $info[$uid] = [
            'input' => realpath($input),
            'output' => realpath($output),
        ];
        $this->setInfoToFile($info, $privateFolder);

        return compact(['uid', 'input', 'output']);
    }

    /**
     * savePID for uid in info.json.
     */
    private function updatePIDForUID(string $pid, string $uid, string $privateFolder = '')
    {
        if (empty($privateFolder)) {
            $privateFolder = $this->getPrivateFolder();
        }
        $info = $this->getInfoFromFile($privateFolder);
        if (isset($info[$uid])) {
            $info[$uid]['pid'] = $pid;
            $this->setInfoToFile($info, $privateFolder);
        }
    }

    /**
     * clean uid info in info.json.
     */
    private function cleanUID(string $uid, string $privateFolder = '')
    {
        if (empty($privateFolder)) {
            $privateFolder = $this->getPrivateFolder();
        }
        $info = $this->getInfoFromFile($privateFolder);
        if (isset($info[$uid])) {
            if (!empty($info[$uid]['input']) && is_file($info[$uid]['input'])) {
                unlink($info[$uid]['input']);
            }
            if (!empty($info[$uid]['output']) && is_file($info[$uid]['output'])) {
                unlink($info[$uid]['output']);
            }
            unset($info[$uid]);
            $this->setInfoToFile($info, $privateFolder);
        }
    }

    /** check id current uid is running.
     * @return array ['running' => bool, 'finished' => bool, 'stopped' => bool,'output' => string]
     */
    private function getRunningUIDdata(string $uid, array $info): array
    {
        if (!is_file($info['output'])) {
            return false;
        }
        $output = @file_get_contents($info['output']);

        if ($output === false) {
            throw new \Exception('Cannot read archive output file. Please check file system access rights');
        }

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
     * generate ---ListedRootFolder from DEFAULT, params and wakka.config.
     *
     * @param string $type "white"|"black"
     */
    private function generateListRootFolders(string $type, array $fromParams): array
    {
        $list = ($type == 'white') ? self::FOLDERS_TO_INCLUDE : self::FOLDERS_TO_EXCLUDE;
        foreach ($this->sanitizeFileList($fromParams) as $folderName) {
            if (!in_array($folderName, $list)) {
                $list[] = $folderName;
            }
        }
        // merge `foldersToInclude` or `foldersToExclude` from wakka.config.php
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
