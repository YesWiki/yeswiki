<?php

namespace YesWiki\Core\Service;

use DateTime;
use DateInterval;
use Exception;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Process\Process;
use YesWiki\Security\Controller\SecurityController;
use YesWiki\Core\Entity\ConfigurationFile;
use YesWiki\Core\Exception\StopArchiveException;
use YesWiki\Core\Service\ConfigurationService;
use YesWiki\Core\Service\ConsoleService;
use YesWiki\Wiki;
use Throwable;
use ZipArchive;

class ArchiveService
{
    public const DEFAULT_EXCLUDED_FILES = [
        'node_modules',
        'tools/*/node_modules',
        '.git',
        'tools/*/.git',
        "cache/*",
        'private/backups/*.zip',
        'private/backups/info.json',
        'private/backups/*.log',
    ];
    public const DEFAULT_PARAMS_TO_ANONYMIZE = [
        'mysql_host' => '',
        'mysql_database' => '',
        'mysql_user' => '',
        'mysql_password' => '',
        'contact_smtp_host' => '',
        'contact_smtp_user' => '',
        'contact_smtp_pass' => '',
        'api_allowed_keys' => []
    ];
    public const PARAMS_KEY_IN_WAKKA = 'archive';
    public const KEY_FOR_PRIVATE_FOLDER = 'privatePath';
    public const KEY_FOR_EXTRAFILES = 'extrafiles';
    public const KEY_FOR_EXCLUDEDFILES = 'excludedfiles';
    public const KEY_FOR_ANONYMOUS = 'anonymous';
    protected const DEFAULT_FOLDER_NAME_IN_TMP = "yeswiki_archive";
    public const ARCHIVE_SUFFIX = "_archive";
    public const ARCHIVE_ONLY_FILES_SUFFIX = "_archive_only_files";
    public const ARCHIVE_ONLY_DATABASE_SUFFIX = "_archive_only_db";
    public const PRIVATE_FOLDER_NAME_IN_ZIP = "private/backups";
    public const SQL_FILENAME_IN_PRIVATE_FOLDER_IN_ZIP = "content.sql";
    public const PRIVATE_FOLDER_README_DEFAULT_CONTENT = "# Description of the usage of folder private/backups\n\n".
        "This folder is **reserved to backups**.\n\n".
        "It **MUST NOT** be accessible from the internet.\n\n".
        " - On Apache server, check that the file `.htaccess` is taken in count.\n".
        " - On Nginx server or other, configure the server to **deny all** access on this folder\n";
    public const MAX_NB_FILES = 10;

    protected $configurationService;
    protected $consoleService;
    protected $params;
    protected $securityController;
    protected $wiki;

    public function __construct(
        ConfigurationService $configurationService,
        ConsoleService $consoleService,
        ParameterBagInterface $params,
        SecurityController $securityController,
        Wiki $wiki
    ) {
        $this->configurationService = $configurationService;
        $this->consoleService = $consoleService;
        $this->params = $params;
        $this->securityController = $securityController;
        $this->wiki = $wiki;
    }

