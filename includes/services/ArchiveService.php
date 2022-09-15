<?php

namespace YesWiki\Core\Service;

use DateTime;
use Exception;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Security\Controller\SecurityController;
use YesWiki\Core\Entity\ConfigurationFile;
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
        "cache"
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
     * @throws Exception
     */
    public function archive(
        &$output,
        bool $savefiles = true,
        bool $savedatabase = true,
        array $extrafiles = [],
        array $excludedfiles = []
    ) {
        $this->writeOutput($output, "=== Checking free space ===");
        $this->assertEnoughtSpace();
        $this->writeOutput($output, "There is enough free space.");

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
        $dataFiles = $this->prepareExcludeFiles($extrafiles, $excludedfiles);

        // prepare location of zip file

        $archiveFileName = (new DateTime())->format("Y-m-d\\TH-i-s")."$fileSuffix.zip";
        $privatePath = $this->getPrivateFolder();
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

            // create zip passing SQL <= TODO
            $this->writeOutput($output, "=== Creating zip archive ===");
            $this->createZip($location, $dataFiles, $output, $sqlContent, $onlyDb);

            $this->writeOutput($output, "Archive \"$location\" successfully created !");
        } catch (Throwable $th) {
            $this->unsetWikiStatus();
            throw $th;
        }
        $this->unsetWikiStatus();
        return $location;
    }

    /**
     * create the zip file
     * @param string $zipPath
     * @param array $dataFiles
     * @param string|OutputInterface &$output
     * @param string $sqlContent
     * @param bool $onlyDb
     * TODO manage wakka.config.php
     */
    protected function createZip(
        string $zipPath,
        array $dataFiles,
        &$output,
        string $sqlContent,
        bool $onlyDb = false
    ) {
        $pathToArchive = dirname(__FILE__, 3); // includes/services/../../
        $pathToArchive = preg_replace("/(\/|\\\\)$/", "", $pathToArchive);
        $dirs = [$pathToArchive];
        $dirnamePathLen = strlen($pathToArchive) ;
        // open file
        $zip = new ZipArchive;
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
                            $this->writeOutput($output, "Adding folder \"$baseDirName\"");
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
                                        $zip->addFromString($relativeName, $this->getWakkaConfigSanitized($dataFiles));
                                    } elseif (is_file($localName)) {
                                        $zip->addFile($localName, $relativeName);
                                    } elseif (is_dir($localName)) {
                                        $dirs[] = $dir.DIRECTORY_SEPARATOR.$file;
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
                $this->writeOutput($output, "Adding SQL file");
                $zip->addEmptyDir(self::PRIVATE_FOLDER_NAME_IN_ZIP);
                $zip->addFromString(
                    self::PRIVATE_FOLDER_NAME_IN_ZIP."/".self::SQL_FILENAME_IN_PRIVATE_FOLDER_IN_ZIP,
                    $sqlContent
                );
                $this->writeOutput($output, "Adding .htaccess file in folder ".self::PRIVATE_FOLDER_NAME_IN_ZIP);
                
                $zip->addFromString(
                    self::PRIVATE_FOLDER_NAME_IN_ZIP."/.htaccess",
                    "DENY FROM ALL\n"
                );
                
                $zip->addFromString(
                    self::PRIVATE_FOLDER_NAME_IN_ZIP."/README.md",
                    self::PRIVATE_FOLDER_README_DEFAULT_CONTENT
                );
            }
            $this->writeOutput($output, "Generating zip file");
            $zip->close();
        }
    }

    /**
     * prepared exhaustove list of excluded files and folders
     * @param array $extrafiles
     * @param array $excludedfiles
     * @return array ['preparedExcludedFiles' => $preparedExcludedFiles, 'extrafiles' => $extrafiles,
     *    'excludedfiles' => $excludedfiles]
     */
    private function prepareExcludeFiles(array $extrafiles, array $excludedfiles): array
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
        list($preparedExtraFiles, $onlyChildren) = $this->prepareFileListFromGlob($extrafiles);
        $excludedfiles = $this->sanitizeFileList($excludedfiles);
        $excludedfilesWithDefault = $excludedfiles;
        foreach ($this->sanitizeFileList(self::DEFAULT_EXCLUDED_FILES) as $filePath) {
            if (!in_array($filePath, $excludedfilesWithDefault) && !in_array($filePath, $extrafiles)) {
                $excludedfilesWithDefault[] = $filePath;
            }
        }
        list($preparedExcludedFiles, $onlyChildren)  = $this->prepareFileListFromGlob($excludedfilesWithDefault, $preparedExtraFiles);
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

    private function prepareFileListFromGlob(array $list, array $ignoreList = []): array
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
                            $this->appendChildPathToChildren($onlyChildren, $filename, $path);
                        }
                    }
                }
                if (empty($onlyChildren[$filename]) && !in_array($filename, $outputList) && !in_array($filename, $ignoreList)) {
                    $outputList[] = $filename;
                }
            }
        }
        return [$outputList,$onlyChildren];
    }

    private function appendChildPathToChildren(array &$onlyChildren, string $dirname, string $path)
    {
        if (empty($onlyChildren[$dirname])) {
            $onlyChildren[$dirname] = [];
        }
        $currentPath = $path;
        $parentDir = dirname($currentPath);
        while ($parentDir != $dirname) {
            $this->appendChildPathToChildren($onlyChildren, $parentDir, $currentPath);
            $currentPath = $parentDir;
            $parentDir = dirname($currentPath);
        }
        if (!in_array($currentPath, $onlyChildren[$dirname])) {
            $onlyChildren[$dirname][] = $currentPath;
        }
    }

    private function getPrivateFolder(): string
    {
        $archiveParams = $this->getArchiveParams();
        if (!empty($archiveParams[self::KEY_FOR_PRIVATE_FOLDER]) &&
            is_string($archiveParams[self::KEY_FOR_PRIVATE_FOLDER]) &&
            is_dir($archiveParams[self::KEY_FOR_PRIVATE_FOLDER]) &&
            $this->canWriteFolder($archiveParams[self::KEY_FOR_PRIVATE_FOLDER])) {
            // TODO check if the private folder is included in root path
            // then check if this private folder is not accessible from internet
            return preg_replace("/(\/|\\\\)$/", "", $archiveParams[self::KEY_FOR_PRIVATE_FOLDER]);
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
            throw new Exception("Folder \"$path\" in tmp should be a directory !");
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

    /**
     * write text to the output
     * @param string|OutputInterface &$output
     * @param string $text
     * @param bool $newline
     */
    private function writeOutput(&$output, string $text, bool $newline = true)
    {
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
     * @return string
     */
    private function getWakkaConfigSanitized(array $dataFiles): string
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
        if (!isset($data[self::KEY_FOR_ANONYMOUS]) || !is_array($data[self::KEY_FOR_ANONYMOUS])) {
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

        $resultFile = tempnam($privatePath, 'tmp_sql_file_to_delete');
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
            ('\\' === DIRECTORY_SEPARATOR ? ["c:\\xampp\\mysql\\bin\\"]: []), // extraDirsWhereSearch
            60 // timeoutInSec
        );

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
}
