<?php

namespace YesWiki\Test\Core\Commands;

use Exception;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Core\Service\ArchiveService;
use YesWiki\Core\Service\ConfigurationService;
use YesWiki\Core\Service\ConsoleService;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\Wiki;
use ZipArchive;

require_once 'tests/YesWikiTestCase.php';

class ArchiveServiceTest extends YesWikiTestCase
{
    /**
     * @covers ArchiveService::__construct
     * @return array ['wiki'=> $wiki,'archiveService' => $archiveService]
     */
    public function testArchiveServiceExisting(): array
    {
        $wiki = $this->getWiki();
        $this->assertTrue($wiki->services->has(ArchiveService::class));
        return ['wiki' => $wiki,'archiveService' => $wiki->services->get(ArchiveService::class)];
    }


    /**
     * @depends testArchiveServiceExisting
     * @dataProvider archiveProvider
     * @covers ArchiveService::archive
     * @param bool $savefiles
     * @param bool $savedatabase
     * @param array $extrafiles
     * @param array $excludedfiles
     * @param string $locationSuffix
     * @param null|int $nbFiles
     * @param array $filesToFind
     * @param null|array $wakkaContent
     * @param array $services [$wiki,$archiveService]
     */
    public function testArchive(
        bool $savefiles,
        bool $savedatabase,
        array $extrafiles,
        array $excludedfiles,
        string $locationSuffix,
        ?int $nbFiles,
        array $filesToFind,
        ?array $wakkaContent,
        array $services
    ) {
        $output = "";
        $location = $services['archiveService']->archive(
            $output,
            $savefiles,
            $savedatabase,
            $extrafiles,
            $excludedfiles,
        );
        $data = $this->getDataFromLocation($location, $services['wiki']);
        $error = $data['error'] ?? "";
        $this->assertEmpty($error, "There is an error : $error");
        $this->assertArrayNotHasKey('error', $data);
        $this->assertMatchesRegularExpression("/^.*".preg_quote(constant("\\YesWiki\\Core\\Service\\ArchiveService::{$locationSuffix}").".zip", "/")."$/", $location);
        if (!is_null($nbFiles) && $nbFiles > -1) {
            $this->assertArrayHasKey('files', $data);
            foreach ($filesToFind as $path) {
                $this->assertContains($path, $data['files']);
            }
            $this->assertCount($nbFiles, $data['files']);
            if (!is_null($wakkaContent)) {
                $this->assertArrayHasKey('wakkaContent', $data);
                $this->checkWakkaContent($wakkaContent, $data['wakkaContent']);
            }
        }
    }

    public function archiveProvider()
    {
        return [
            'archive only wakka.config.php' => [
                'savefiles' => true,
                'savedatabase' => false,
                'extrafiles' => ['wakka.config.php'],
                'excludedfiles' => ['*','.*','/var/tmp','../../*','c:\\'],
                'locationSuffix' => "ARCHIVE_ONLY_FILES_SUFFIX",
                'nbFiles' => 1,
                'filesToFind' => ['wakka.config.php'],
                'wakkaContent' => [
                    'archive' => [
                        'extrafiles' => ['wakka.config.php'],
                        'excludedfiles' => ['*','.*'],
                    ],
                ]
            ],
            'archive only wakka.config.php with database' => [
                'savefiles' => true,
                'savedatabase' => true,
                'extrafiles' => ['wakka.config.php'],
                'excludedfiles' => ['*','.*'],
                'locationSuffix' => "ARCHIVE_SUFFIX",
                'nbFiles' => 6,
                'filesToFind' => [
                    'wakka.config.php',
                    'private',
                    'private/backups',
                    'private/backups/.htaccess',
                    'private/backups/README.md',
                    'private/backups/content.sql',
                ],
                'wakkaContent' => [
                    'archive' => [
                        'extrafiles' => ['wakka.config.php'],
                        'excludedfiles' => ['*','.*'],
                    ],
                ]
            ],
            'archive only database' => [
                'savefiles' => false,
                'savedatabase' => true,
                'extrafiles' => [],
                'excludedfiles' => [],
                'locationSuffix' => "ARCHIVE_ONLY_DATABASE_SUFFIX",
                'nbFiles' => 5,
                'filesToFind' => [
                    'private',
                    'private/backups',
                    'private/backups/.htaccess',
                    'private/backups/README.md',
                    'private/backups/content.sql'
                ],
                'wakkaContent' => null
            ],
            'archive only wakka.config.php and tools/bazar/config.yaml' => [
                'savefiles' => true,
                'savedatabase' => false,
                'extrafiles' => ['wakka.config.php','tools/bazar/config.yaml'],
                'excludedfiles' => ['*','.*'],
                'locationSuffix' => "ARCHIVE_ONLY_FILES_SUFFIX",
                'nbFiles' => 4,
                'filesToFind' => ['wakka.config.php','tools/bazar/config.yaml'],
                'wakkaContent' => [
                    'archive' => [
                        'extrafiles' => ['wakka.config.php','tools/bazar/config.yaml'],
                        'excludedfiles' => ['*','.*'],
                    ],
                ]
            ],
        ];
    }

