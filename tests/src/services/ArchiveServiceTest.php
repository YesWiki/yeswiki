<?php

namespace YesWiki\Test\Core\Commands;

use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Admin\Service\ArchiveService;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Kernel\Service\ConsoleService;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\YesWikiRuntime;

require_once 'tests/YesWikiTestCase.php';

#[CoversMethod(ArchiveService::class, '__construct')]
#[CoversMethod(ArchiveService::class, 'archive')]
#[CoversMethod(ArchiveService::class, 'setWikiStatus')]
class ArchiveServiceTest extends YesWikiTestCase
{
    /**
     * @return array{wiki: YesWikiRuntime, archiveService: ArchiveService}
     */
    public function testArchiveServiceExisting(): array
    {
        $wiki = $this->getWiki();
        $this->assertTrue($wiki->services->has(ArchiveService::class));

        return ['wiki' => $wiki, 'archiveService' => $wiki->services->get(ArchiveService::class)];
    }

    /**
     * @param list<string>                                                $foldersToInclude
     * @param list<string>                                                $foldersToExclude
     * @param list<string>                                                $filesToFind
     * @param array<string, mixed>|null                                   $wakkaContent
     * @param array{wiki: YesWikiRuntime, archiveService: ArchiveService} $services
     */
    #[Depends('testArchiveServiceExisting')]
    #[DataProvider('archiveProvider')]
    public function testArchive(
        bool $savefiles,
        bool $savedatabase,
        array $foldersToInclude,
        array $foldersToExclude,
        string $locationSuffix,
        ?int $nbFiles,
        array $filesToFind,
        ?array $wakkaContent,
        array $services
    ): void {
        $this->assertTrue(
            $services['wiki']->services->get(DbService::class)->dialect()->supportsDump(),
            'every supported driver must be able to export its table structure'
        );

        $output = '';
        $location = $services['archiveService']->archive(
            $output,
            $savefiles,
            $savedatabase,
            $foldersToInclude,
            $foldersToExclude,
        );
        $data = $this->getDataFromLocation($location, $services['wiki']);
        $error = $data['error'] ?? '';
        $this->assertEmpty($error, "There is an error : $error");
        $this->assertArrayNotHasKey('error', $data);
        $this->assertMatchesRegularExpression('/^.*' . preg_quote(constant("\\YesWiki\\Admin\\Service\\ArchiveService::{$locationSuffix}") . '.zip', '/') . '$/', $location);
        if (!is_null($nbFiles) && $nbFiles > -1) {
            $this->assertArrayHasKey('files', $data);
            foreach ($filesToFind as $path) {
                $this->assertContains($path, $data['files']);
            }
            $this->assertCount($nbFiles, $data['files']);
        }
        if (!is_null($wakkaContent)) {
            $this->assertArrayHasKey('wakkaContent', $data);
            $this->checkWakkaContent($wakkaContent, $data['wakkaContent']);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function archiveProvider(): array
    {
        $defaultFoldersToInclude = constant('\\YesWiki\\Admin\\Service\\ArchiveService::FOLDERS_TO_INCLUDE');
        $defaultFoldersToExclude = constant('\\YesWiki\\Admin\\Service\\ArchiveService::FOLDERS_TO_EXCLUDE');

        return [
            'archive only root files' => [
                true, false, [], $defaultFoldersToInclude,
                'ARCHIVE_ONLY_FILES_SUFFIX', -1,
                ['yeswiki.config.php'],
                ['archive' => ['foldersToInclude' => $defaultFoldersToInclude, 'foldersToExclude' => array_merge($defaultFoldersToExclude, $defaultFoldersToInclude)]],
            ],
            'archive only root files with database' => [
                true, true, [], $defaultFoldersToInclude,
                'ARCHIVE_SUFFIX', -1,
                ['yeswiki.config.php', 'private', 'private/backups', 'private/backups/.htaccess', 'private/backups/README.md', 'private/backups/content.sql'],
                ['archive' => ['foldersToInclude' => $defaultFoldersToInclude, 'foldersToExclude' => array_merge($defaultFoldersToExclude, $defaultFoldersToInclude)]],
            ],
            'archive only database' => [
                false, true, [], [],
                'ARCHIVE_ONLY_DATABASE_SUFFIX', 5,
                ['private', 'private/backups', 'private/backups/.htaccess', 'private/backups/README.md', 'private/backups/content.sql'],
                null,
            ],
        ];
    }

    /**
     * retrieve data from location delete the zip file because only for tests.
     *
     * @return array<string, mixed> $data
     */
    private function getDataFromLocation(string $location, YesWikiRuntime $wiki): array
    {
        $data = [];
        if (!empty($location) && file_exists($location)) {
            if (!preg_match("/^.*\.zip$/", $location)) {
                $data['error'] = "\"\$location\" (\"$location\") is not a zip file !";
            } else {
                $zip = new \ZipArchive();
                if ($zip->open($location) !== true) {
                    $data['error'] = "\"\$location\" (\"$location\") is not openable !";
                } else {
                    do {
                        $tmpFolderName = 'tmp_folder_to_delete_' . md5((string)time());
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
                            if ($dh === false) {
                                array_shift($dirs);
                                continue;
                            }
                            while (false !== ($file = readdir($dh))) {
                                if ($file != '.' && $file != '..') {
                                    if (!in_array("$dir/$file", ['.', '..'])) {
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
                        $files = array_map(function (string $path) use ($tmpFolderName): string {
                            $stripped = preg_replace("/^cache(?:\/|\\\\)" . preg_quote($tmpFolderName, '/') . "(?:\/|\\\\)/", '', $path) ?? $path;

                            return str_replace('\\', '/', $stripped);
                        }, $files);
                        $data['files'] = $files;

                        if (file_exists("cache/$tmpFolderName/yeswiki.config.php") && is_file("cache/$tmpFolderName/yeswiki.config.php")) {
                            $configurationService = $wiki->services->get(ConfigurationService::class);
                            $config = $configurationService->getConfiguration("cache/$tmpFolderName/yeswiki.config.php");
                            $config->load();
                            $data['wakkaContent'] = $config->__get('_parameters');
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

    private function recursiveDelete(string $path): void
    {
        if (!in_array(basename($path), ['.', '..']) && !preg_match("/(?:^|\/|\\\\)\.{1,2}(?:^|\/|\\\\)/", $path)) {
            if (file_exists($path)) {
                if (is_dir($path)) {
                    $dh = opendir($path);
                    if ($dh !== false) {
                        while (false !== ($file = readdir($dh))) {
                            $this->recursiveDelete("$path/$file");
                        }
                        closedir($dh);
                    }
                    rmdir($path);
                } elseif (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }

    /**
     * @param mixed $contentDefinition what the fixture says the archived config must contain
     * @param mixed $contentToCheck    the matching part of the archived config
     */
    private function checkWakkaContent($contentDefinition, $contentToCheck): void
    {
        if (is_array($contentDefinition)) {
            $this->assertIsArray($contentToCheck);
            foreach ($contentDefinition as $key => $value) {
                if (is_integer($key) && is_scalar($value)) {
                    $this->assertContains($value, $contentToCheck);
                } else {
                    $this->assertArrayHasKey($key, $contentToCheck);
                    $this->checkWakkaContent($contentDefinition[$key], $contentToCheck[$key]);
                }
            }
        } elseif (is_scalar($contentDefinition)) {
            $this->assertEquals($contentDefinition, $contentToCheck);
        }
    }

    /**
     * @param array{wiki: YesWikiRuntime, archiveService: ArchiveService} $services
     */
    #[Depends('testArchiveServiceExisting')]
    #[Depends('testArchive')]
    #[DataProvider('notInParallelProvider')]
    public function testNotArchiveInParallel(
        string $status,
        array $services
    ): void {
        $params = $services['wiki']->services->get(ParameterBagInterface::class);
        $configService = $services['wiki']->services->get(ConfigurationService::class);
        $consoleService = $services['wiki']->services->get(ConsoleService::class);
        $previousStatus = $params->has('wiki_status') ? $params->get('wiki_status') : null;
        $previousStatus = is_string($previousStatus) ? $previousStatus : '';
        $this->setWikiStatus($configService, $status);

        $defaultFoldersToInclude = constant('\\YesWiki\\Admin\\Service\\ArchiveService::FOLDERS_TO_INCLUDE');

        $results = $consoleService->startConsoleSync('core:archive', [
            '-f',
            '-x', implode(',', $defaultFoldersToInclude),
        ]);
        if (empty($previousStatus)) {
            $this->unsetWikiStatus($configService);
        } else {
            $this->setWikiStatus($configService, $previousStatus);
        }
        $this->assertNotNull($results, 'the archive command produced no output at all');
        $atLeastOneStdErr = false;
        foreach ($results as $result) {
            if (!empty($result['stderr'])) {
                $atLeastOneStdErr = true;
            }
        }
        $this->assertTrue($atLeastOneStdErr, "No error in \"ArchiveService\" when \"wiki_status\" = \"$status\" ; results: " . json_encode($results));
    }

    protected function setWikiStatus(ConfigurationService $configurationService, string $status = 'archiving'): void
    {
        $config = $configurationService->getConfiguration('yeswiki.config.php');
        $config->load();
        $config['wiki_status'] = $status;
        $configurationService->write($config);
    }

    protected function unsetWikiStatus(ConfigurationService $configurationService): void
    {
        $config = $configurationService->getConfiguration('yeswiki.config.php');
        $config->load();
        unset($config['wiki_status']);
        $configurationService->write($config);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function notInParallelProvider(): array
    {
        return [
            'archiving' => ['archiving'],
            'hibernate' => ['hibernate'],
            'updating' => ['updating'],
        ];
    }

    /**
     * @param array<string, mixed>|null                                   $hideConfigValuesParam
     * @param array<string, mixed>                                        $wakkaContent
     * @param array{wiki: YesWikiRuntime, archiveService: ArchiveService} $services
     */
    #[Depends('testArchiveServiceExisting')]
    #[Depends('testArchive')]
    #[DataProvider('hideConfigValuesProvider')]
    public function testhideConfigValuesParams(
        bool $paramsFromWakka,
        ?array $hideConfigValuesParam,
        array $wakkaContent,
        array $services
    ): void {
        $params = $services['wiki']->services->get(ParameterBagInterface::class);
        $configService = $services['wiki']->services->get(ConfigurationService::class);
        $consoleService = $services['wiki']->services->get(ConsoleService::class);

        $defaultFoldersToInclude = constant('\\YesWiki\\Admin\\Service\\ArchiveService::FOLDERS_TO_INCLUDE');

        $consoleParams = [
            '-f',
            '-x', implode(',', $defaultFoldersToInclude),
        ];

        $previoushideConfigValuesParams = $this->getHideConfigValuesParam($configService);
        if ($paramsFromWakka) {
            if (is_null($hideConfigValuesParam)) {
                $this->unsetHideConfigValuesParam($configService);
            } else {
                $this->setHideConfigValuesParam($configService, $hideConfigValuesParam);
            }
        } else {
            $encoded = json_encode($hideConfigValuesParam);
            $this->assertNotFalse($encoded, 'the hideConfigValues fixture must be encodable');
            $consoleParams[] = '-a';
            $consoleParams[] = $encoded;
        }
        $results = $consoleService->startConsoleSync('core:archive', $consoleParams);
        if (!is_null($previoushideConfigValuesParams)) {
            $this->setHideConfigValuesParam($configService, $previoushideConfigValuesParams);
        } else {
            $this->unsetHideConfigValuesParam($configService);
        }

        $location = '';
        $this->assertNotNull($results, 'the archive command produced no output at all');
        foreach ($results as $result) {
            if (!empty($result['stdout'])) {
                if (preg_match("/^Archive \\\"(.*)\\\" successfully created !\s*END\s*$/m", $result['stdout'], $matches)) {
                    $location = $matches[1];
                }
                break;
            }
        }

        $this->assertNotEmpty($location, 'Bad format of stdout; results: ' . json_encode($results));
        $this->assertTrue(is_file($location), 'Extracted location is not a file !');
        $data = $this->getDataFromLocation($location, $services['wiki']);
        $error = $data['error'] ?? '';
        $this->assertEmpty($error, "There is an error : $error");
        $this->assertArrayNotHasKey('error', $data);
        $this->assertArrayHasKey('wakkaContent', $data);
        $this->checkWakkaContent($wakkaContent, $data['wakkaContent']);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function getHideConfigValuesParam(ConfigurationService $configurationService): ?array
    {
        $config = $configurationService->getConfiguration('yeswiki.config.php');
        $config->load();
        $archiveParams = $config['archive'] ?? [];

        return $archiveParams['hideConfigValues'] ?? null;
    }

    /**
     * @param array<string, mixed> $hideConfigValuesParam
     */
    protected function setHideConfigValuesParam(ConfigurationService $configurationService, array $hideConfigValuesParam): void
    {
        $config = $configurationService->getConfiguration('yeswiki.config.php');
        $config->load();
        $archiveParams = $config['archive'] ?? [];
        $archiveParams['hideConfigValues'] = $hideConfigValuesParam;
        $config['archive'] = $archiveParams;
        $configurationService->write($config);
    }

    protected function unsetHideConfigValuesParam(ConfigurationService $configurationService): void
    {
        $config = $configurationService->getConfiguration('yeswiki.config.php');
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

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function hideConfigValuesProvider(): array
    {
        return [
            'default' => [
                true,
                null,
                [
                    'db_host' => '',
                    'db_database' => '',
                    'db_user' => '',
                    'db_password' => '',
                    'archive' => [
                        'hideConfigValues' => [
                            'db_host' => '',
                            'db_database' => '',
                            'db_user' => '',
                            'db_password' => '',
                            'contact_smtp_host' => '',
                            'contact_smtp_user' => '',
                            'contact_smtp_pass' => '',
                            'api_allowed_keys' => [],
                        ],
                    ],
                ],
            ],
            'specific' => [
                true,
                [
                    'db_host' => '',
                    'db_database' => '',
                    'db_user' => '',
                    'db_password' => '',
                    'custom_key' => '',
                ],
                [
                    'db_host' => '',
                    'db_database' => '',
                    'db_user' => '',
                    'db_password' => '',
                    'archive' => [
                        'hideConfigValues' => [
                            'db_host' => '',
                            'db_database' => '',
                            'db_user' => '',
                            'db_password' => '',
                            'custom_key' => '',
                        ],
                    ],
                ],
            ],
            'specific via command line' => [
                false,
                [
                    'db_host' => '',
                    'db_database' => '',
                    'db_user' => '',
                    'db_password' => '',
                    'custom_key_2' => '',
                    'custom_key_3' => '',
                ],
                [
                    'db_host' => '',
                    'db_database' => '',
                    'db_user' => '',
                    'db_password' => '',
                    'archive' => [
                        'hideConfigValues' => [
                            'db_host' => '',
                            'db_database' => '',
                            'db_user' => '',
                            'db_password' => '',
                            'custom_key_2' => '',
                            'custom_key_3' => '',
                        ],
                    ],
                ],
            ],
        ];
    }
}
