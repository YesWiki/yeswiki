<?php

namespace YesWiki\Test\Core\Commands;

use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Admin\Service\ArchiveService;
use YesWiki\Core\YesWikiRuntime;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Kernel\Service\ConsoleService;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\WikiSettings;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\Test\Kernel\QueueTestHolders;

require_once 'tests/YesWikiTestCase.php';
require_once 'tests/src/Kernel/QueueTestHolders.php';

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
        $this->assertNotContains('yeswiki.config.php', $data['files'] ?? [], 'an archive holds no configuration file (replicas 20)');
        $this->assertNotContains('private/.env', $data['files'] ?? [], 'nor the host\'s settings');
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
                [],
                null,
            ],
            'archive only root files with database' => [
                true, true, [], $defaultFoldersToInclude,
                'ARCHIVE_SUFFIX', -1,
                ['private', 'private/backups', 'private/backups/.htaccess', 'private/backups/README.md', 'private/backups/content.sql', 'private/backups/restore.json'],
                null,
            ],
            'archive only database' => [
                false, true, [], [],
                'ARCHIVE_ONLY_DATABASE_SUFFIX', 6,
                ['private', 'private/backups', 'private/backups/.htaccess', 'private/backups/README.md', 'private/backups/content.sql', 'private/backups/restore.json'],
                null,
            ],
        ];
    }

    /** @param array{wiki: YesWikiRuntime, archiveService: ArchiveService} $services */
    #[Depends('testArchiveServiceExisting')]
    public function testOnlyFoldersKeepsEverythingElseOutOfTheArchive(array $services): void
    {
        $output = '';
        $location = $services['archiveService']->archive($output, true, false, [], [], null, '', ['custom']);
        $data = $this->getDataFromLocation($location, $services['wiki']);

        $this->assertArrayNotHasKey('error', $data);
        $this->assertContains('custom', $data['files']);
        foreach (['files', 'vendor', 'src', 'themes', 'private'] as $folder) {
            $this->assertNotContains($folder, $data['files'], "'$folder' should have been left out");
        }
        foreach ($data['files'] as $path) {
            if (!str_contains($path, '/')) {
                continue;
            }
            $this->assertStringStartsWith('custom/', $path, "'$path' should have been left out");
        }
    }

    /** @param array{wiki: YesWikiRuntime, archiveService: ArchiveService} $services */
    #[Depends('testArchiveServiceExisting')]
    public function testAFolderSharingAPrefixWithAWhitelistedOneIsLeftOut(array $services): void
    {
        $method = new \ReflectionMethod(ArchiveService::class, 'shouldIncludeFolder');
        $include = fn (string $folder): bool => $method->invoke($services['archiveService'], $folder, ['custom'], []);

        $this->assertTrue($include('custom'));
        $this->assertTrue($include('custom/lang'));
        $this->assertFalse($include('custom-jdn'));
        $this->assertFalse($include('custom-jdn/lang'));
        $this->assertFalse($include('customs'));
    }

    /** @param array{wiki: YesWikiRuntime, archiveService: ArchiveService} $services */
    #[Depends('testArchiveServiceExisting')]
    public function testAnEmptyOnlyFoldersArchivesTheWholeWikiRatherThanNothing(array $services): void
    {
        $method = new \ReflectionMethod(ArchiveService::class, 'generateListRootFolders');
        $whitelist = $method->invoke($services['archiveService'], 'white', [], []);

        foreach (['files', 'custom', 'src', 'themes'] as $folder) {
            $this->assertContains($folder, $whitelist, "'$folder' should still be archived");
        }
        $this->assertSame(['custom'], $method->invoke($services['archiveService'], 'white', [], ['custom/', '../etc', '']));
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

                        foreach (['sql' => 'private/backups/content.sql', 'info' => 'private/backups/restore.json'] as $part => $name) {
                            if (is_file("cache/$tmpFolderName/$name")) {
                                $data[$part] = (string)file_get_contents("cache/$tmpFolderName/$name");
                            }
                        }
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
        // `archiving` is a backup under way only while that run holds the archive lock: without it, the next reader lifts the status.
        $letGo = $status === 'archiving' ? QueueTestHolders::wikiLockHeldElsewhere($params, HibernationService::ARCHIVING_LOCK) : null;
        $this->setWikiStatus($configService, $status);

        $defaultFoldersToInclude = constant('\\YesWiki\\Admin\\Service\\ArchiveService::FOLDERS_TO_INCLUDE');

        try {
            $results = $consoleService->startConsoleSync('core:archive', [
                '-f',
                '-x', implode(',', $defaultFoldersToInclude),
            ]);
        } finally {
            if (empty($previousStatus)) {
                $this->unsetWikiStatus($configService);
            } else {
                $this->setWikiStatus($configService, $previousStatus);
            }
            if ($letGo !== null) {
                $this->assertSame(0, $letGo());
            }
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
     * Two backups started at once both find the wiki `running`; the one that reaches the archive lock second is refused before it sets any status, so it lifts none either.
     *
     * @param array{wiki: YesWikiRuntime, archiveService: ArchiveService} $services
     */
    #[Depends('testArchiveServiceExisting')]
    public function testAnArchiveRefusesToStartWhileAnotherHoldsTheLock(array $services): void
    {
        $configService = $services['wiki']->services->get(ConfigurationService::class);
        $before = $this->statusInTheConfig($configService);
        $letGo = QueueTestHolders::wikiLockHeldElsewhere($services['wiki']->services->get(ParameterBagInterface::class), HibernationService::ARCHIVING_LOCK);
        $output = '';
        $location = '';
        $refused = null;

        try {
            $location = $services['archiveService']->archive($output, true, false, [], ArchiveService::FOLDERS_TO_INCLUDE);
        } catch (\Exception $exception) {
            $refused = $exception;
        } finally {
            $this->assertSame(0, $letGo());
            if ($location !== '' && is_file($location)) {
                unlink($location);
            }
        }

        $this->assertNotNull($refused, 'a backup ran while another held the archive lock: ' . (is_string($output) ? $output : ''));
        $this->assertSame(_t('ARCHIVE_ALREADY_RUNNING'), $refused->getMessage());
        $this->assertSame($before, $this->statusInTheConfig($configService), 'no status was set, and none lifted');
    }

    /** What the wiki's config file says of its status now, null when it says nothing. */
    private function statusInTheConfig(ConfigurationService $configurationService): ?string
    {
        $config = $configurationService->getConfiguration('yeswiki.config.php');
        $config->load();
        $status = $config['wiki_status'] ?? null;

        return $status === null ? null : (string)$status;
    }

    /**
     * The settings an archive is told to hide are emptied in its dump, the rest travel with it (replicas 20).
     *
     * @param array<string, mixed>|null                                   $hidden
     * @param list<string>|null                                           $emptied null for those the wiki is configured with
     * @param array{wiki: YesWikiRuntime, archiveService: ArchiveService} $services
     */
    #[Depends('testArchiveServiceExisting')]
    #[Depends('testArchive')]
    #[DataProvider('hideConfigValuesProvider')]
    public function testhideConfigValuesParams(?array $hidden, ?array $emptied, array $services): void
    {
        $emptied ??= array_values(array_intersect(['api_allowed_keys', 'custom_key'], array_keys($services['archiveService']->hiddenSettings())));
        $settings = $services['wiki']->services->get(WikiSettings::class);
        $before = $settings->all();
        $settings->store(['api_allowed_keys' => ['kept-out-of-backups'], 'custom_key' => 'kept-out-too', 'yeswiki_name_hint' => 'travels-with-it']);
        try {
            $output = '';
            $location = $services['archiveService']->archive($output, false, true, [], [], $hidden);
        } finally {
            $settings->store(array_intersect_key($before, array_flip(['api_allowed_keys', 'custom_key', 'yeswiki_name_hint'])), array_values(array_diff(['api_allowed_keys', 'custom_key', 'yeswiki_name_hint'], array_keys($before))));
        }

        $data = $this->getDataFromLocation($location, $services['wiki']);
        $this->assertArrayNotHasKey('error', $data);
        $sql = (string)($data['sql'] ?? '');
        $this->assertStringContainsString('travels-with-it', $sql, 'a setting nobody hides goes with the database');
        foreach (['api_allowed_keys' => 'kept-out-of-backups', 'custom_key' => 'kept-out-too'] as $key => $value) {
            if (in_array($key, $emptied, true)) {
                $this->assertStringNotContainsString($value, $sql, "$key is emptied in the dump");
            } else {
                $this->assertStringContainsString($value, $sql, "$key is not hidden, so it stays");
            }
        }
        $info = json_decode((string)($data['info'] ?? ''), true);
        $this->assertIsArray($info);
        $this->assertSame($emptied, array_values(array_intersect($emptied, $info['hideConfigValues'] ?? [])), 'the archive says which ones it emptied, for the restore to keep the local values');
    }

    /** @param array{wiki: YesWikiRuntime, archiveService: ArchiveService} $services */
    #[Depends('testArchiveServiceExisting')]
    #[Depends('testArchive')]
    public function testTheQueueSecretNeverLeavesWithABackup(array $services): void
    {
        $output = '';
        $location = $services['archiveService']->archive($output, true, true, ['private'], [], ['db_password' => '']);

        $data = $this->getDataFromLocation($location, $services['wiki']);
        $this->assertArrayNotHasKey('error', $data);
        $this->assertNotContains('private/.env', $data['files'] ?? [], 'the host\'s settings, the queue secret among them, never leave with a backup');
        $secret = (string)($services['wiki']->config['queue_consume_secret'] ?? '');
        if ($secret !== '') {
            $this->assertStringNotContainsString($secret, (string)($data['sql'] ?? ''));
        }
    }

    /** @return array<string, array<int, mixed>> */
    public static function hideConfigValuesProvider(): array
    {
        return [
            'configured' => [null, null],
            'specific' => [['db_password' => '', 'custom_key' => ''], ['custom_key']],
        ];
    }
}