    /**
     * archive data in zip file
     * @param string|OutputInterface &$output
     * @param bool $savefiles
     * @param bool $savedatabase
     * @param array $extrafiles
     * @param array $excludedfiles
     * @param null|array $anonymousParams
     * @param string $uid
     * @throws Exception
     */
    public function archive(
        &$output,
        bool $savefiles = true,
        bool $savedatabase = true,
        array $extrafiles = [],
        array $excludedfiles = [],
        ?array $anonymousParams = null,
        string $uid = ""
    ) {
        $inputFile = "";
        $outputFile = "";
        $privatePath = $this->getPrivateFolder();

        if (!empty($uid)) {
            $info = $this->getInfoFromFile($privatePath);
            if (isset($info[$uid])) {
                $inputFile = $info[$uid]['input'];
                $outputFile = $info[$uid]['output'];
            }
        }
        if (!empty($outputFile)) {
            file_put_contents($outputFile, "");
        }

        // checking folder not available on the internet
        file_put_contents("$privatePath/tmpTestFile000.txt", "test");
        $error = !$this->localPrivateFolderNotAvailableOnInternet($privatePath, "tmpTestFile000.txt");
        if (file_exists("$privatePath/tmpTestFile000.txt")) {
            unlink("$privatePath/tmpTestFile000.txt");
        }
        if ($error) {
            $this->writeOutput($output, "! Private folder available on the internet", true, $outputFile);
            $this->writeOutput($output, "STOP", true, $outputFile);
            return "";
        }

        $this->writeOutput($output, "=== Checking free space ===", true, $outputFile);
        try {
            $this->assertEnoughtSpace();
        } catch (Throwable $th) {
            $this->writeOutput($output, "There is not enough free space.", true, $outputFile);
            $this->writeOutput($output, "=> {$th->getMessage()}", true, $outputFile);
            $this->writeOutput($output, "STOP", true, $outputFile);
            throw $th;
        }
        $this->writeOutput($output, "There is enough free space.", true, $outputFile);

        if ($this->checkIfNeedStop($inputFile)) {
            $this->writeOutput($output, "STOP", true, $outputFile);
            return "";
        }
        $onlyDb = false;
        // check options and prepare file suffix
        if (!$savefiles && !$savedatabase) {
            throw new Exception("Invalid options : It is not possible to use 'savefiles = false' and 'savedatabase = false'options in same time.");
        } elseif (!$savefiles) {
            $fileSuffix = self::ARCHIVE_ONLY_DATABASE_SUFFIX;
            $onlyDb = true;
        } elseif (!$savedatabase) {
            $fileSuffix = self::ARCHIVE_ONLY_FILES_SUFFIX;
        } else {
            $fileSuffix = self::ARCHIVE_SUFFIX;
        }
        if (!$onlyDb) {
            $this->writeOutput($output, "=> Preparing list of excluded files", true, $outputFile);
            try {
                $dataFiles = $this->prepareExcludeFiles($extrafiles, $excludedfiles, $inputFile);
            } catch (StopArchiveException $ex) {
                $this->writeOutput($output, "STOP", true, $outputFile);
                return "";
            }
        } else {
            $dataFiles = ['preparedExcludedFiles' => [],'extrafiles' => [], 'excludedfiles' => [], 'onlyChildren' =>[]];
        }

        if ($this->checkIfNeedStop($inputFile)) {
            $this->writeOutput($output, "STOP", true, $outputFile);
            return "";
        }
        // prepare location of zip file

        $archiveFileName = (new DateTime())->format("Y-m-d\\TH-i-s")."$fileSuffix.zip";
        $location = $privatePath.DIRECTORY_SEPARATOR.$archiveFileName;
        if (file_exists($location)) {
            throw new Exception("Zip file already existing !");
        }
        if (file_exists($location)) {
            throw new Exception("Zip file already existing !");
        }
        if ($this->securityController->isWikiHibernated()) {
            throw new Exception(_t('WIKI_IN_HIBERNATION'));
        }

        try {
            // set wiki status
            $this->setWikiStatus();
            // get SQl
            $sqlContent = $savedatabase ? $this->getSQLContent($privatePath) : "";

            if ($this->checkIfNeedStop($inputFile)) {
                $this->unsetWikiStatus();
                $this->writeOutput($output, "STOP", true, $outputFile);
                return "";
            }

            $this->writeOutput($output, "=== Creating zip archive ===", true, $outputFile);
            $this->createZip($location, $dataFiles, $output, $sqlContent, $onlyDb, $anonymousParams, $inputFile, $outputFile);
            if (!file_exists($location)) {
                throw new StopArchiveException("Stop archive : not saved !");
            }

            $this->writeOutput($output, "Archive \"$location\" successfully created !", true, $outputFile);
            $this->writeOutput($output, "END", true, $outputFile);
        } catch (StopArchiveException $ex) {
            $this->unsetWikiStatus();
            $this->writeOutput($output, "STOP", true, $outputFile);
            return "";
        } catch (Throwable $th) {
            $this->unsetWikiStatus();
            throw $th;
        }
        $this->unsetWikiStatus();

        // clean oldest files
        $this->cleanOldestFiles();
        return $location;
    }

    /**
     * check if a recent and valided backup is present
     * @param mixed $token
     * @return bool
     */
    public function hasValidatedBackup($token): bool
    {
        $status = $this->getArchivingStatus();
        // skip backup if not writable, because could be bloking otherwise
        if (!$status['canArchive'] && !$status['privatePathWritable']) {
            return true;
        }
        if (empty($token) || !is_string($token)) {
            return false;
        }
        $privatePath = $this->getPrivateFolder();
        $info = $this->getInfoFromFile($privatePath);
        $result =
            (
                $status['privatePathWritable'] &&
                !empty($info[$token]) &&
                isset($info[$token]['isForcedUpdate']) &&
                $info[$token]['isForcedUpdate'] === true
            );
        foreach ($info as $uid => $data) {
            if (isset($data['isForcedUpdate']) && $data['isForcedUpdate'] === true) {
                $this->cleanUID($uid, $privatePath);
            }
        }
        if ($result && !$status['canArchive'] && $status['archiving']) {
            $this->unsetWikiStatus();
        }
        return $result;
    }

    /**
     * retrieve the current status to archive
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
        $archiveParams = $this->getArchiveParams();
        $callAsync = (isset($archiveParams['call_archive_async']) && is_bool($archiveParams['call_archive_async']))
            ? $archiveParams['call_archive_async']
            : true;
        if ($this->securityController->isWikiHibernated()) {
            switch ($this->params->get('wiki_status')) {
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
        } catch (Exception $th) {
            $privatePathWritable = false;
            $privatePath = "";
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
                    file_put_contents($tmpFileName, "test");
                    if (!file_exists($tmpFileName)) {
                        throw new Exception("Not writable folder");
                    }
                    $content = file_get_contents($tmpFileName);
                    if ($content != "test") {
                        throw new Exception("Bad content");
                    }
                    $notAvailableOnTheInternet = $this->localPrivateFolderNotAvailableOnInternet($privatePath, basename($tmpFileName));
                    unlink($tmpFileName);
                } catch (Throwable $th) {
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
                if (empty($result['stderr']) && !empty($result['stdout']) &&
                    preg_match("/^Hello !(?:\r|\n)+/", $result['stdout'])) {
                    $canExec = true;
                }
            }
        } catch (Throwable $th) {
            $canExec = false;
        }
        // free space
        try {
            $this->assertEnoughtSpace();
        } catch (Throwable $th) {
            $enoughSpace = false;
        }
        $canArchive = (!$archiving && !$hibernated && $privatePathWritable && $notAvailableOnTheInternet && (!$callAsync || $canExec) && $enoughSpace);
        return compact(['canArchive','archiving','hibernated','privatePathWritable','canExec','callAsync','notAvailableOnTheInternet','enoughSpace']);
    }

    /**
     * get a token to force update
     * @return string $token
     */
    public function getForcedUpdateToken(): string
    {
        $status = $this->getArchivingStatus();
        $privatePath = $this->getPrivateFolder();
        $uidData = $this->getUID($privatePath);
        $info = $this->getInfoFromFile($privatePath);
        $uid = $uidData['uid'] ?? "";
        if (empty($uid) || !isset($info[$uid])) {
            return "";
        }

        $info[$uid]['isForcedUpdate'] = true;
        $this->setInfoToFile($info, $privatePath);
        return $uid;
    }