    /**
     * retrieve data from location
     * delete the zip file because only for tests
     * @param string $location
     * @param Wiki $wiki
     * @return array $data
     */
    private function getDataFromLocation(string $location, Wiki $wiki): array
    {
        $data = [];
        if (!empty($location) && file_exists($location)) {
            if (!preg_match("/^.*\.zip$/", $location)) {
                $data['error'] = "\"\$location\" (\"$location\") is not a zip file !";
            } else {
                $zip = new ZipArchive();
                if ($zip->open($location) !== true) {
                    $data['error'] = "\"\$location\" (\"$location\") is not openable !";
                } else {
                    // create tmp folder in cache
                    do {
                        $tmpFolderName = "tmp_folder_to_delete_".md5(time());
                    } while (file_exists("cache/$tmpFolderName"));
                    if (!$zip->extractTo("cache/$tmpFolderName")) {
                        $data['error'] = "\"\$location\" (\"$location\") is not extractable !";
                        $zip->close();
                    } else {
                        $zip->close();
                        $files = [];
                        $dirs = ["cache/$tmpFolderName"];
                        while (count($dirs)) {
                            $dir = current($dirs);
                            $dh = opendir($dir);
                            while (false !== ($file = readdir($dh))) {
                                if ($file != '.' && $file != '..') {
                                    if (!in_array("$dir/$file", ['.','..'])) {
                                        if (is_file("$dir/$file") || is_dir("$dir/$file")) {
                                            if (!in_array("$dir/$file", $files)) {
                                                $files[] = "$dir/$file";
                                            }
                                        }
                                        if (is_dir("$dir/$file") && !in_array("$dir/$file", $dirs)) {
                                            $dirs[] = "$dir/$file";
                                        }
                                    }
                                }
                            }
                            closedir($dh);
                            array_shift($dirs);
                        }
                        $files = array_map(function ($path) use ($tmpFolderName) {
                            return str_replace("\\", "/", preg_replace("/^cache(?:\/|\\\\)".preg_quote($tmpFolderName, "/")."(?:\/|\\\\)/", "", $path));
                        }, $files);
                        $data['files'] = $files;

                        // wakka content
                        if (file_exists("cache/$tmpFolderName/wakka.config.php") && is_file("cache/$tmpFolderName/wakka.config.php")) {
                            $configurationService = $wiki->services->get(ConfigurationService::class);
                            $config = $configurationService->getConfiguration("cache/$tmpFolderName/wakka.config.php");
                            $config->load();
                            $data['wakkaContent'] = $config->_parameters;
                            unset($config);
                        }
                        $this->recursiveDelete("cache/$tmpFolderName");
                    }
                }
            }
            unlink($location);
        } else {
            $data['error'] = "\"\$location\" (\"$location\") is not a file !";
        }
        return $data;
    }