    /**
     * start archive async via CLI or directly if sync
     *
     * @param bool $savefiles
     * @param bool $savedatabase
     * @param array $extrafiles
     * @param array $excludedfiles
     * @param bool $callAsync
     * @return string uid
     */
    public function startArchive(
        bool $savefiles = true,
        bool $savedatabase = true,
        array $extrafiles = [],
        array $excludedfiles = [],
        bool $callAsync = true
    ): string {
        $privatePath = $this->getPrivateFolder();
        $uidData = $this->getUID($privatePath);
        if ($callAsync) {
            $args = [];
            if (!$savefiles) {
                $args[] = "-d";
            }
            if (!$savedatabase) {
                $args[] = "-f";
            }
            if (!empty($extrafiles)) {
                $args[] = "-e";
                $args[] = implode(",", $extrafiles);
            }
            if (!empty($excludedfiles)) {
                $args[] = "-x";
                $args[] = implode(",", $excludedfiles);
            }

            $args[] = "-u";
            $args[] = $uidData['uid'];
            $process = $this->consoleService->startConsoleAsync(
                'core:archive',
                $args
            );
            if (!empty($process)) {
                $this->updatePIDForUID($process->getPid(), $uidData['uid'], $privatePath);
                return $uidData['uid'];
            } else {
                $this->cleanUID($uidData['uid'], $privatePath);
                return '';
            }
        } else {
            $output = "";
            $location = $this->archive($output, $savefiles, $savedatabase, $extrafiles, $excludedfiles, null, $uidData['uid']);
            if (empty($location)) {
                $this->cleanUID($uidData['uid'], $privatePath);
                return '';
            } else {
                return $uidData['uid'];
            }
        }
    }