    private function recursiveDelete(string $path)
    {
        if (!in_array(basename($path), ['.','..']) && !preg_match("/(?:^|\/|\\\\)\.{1,2}(?:^|\/|\\\\)/", $path)) {
            if (file_exists($path)) {
                if (is_dir($path)) {
                    $dh = opendir($path);
                    while (false !== ($file = readdir($dh))) {
                        $this->recursiveDelete("$path/$file");
                    }
                    closedir($dh);
                    rmdir($path);
                } elseif (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }

    /**
     * @param mixed $contentDefinition
     * @param mixed $contentToCheck
     */
    private function checkWakkaContent($contentDefinition, $contentToCheck)
    {
        if (is_array($contentDefinition)) {
            $this->assertIsArray($contentToCheck);
            foreach ($contentDefinition as $key => $value) {
                $this->assertArrayHasKey($key, $contentToCheck);
                $this->checkWakkaContent($contentDefinition[$key], $contentToCheck[$key]);
            }
        } elseif (is_scalar($contentDefinition)) {
            $this->assertEquals($contentDefinition, $contentToCheck);
        }
    }

    /**
     * @depends testArchiveServiceExisting
     * @depends testArchive
     * @dataProvider notInParallelProvider
     * @covers ArchiveService::setWikiStatus
     * @param string $status
     * @param array $services [$wiki,$archiveService]
     */
    public function testNotArchiveInParallel(
        string $status,
        array $services
    ) {
        $params = $services['wiki']->services->get(ParameterBagInterface::class);
        $configService = $services['wiki']->services->get(ConfigurationService::class);
        $consoleService = $services['wiki']->services->get(ConsoleService::class);
        $previousStatus = $params->has('wiki_status') ? $params->get('wiki_status') : null;
        $this->setWikiStatus($configService, $status);
        $results = $consoleService->startConsoleSync("core:archive", [
            "-f",
            "-x","*,.*",
            "-e","wakka.config.php"
        ]);
        if (empty($previousStatus)) {
            $this->unsetWikiStatus($configService);
        } else {
            $this->setWikiStatus($configService, $previousStatus);
        }
        $atLeastOneStdErr = false;
        foreach ($results as $result) {
            if (isset($result['stderr'])) {
                $atLeastOneStdErr = true;
            }
        }
        $this->assertTrue($atLeastOneStdErr, "No error in \"ArchiveService\" when \"wiki_status\" = \"$status\" ; results: ".json_encode($results));
    }

    protected function setWikiStatus(ConfigurationService $configurationService, string $status = 'archiving')
    {
        $config = $configurationService->getConfiguration('wakka.config.php');
        $config->load();
        $config['wiki_status'] = $status;
        $configurationService->write($config);
    }
    protected function unsetWikiStatus(ConfigurationService $configurationService)
    {
        $config = $configurationService->getConfiguration('wakka.config.php');
        $config->load();
        unset($config['wiki_status']);
        $configurationService->write($config);
    }

    public function notInParallelProvider()
    {
        return [
            'archiving' => ['status' => 'archiving'],
            'hibernate' => ['status' => 'hibernate'],
            'updating' => ['status' => 'updating'],
        ];
    }

    /**
     * @depends testArchiveServiceExisting
     * @depends testArchive
     * @dataProvider hideConfigValuesProvider
     * @param bool $paramsFromWakka
     * @param null|array $hideConfigValuesParam
     * @param array $wakkaContent
     * @param array $services [$wiki,$archiveService]
     */
    public function testhideConfigValuesParams(
        bool $paramsFromWakka,
        ?array $hideConfigValuesParam,
        array $wakkaContent,
        array $services
    ) {
        $params = $services['wiki']->services->get(ParameterBagInterface::class);
        $configService = $services['wiki']->services->get(ConfigurationService::class);
        $consoleService = $services['wiki']->services->get(ConsoleService::class);

        $consoleParams = [
            "-f",
            "-x","*,.*",
            "-e","wakka.config.php"
        ];

        $previoushideConfigValuesParams = $this->getHideConfigValuesParam($configService);
        if ($paramsFromWakka) {
            if (is_null($hideConfigValuesParam)) {
                $this->unsetHideConfigValuesParam($configService);
            } else {
                $this->setHideConfigValuesParam($configService, $hideConfigValuesParam);
            }
        } else {
            $consoleParams[] = "-a";
            $consoleParams[] = json_encode($hideConfigValuesParam);
        }
        $results = $consoleService->startConsoleSync("core:archive", $consoleParams);
        if (!is_null($previoushideConfigValuesParams)) {
            $this->setHideConfigValuesParam($configService, $previoushideConfigValuesParams);
        } else {
            $this->unsetHideConfigValuesParam($configService);
        }

        $location = null;
        foreach ($results as $result) {
            if (isset($result['stdout'])) {
                if (preg_match("/^Archive \\\"(.*)\\\" successfully created !\s*END\s*$/m", $result['stdout'], $matches)) {
                    $location = $matches[1];
                }
                break;
            }
        }

        $this->assertNotEmpty($location, "Bad format of stdout");
        $this->assertTrue(is_file($location), "Extracted location is not a file !");
        $data = $this->getDataFromLocation($location, $services['wiki']);
        $error = $data['error'] ?? "";
        $this->assertEmpty($error, "There is an error : $error");
        $this->assertArrayNotHasKey('error', $data);
        $this->assertArrayHasKey('wakkaContent', $data);
        $this->checkWakkaContent($wakkaContent, $data['wakkaContent']);
    }

    protected function getHideConfigValuesParam(ConfigurationService $configurationService): ?array
    {
        $config = $configurationService->getConfiguration('wakka.config.php');
        $config->load();
        $archiveParams = $config['archive'] ?? [];
        return $archiveParams['hideConfigValues'] ?? null;
    }

    protected function setHideConfigValuesParam(ConfigurationService $configurationService, array $hideConfigValuesParam)
    {
        $config = $configurationService->getConfiguration('wakka.config.php');
        $config->load();
        $archiveParams = $config['archive'] ?? [];
        $archiveParams['hideConfigValues'] = $hideConfigValuesParam;
        $config['archive'] = $archiveParams;
        $configurationService->write($config);
    }

    protected function unsetHideConfigValuesParam(ConfigurationService $configurationService)
    {
        $config = $configurationService->getConfiguration('wakka.config.php');
        $config->load();
        if (isset($config['archive'])) {
            $archiveParams = $config['archive'];
            unset($archiveParams['hideConfigValues']);
            if (empty($archiveParams)) {
                unset($config['archive']);
            } else {
                $config['archive'] = $archiveParams;
            }
        }
        $configurationService->write($config);
    }

    public function hideConfigValuesProvider()
    {
        return [
            'default' => [
                'paramsFromWakka' => true,
                'hideConfigValuesParam' => null,
                'wakkaContent' => [
                    'mysql_host' => '',
                    'mysql_database' => '',
                    'mysql_user' => '',
                    'mysql_password' => '',
                    'archive' => [
                        'hideConfigValues' => [
                            'mysql_host' => '',
                            'mysql_database' => '',
                            'mysql_user' => '',
                            'mysql_password' => '',
                            'contact_smtp_host' => '',
                            'contact_smtp_user' => '',
                            'contact_smtp_pass' => '',
                            'api_allowed_keys' => []
                        ],
                    ],
                ]
            ],
            'specific' => [
                'paramsFromWakka' => true,
                'hideConfigValuesParam' => [
                    'mysql_host' => '',
                    'mysql_database' => '',
                    'mysql_user' => '',
                    'mysql_password' => '',
                    'custom_key' => ''
                ],
                'wakkaContent' => [
                    'mysql_host' => '',
                    'mysql_database' => '',
                    'mysql_user' => '',
                    'mysql_password' => '',
                    'archive' => [
                        'hideConfigValues' => [
                            'mysql_host' => '',
                            'mysql_database' => '',
                            'mysql_user' => '',
                            'mysql_password' => '',
                            'custom_key' => ''
                        ],
                    ],
                ]
            ],
            'specific via command line' => [
                'paramsFromWakka' => false,
                'hideConfigValuesParam' => [
                    'mysql_host' => '',
                    'mysql_database' => '',
                    'mysql_user' => '',
                    'mysql_password' => '',
                    'custom_key_2' => '',
                    'custom_key_3' => ''
                ],
                'wakkaContent' => [
                    'mysql_host' => '',
                    'mysql_database' => '',
                    'mysql_user' => '',
                    'mysql_password' => '',
                    'archive' => [
                        'hideConfigValues' => [
                            'mysql_host' => '',
                            'mysql_database' => '',
                            'mysql_user' => '',
                            'mysql_password' => '',
                            'custom_key_2' => '',
                            'custom_key_3' => ''
                        ],
                    ],
                ]
            ],
        ];
    }
}