    /**
     * get the list of archives in a array with information for each one
     * @return array
     */
    public function getArchives(): array
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
                    'type' => $matches[7] ?? "",
                    'size' => filesize("$privatePath/$filename"),
                    'link' => $this->wiki->Href('', "api/archives/$filename")
                ];
            }
        }
        usort($archives, function ($a, $b) {
            return strnatcmp($b['date'], $a['date']);
        });
        return $archives;
    }

    /**
     * get the path to an archive filename
     * @param string $filename
     * @return string $filepath
     */
    public function getFilePath(string $filename): string
    {
        $privatePath = $this->getPrivateFolder();
        // sanitize $filename
        $filename = basename($filename);
        if (substr($filename, -4) != ".zip") {
            return "";
        }
        $filePath = "$privatePath/$filename";
        return (file_exists($filePath) && is_file($filePath)) ? $filePath : "";
    }

    /**
     * delete archives
     * @param array $filesname
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
            $results[$filename] = (substr($filename, -4) == ".zip") && file_exists("$privatePath/$filename") && is_file("$privatePath/$filename");
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
     * get uid status
     * @param string $uid
     * @param bool $forceStarted
     * @return array ['found'=> bool,'running' => bool,'finished'=>bool,'output' => string]
     */
    public function getUIDStatus(string $uid, bool $forceStarted = false): array
    {
        $results = [
            'started' => false,
            'running' => false,
            'finished' => false,
            'stopped' => false,
            'output' => ""
        ];
        $privateFolder = $this->getPrivateFolder();
        $info = $this->getInfoFromFile($privateFolder);
        // clean others uids because it sould not be ever existing
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
                'output' =>$output
            ) = $this->getRunningUIDdata($uid, $info[$uid]);
            $results['running'] = $running;
            $results['finished'] = $finished;
            $results['stopped'] = $stopped;
            if (!$running) {
                $output = preg_replace("/(^Archive \\\")(.*)(\\\" successfully created !(?:\s*END)?\s*$)/m", "$1---$3", $output);
            }
            $results['output'] = $output;
            if (!$results['running']) {
                $this->cleanUID($uid, $privateFolder);
            }
        }
        return $results;
    }

    /**
     * put data in file to stop archive
     * @param string $uid
     * @return bool
     */
    public function stopArchive(string $uid): bool
    {
        if (empty($uid)) {
            return false;
        }
        $info = $this->getInfoFromFile();
        if (!isset($info[$uid]) ||
            empty($info[$uid]['input']) ||
            !is_file($info[$uid]['input'])
        ) {
            return false;
        }
        file_put_contents($info[$uid]['input'], "STOP");
        return true;
    }

    /**
     * check if need to stop archive
     * @param string $inputFile
     * @return bool
     */
    protected function checkIfNeedStop(string $inputFile = ""): bool
    {
        if (empty($inputFile) || !is_file($inputFile)) {
            return false;
        }
        $content = file_get_contents($inputFile);
        if (empty($content)) {
            return false;
        }
        return preg_match("/^STOP.*/", $content);
    }

    /**
     * create the zip file
     * @param string $zipPath
     * @param array $dataFiles
     * @param string|OutputInterface &$output
     * @param string $sqlContent
     * @param bool $onlyDb
     * @param null|array $anonymousParams
     * @param string $inputFile
     * @param string $outputFile
     */
    protected function createZip(
        string $zipPath,
        array $dataFiles,
        &$output,
        string $sqlContent,
        bool $onlyDb = false,
        ?array $anonymousParams = null,
        string $inputFile = "",
        string $outputFile = ""
    ) {
        if (!file_exists('index.php') || !file_exists('wakka.config.php') || !file_exists('composer.json') || !file_exists('composer.lock')) {
            throw new Exception("Can only be started from main directory");
        }
        $pathToArchive = getcwd();
        $pathToArchive = preg_replace("/(\/|\\\\)$/", "", $pathToArchive);
        $dirs = [$pathToArchive];
        $dirnamePathLen = strlen($pathToArchive) ;
        // open file
        $zip = new ZipArchive();
        $resource = $zip->open($zipPath, ZipArchive::CREATE |  ZipArchive::OVERWRITE);
        if ($resource === true) {
            if (!$onlyDb) {
                while (count($dirs)) {
                    $dir = current($dirs);
                    $dir = preg_replace("/(?:\/|\\\\|([^\/\\\\]))$/", "$1", $dir);
                    $baseDirName = preg_replace("/\\\\/", "/", substr($dir, $dirnamePathLen));
                    $baseDirName = preg_replace("/^\//", "", $baseDirName);
                    if (!in_array($baseDirName, $dataFiles['preparedExcludedFiles'])) {
                        if (!empty($baseDirName)) {
                            $this->writeOutput($output, "Adding folder \"$baseDirName\"", true, $outputFile);
                            $zip->addEmptyDir($baseDirName);
                        }
                        $dh = opendir($dir);
                        while (false !== ($file = readdir($dh))) {
                            if ($file != '.' && $file != '..') {
                                $localName = $dir.DIRECTORY_SEPARATOR.$file;
                                $relativeName = (empty($baseDirName) ? "" : "$baseDirName/").$file;
                                if ((
                                    !empty($dataFiles['onlyChildren'][$baseDirName]) &&
                                        in_array($relativeName, $dataFiles['onlyChildren'][$baseDirName])
                                ) || (
                                    empty($dataFiles['onlyChildren'][$baseDirName]) &&
                                        !in_array($relativeName, $dataFiles['preparedExcludedFiles'])
                                )) {
                                    if (empty($baseDirName) && $file == "wakka.config.php") {
                                        $zip->addFromString($relativeName, $this->getWakkaConfigSanitized($dataFiles, $anonymousParams));
                                    } elseif (is_file($localName)) {
                                        $zip->addFile($localName, $relativeName);
                                    } elseif (is_dir($localName)) {
                                        $dirs[] = $dir.DIRECTORY_SEPARATOR.$file;
                                        if ($this->checkIfNeedStop($inputFile)) {
                                            $zip->unchangeAll();
                                            $this->writeOutput($output, "== Closing archive after undoing all changes ==", true, $outputFile);
                                            $zip->close();
                                            throw new StopArchiveException("Stop archive");
                                        }
                                    }
                                }
                            }
                        }
                        closedir($dh);
                    }
                    array_shift($dirs);
                }
            }
            if (!empty($sqlContent)) {
                $this->writeOutput($output, "Adding SQL file", true, $outputFile);
                $zip->addEmptyDir(self::PRIVATE_FOLDER_NAME_IN_ZIP);
                $zip->addFromString(
                    self::PRIVATE_FOLDER_NAME_IN_ZIP."/".self::SQL_FILENAME_IN_PRIVATE_FOLDER_IN_ZIP,
                    $sqlContent
                );
                $this->writeOutput($output, "Adding .htaccess file in folder ".self::PRIVATE_FOLDER_NAME_IN_ZIP, true, $outputFile);

                $zip->addFromString(
                    self::PRIVATE_FOLDER_NAME_IN_ZIP."/.htaccess",
                    "DENY FROM ALL\n"
                );

                $zip->addFromString(
                    self::PRIVATE_FOLDER_NAME_IN_ZIP."/README.md",
                    self::PRIVATE_FOLDER_README_DEFAULT_CONTENT
                );
            }
            $this->writeOutput($output, "Generating zip file", true, $outputFile);
            // register cancel callback if available
            if (method_exists($zip, 'registerCancelCallback')) {
                $zip->registerCancelCallback(function () use ($inputFile) {
                    // 0 will continue process
                    return ($this->checkIfNeedStop($inputFile)) ? -1 : 0;
                });
            }
            // register progress callback if available
            if (method_exists($zip, 'registerProgressCallback')) {
                $zip->registerProgressCallback(0.1, function ($r) use (&$output, $outputFile) {
                    $this->writeOutput($output, "Zip file creation : ".strval(round($r*100, 0))." %", true, $outputFile);
                });
            }
            $zip->close();
        }
    }

    /**
     * prepared exhaustove list of excluded files and folders
     * @param array $extrafiles
     * @param array $excludedfiles
     * @param string $inputFile
     * @return array ['preparedExcludedFiles' => $preparedExcludedFiles, 'extrafiles' => $extrafiles,
     *    'excludedfiles' => $excludedfiles]
     */
    private function prepareExcludeFiles(array $extrafiles, array $excludedfiles, string $inputFile): array
    {
        // merge extra and exlucded files from wakka.config.php
        $archiveParams = $this->getArchiveParams();
        if (!empty($archiveParams[self::KEY_FOR_EXTRAFILES]) &&
            is_array($archiveParams[self::KEY_FOR_EXTRAFILES])) {
            foreach ($archiveParams[self::KEY_FOR_EXTRAFILES] as $path) {
                if (is_string($path) && !in_array($path, $extrafiles)) {
                    $extrafiles[] = $path;
                }
            }
        }
        if (!empty($archiveParams[self::KEY_FOR_EXCLUDEDFILES]) &&
            is_array($archiveParams[self::KEY_FOR_EXCLUDEDFILES])) {
            foreach ($archiveParams[self::KEY_FOR_EXCLUDEDFILES] as $path) {
                if (is_string($path) && !in_array($path, $excludedfiles)) {
                    $excludedfiles[] = $path;
                }
            }
        }
        $extrafiles = $this->sanitizeFileList($extrafiles);
        list($preparedExtraFiles, $onlyChildren) = $this->prepareFileListFromGlob($extrafiles, [], $inputFile);
        $excludedfiles = $this->sanitizeFileList($excludedfiles);
        $excludedfilesWithDefault = $excludedfiles;
        if ($this->checkIfNeedStop($inputFile)) {
            throw new StopArchiveException("Stop archive");
        }
        foreach ($this->sanitizeFileList(self::DEFAULT_EXCLUDED_FILES) as $filePath) {
            if (!in_array($filePath, $excludedfilesWithDefault) && !in_array($filePath, $extrafiles)) {
                $excludedfilesWithDefault[] = $filePath;
            }
        }
        list($preparedExcludedFiles, $onlyChildren)  = $this->prepareFileListFromGlob($excludedfilesWithDefault, $preparedExtraFiles, $inputFile);
        return compact(['preparedExcludedFiles','extrafiles','excludedfiles','onlyChildren']);
    }

    private function sanitizeFileList(array $list): array
    {
        $outputList = [];
        foreach ($list as $filePath) {
            if (is_string($filePath)) {
                $filePath = trim($filePath);
                // remove path containing '/../' to be sure to keep in root folder of the wiki
                // or begining by '/' or 'c:\' to be sure to keep relative to root folder of website
                if (!empty($filePath) && !preg_match("/^(?:\\/|\\\\)|[A-Za-z]:\\\\|(?:\\/|\\\\|^)\\.\\.(?:\\/|\\\\|$)/", $filePath)) {
                    $formattedFilePath = preg_replace("/(\/|\\\\)$/", "", $filePath);
                    if (!in_array($formattedFilePath, $outputList)) {
                        $outputList[] = $formattedFilePath;
                    }
                }
            }
        }
        return $outputList;
    }

    /**
     * @param array $list
     * @param array $ignoreList
     * @param string $inputFile
     * @return array
     */
    private function prepareFileListFromGlob(array $list, array $ignoreList = [], string $inputFile = ""): array
    {
        $outputList = [];
        $onlyChildren = [];
        foreach ($list as $filePath) {
            foreach (glob($filePath) as $filename) {
                $filename = str_replace("\\", "/", $filename);
                if (is_dir($filename)) {
                    $foundChildren = array_filter($ignoreList, function ($path) use ($filename) {
                        return substr($path, 0, strlen($filename)) == $filename;
                    });
                    if (!empty($foundChildren)) {
                        foreach ($foundChildren as $path) {
                            $this->appendChildPathToChildren($onlyChildren, $filename, $path, $inputFile);
                        }
                    }
                }
                if (empty($onlyChildren[$filename]) && !in_array($filename, $outputList) && !in_array($filename, $ignoreList)) {
                    $outputList[] = $filename;
                }
                if ($this->checkIfNeedStop($inputFile)) {
                    throw new StopArchiveException("Stop archive");
                }
            }
        }
        return [$outputList,$onlyChildren];
    }

    /**
     * @param &array $onlyChildren
     * @param string $dirname
     * @param string $path
     * @param string $inputFile
     * @return array
     */
    private function appendChildPathToChildren(array &$onlyChildren, string $dirname, string $path, string $inputFile)
    {
        if (empty($onlyChildren[$dirname])) {
            $onlyChildren[$dirname] = [];
        }
        $currentPath = $path;
        $parentDir = dirname($currentPath);
        while ($parentDir != $dirname) {
            $this->appendChildPathToChildren($onlyChildren, $parentDir, $currentPath, $inputFile);
            $currentPath = $parentDir;
            $parentDir = dirname($currentPath);
            if ($this->checkIfNeedStop($inputFile)) {
                throw new StopArchiveException("Stop archive");
            }
        }
        if (!in_array($currentPath, $onlyChildren[$dirname])) {
            $onlyChildren[$dirname][] = $currentPath;
        }
    }

    private function getPrivateFolder(): string
    {
        $archiveParams = $this->getArchiveParams();
        $folderPath = (
            empty($archiveParams[self::KEY_FOR_PRIVATE_FOLDER]) ||
            !is_string($archiveParams[self::KEY_FOR_PRIVATE_FOLDER])
        )
            ? self::PRIVATE_FOLDER_NAME_IN_ZIP
            : $archiveParams[self::KEY_FOR_PRIVATE_FOLDER];
        if ($folderPath != "%TMP") {
            if (is_dir($folderPath) &&
            $this->canWriteFolder($folderPath)) {
                return preg_replace("/(\/|\\\\)$/", "", $folderPath);
            } else {
                throw new Exception("Not writable ".self::PARAMS_KEY_IN_WAKKA."[".self::KEY_FOR_PRIVATE_FOLDER."]");
            }
        } else {
            $this->createFolder(sys_get_temp_dir().DIRECTORY_SEPARATOR, self::DEFAULT_FOLDER_NAME_IN_TMP);
            $mainTmpDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.self::DEFAULT_FOLDER_NAME_IN_TMP;
            $sanitizeWebsiteName = preg_replace(
                "/-+$/",
                "",
                preg_replace(
                    "/[.\/\\{}\[\]#?&=!;:\\\$<>]/",
                    "-",
                    preg_replace(
                        "/^https?:\/\//",
                        "",
                        $this->params->get('base_url')
                    )
                )
            );
            $this->createFolder($mainTmpDir.DIRECTORY_SEPARATOR, $sanitizeWebsiteName);
            return $mainTmpDir.DIRECTORY_SEPARATOR.$sanitizeWebsiteName;
        }
    }

    private function createFolder(string $basePath, string $path)
    {
        if (file_exists($basePath.$path) && !is_dir($basePath.$path)) {
            throw new Exception("Folder \"$path\" in \"$basePath\" should be a directory !");
        } elseif (!file_exists($basePath.$path)) {
            mkdir($basePath.$path);
        }
    }

    private function getArchiveParams(): array
    {
        if ($this->params->has(self::PARAMS_KEY_IN_WAKKA)) {
            $archiveParams = $this->params->get(self::PARAMS_KEY_IN_WAKKA);
        }
        return (empty($archiveParams) || !is_array($archiveParams)) ? [] : $archiveParams;
    }

    private function canWriteFolder(string $path): bool
    {
        $perms = fileperms($path);
        return (
            (($perms & 0xF000) == 0x4000) && // directory
            ($perms & 0x0080) &&           // writable by owner
            ($perms & 0x0010)              // writable by group
        );
    }

    private function localPrivateFolderNotAvailableOnInternet(string $localPath, string $testFileName): bool
    {
        $isAbsolutePath = (
            in_array(substr($localPath, 0, 1), ["/",DIRECTORY_SEPARATOR]) ||
            (
                DIRECTORY_SEPARATOR == "\\" &&
                (
                    preg_match("/^[A-Za-z]:.*$/", $localPath)
                )
            )
        );
        $basePath = realpath(getcwd());
        $realLocalPath = $isAbsolutePath
            ? realpath($localPath)
            : realpath($basePath . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $localPath));
        $isLocal = (substr($realLocalPath, 0, strlen($basePath)) == $basePath);

        if (!$isLocal) {
            return true;
        }
        if (!file_exists("$localPath/$testFileName")) {
            throw new Exception("\"$localPath/$testFileName\" must exist for tests !");
        }
        $url = preg_replace("/\??$/", "", $this->params->get('base_url'));
        $url .= str_replace(DIRECTORY_SEPARATOR, "/", "$localPath/$testFileName");
        $ct = stream_context_set_default([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
            'http' => [
                'method' => 'HEAD'
            ]
        ]);
        return !strstr(get_headers($url, true, $ct)[0], '200 OK') ;
    }

    /**
     * write text to the output
     * @param string|OutputInterface &$output
     * @param string $text
     * @param bool $newline
     * @param string $outputFile
     */
    private function writeOutput(&$output, string $text, bool $newline = true, string $outputFile = "")
    {
        if (!empty($outputFile) && is_file($outputFile)) {
            file_put_contents($outputFile, $text . ($newline ? "\n" : ""), FILE_APPEND);
        }
        if ($output instanceof OutputInterface) {
            $output->write($text, $newline);
        } elseif (is_string($output)) {
            $output .= $text . ($newline ? "\n" : "");
        } else {
            throw new Exception("\"\$output\" should be string or OutputInterface !");
        }
    }

    /**
     * sanitize wakka.config.php before saving it
     * @param array $dataFiles
     * @param null|array $anonymousParams
     * @return string
     */
    private function getWakkaConfigSanitized(array $dataFiles, ?array $anonymousParams = null): string
    {
        // get wakka.config.php content
        $config = $this->configurationService->getConfiguration('wakka.config.php');
        $config->load();
        if (!isset($config[self::PARAMS_KEY_IN_WAKKA]) ||
            !is_array($config[self::PARAMS_KEY_IN_WAKKA])) {
            $data = [];
        } else {
            $data = $config[self::PARAMS_KEY_IN_WAKKA];
        }
        if (!empty($dataFiles['extrafiles'])) {
            $data[self::KEY_FOR_EXTRAFILES] = $dataFiles['extrafiles'];
        }
        if (!empty($dataFiles['excludedfiles'])) {
            $data[self::KEY_FOR_EXCLUDEDFILES] = $dataFiles['excludedfiles'];
        }
        if (!is_null($anonymousParams)) {
            $data[self::KEY_FOR_ANONYMOUS] = $anonymousParams;
        } elseif (!isset($data[self::KEY_FOR_ANONYMOUS]) || !is_array($data[self::KEY_FOR_ANONYMOUS])) {
            $data[self::KEY_FOR_ANONYMOUS] = self::DEFAULT_PARAMS_TO_ANONYMIZE;
        }
        $config[self::PARAMS_KEY_IN_WAKKA] = $data;

        $config = $this->setDefaultValuesRecursive($config[self::PARAMS_KEY_IN_WAKKA][self::KEY_FOR_ANONYMOUS], $config);
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
        $config = $this->configurationService->getConfiguration('wakka.config.php');
        $config->load();
        $config['wiki_status'] = 'archiving';
        $this->configurationService->write($config);
    }
    protected function unsetWikiStatus()
    {
        $config = $this->configurationService->getConfiguration('wakka.config.php');
        $config->load();
        unset($config['wiki_status']);
        $this->configurationService->write($config);
    }

    /**
     * extract sql content
     * @param string $privatePath
     * @return string $sqlContent
     * @throws Exception
     * @throws Throwable
     */
    protected function getSQLContent(string $privatePath): string
    {
        $hostname = $this->params->get('mysql_host');
        $this->assertParamIsNotEmptyString('mysql_host', $hostname);

        $databasename = $this->params->get('mysql_database');
        $this->assertParamIsNotEmptyString('mysql_database', $databasename);

        $tablePrefix = $this->params->get('table_prefix');
        $this->assertParamIsNotEmptyString('table_prefix', $tablePrefix);

        $username = $this->params->get('mysql_user');
        $this->assertParamIsString('mysql_user', $username);

        $password = $this->params->get('mysql_password');
        $this->assertParamIsString('mysql_password', $password);

        $resultFile = tempnam($privatePath, self::SQL_FILENAME_IN_PRIVATE_FOLDER_IN_ZIP);
        try {
            $results = $this->consoleService->findAndStartExecutableSync(
                "mysqldump",
                [
                    "--host=$hostname",
                    "--user=$username",
                    "--password=$password",
                    "--result-file=".realpath($resultFile),
                    $databasename, // databasename
                    "{$tablePrefix}users", // tables
                    "{$tablePrefix}pages", // tables
                    "{$tablePrefix}nature", // tables
                    "{$tablePrefix}triples", // tables
                    "{$tablePrefix}acls", // tables
                    "{$tablePrefix}links", // tables
                    "{$tablePrefix}referrers", // tables
                ], // args
                "", // subfolder
                ('\\' === DIRECTORY_SEPARATOR ? ["c:\\xampp\\mysql\\bin\\"] : ["/usr/bin/","/usr/local/bin/"]), // extraDirsWhereSearch
                60 // timeoutInSec
            );
        } catch (Throwable $th) {
            if (file_exists($resultFile)) {
                unlink($resultFile);
            }
            throw $th;
        }

        // get content
        if (file_exists($resultFile)) {
            $sqlContent = file_get_contents($resultFile);
            unlink($resultFile);
        }
        if (empty($sqlContent)) {
            throw new Exception("SQL not exported");
        }
        if (!empty($results)) {
            // it could be occur an error
            // throw new Exception("Error when exporting SQL :".implode(',',array_map(function ($result){return $result['stderr'] ?? '';},$results)));
        }
        return $sqlContent;
    }

    /**
     * assert param is a not empty string
     * @param string $name
     * @param mixed $param
     * @throws Exception
     */
    protected function assertParamIsNotEmptyString(string $name, $param)
    {
        if (empty($param)) {
            throw new Exception("'$name' should not be empty in 'wakka.config.php'");
        }
        $this->assertParamIsString($name, $param);
    }

    /**
     * assert param is a string
     * @param string $name
     * @param mixed $param
     * @throws Exception
     */
    protected function assertParamIsString(string $name, $param)
    {
        if (!is_string($param)) {
            throw new Exception("'$name' should be a string in 'wakka.config.php'");
        }
    }

    /**
     * check if there is enought free space before archive (size of files + custom + 300 Mo)
     * @throws Exception
     */
    protected function assertEnoughtSpace()
    {
        $extimatedNeededSpace = $this->estimateFilesAndCustomFolders();
        $extimatedNeededSpace += 300 * 1024 * 1024;

        $freeSpace = disk_free_space(realpath(getcwd()));
        if ($freeSpace < $extimatedNeededSpace) {
            throw new Exception("Not enough free space for a new archive!");
        }
    }

    /**
     * estimate size of files and custom folder
     * @return int $bytes
     */
    protected function estimateFilesAndCustomFolders(): int
    {
        $bytes = 0;
        $bytes += $this->folderSize("files");
        $bytes += $this->folderSize("custom");

        return $bytes;
    }

    /**
     * recursive method
     * @param string $folderPath
     * @return int $bytes
     */
    private function folderSize(string $folderPath): int
    {
        $contents = array_filter(scandir($folderPath), function ($path) {
            return !in_array($path, ['.','..']);
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
     * remove oldest files to keep only 10 files
     */
    private function cleanOldestFiles()
    {
        $archivesToDelete = $this->archivesToDelete();
        if (!empty($archivesToDelete)) {
            $this->deleteArchives($archivesToDelete);
        }
    }

    /**
     * extract list of archives to delete
     * @param bool $beforeArchive
     * @return array $files
     */
    public function archivesToDelete(bool $beforeArchive = false): array
    {
        $archives =  $this->getArchives();
        $nbFilesToRemove = count($archives) - self::MAX_NB_FILES + ($beforeArchive ? 1 : 0);
        if ($nbFilesToRemove > 0) {
            // there are files to remove
            // keep at least one file more than 1 day and other more than 2 days to prevent
            // full deletion if attack on api
            $indexesToRemove = range(self::MAX_NB_FILES, count($archives)-1);
            if (!empty($indexesToRemove)) {
                $archivesIndexesMoreThan2days = $this->getIndexesMoreThanxdays($archives, 2);
                $archivesIndexesMoreThan1day = $this->getIndexesMoreThanxdays($archives, 1);

                $notDeletedArchivesMoreThan2Days = array_diff($archivesIndexesMoreThan2days, $indexesToRemove);
                if (!empty($archivesIndexesMoreThan2days) && empty($notDeletedArchivesMoreThan2Days)) {
                    // we should kept the most recent 2 days old
                    $indexesToRemove = array_diff($indexesToRemove, [min($archivesIndexesMoreThan2days)]);
                    if (empty($indexesToRemove)) {
                        $indexesToRemove = [min($archivesIndexesMoreThan2days)-1];
                    } else {
                        array_unshift($indexesToRemove, min($indexesToRemove)-1);
                    }
                }
                $archivesIndexesBetween1and2days = array_diff($archivesIndexesMoreThan1day, $archivesIndexesMoreThan2days);
                $notDeletedArchivesBetween1and2days = array_diff($archivesIndexesBetween1and2days, $indexesToRemove);
                if (!empty($archivesIndexesBetween1and2days) && empty($notDeletedArchivesBetween1and2days)) {
                    // we should kept the most recent 1 day old
                    $indexesToRemove = array_diff($indexesToRemove, [min($archivesIndexesBetween1and2days)]);
                    if (empty($indexesToRemove)) {
                        $indexesToRemove = [min($archivesIndexesBetween1and2days)-1];
                    } else {
                        array_unshift($indexesToRemove, min($indexesToRemove)-1);
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
        $nowMinusXDays = (new DateTime())->sub(new DateInterval("P{$days}D"));
        foreach ($archives as $key => $archive) {
            // check the the last file is aged more than x days
            $fileDateTime = (new DateTime())
                ->setDate($archive['year'], $archive['month'], $archive['day'])
                ->setTime($archive['hours'], $archive['minutes'], $archive['seconds'], 0);
            if ($fileDateTime->diff($nowMinusXDays)->invert ==0 // current file date is before - x days
            ) {
                $indexes[] = $key;
            }
        }

        return $indexes;
    }

    /**
     * get content of info.json file from privatePath
     * @param string $privateFolder
     * @return mixed
     */
    private function getInfoFromFile(string $privateFolder = "")
    {
        if (empty($privateFolder)) {
            $privateFolder = $this->getPrivateFolder();
        }
        if (!file_exists("$privateFolder/info.json")) {
            file_put_contents("$privateFolder/info.json", "{}");
        }
        $fileContent = file_get_contents("$privateFolder/info.json");
        $content = json_decode($fileContent, true);
        return (empty($content) || !is_array($content)) ? [] : $content;
    }


    /**
     * set content to info.json file from privatePath
     * @param mixed $content
     * @param string $privateFolder
     */
    private function setInfoToFile($content, string $privateFolder = "")
    {
        if (empty($privateFolder)) {
            $privateFolder = $this->getPrivateFolder();
        }
        file_put_contents("$privateFolder/info.json", json_encode($content));
    }

    /**
     * get a unique id for the current PID with input and output files created
     * @param string $privateFolder
     * @return null|array ['uid' => string, 'input' => string, 'output' => string]
     */
    private function getUID(string $privateFolder = ""): ?array
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
        file_put_contents($input, "");
        file_put_contents($output, "");

        $info[$uid] = [
            'input' => realpath($input),
            'output' => realpath($output),
        ];
        $this->setInfoToFile($info, $privateFolder);
        return compact(['uid','input','output']);
    }

    /**
     * savePID for uid in info.json
     * @param string $pid
     * @param string $uid
     * @param string $privateFolder
     */
    private function updatePIDForUID(string $pid, string $uid, string $privateFolder = "")
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
     * clean uid info in info.json
     * @param string $uid
     * @param string $privateFolder
     */
    private function cleanUID(string $uid, string $privateFolder = "")
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

    /** check id current uid is running
     * @param string $uid
     * @param array $info
     * @return array ['running' => bool, 'finished' => bool, 'stopped' => bool,'output' => string]
     */
    private function getRunningUIDdata(string $uid, array $info): array
    {
        if (!is_file($info['output'])) {
            return false;
        }
        $output = file_get_contents($info['output']);
        $running = !empty(trim($output));
        $finished = !$running ? false : (
            preg_match("/(END|STOP)\s*$/", $output)
            ? true
            : false
        );
        $stopped = preg_match("/(STOP)\s*$/", $output);
        if ($finished) {
            $running = false;
        }

        return compact(['running','finished','stopped','output']);
    }
}
