<?php

namespace YesWiki\Test\Core\Commands;

use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Core\Service\ArchiveFilename;
use YesWiki\Core\Service\ArchiveService;
use YesWiki\Core\Service\ConfigurationService;
use YesWiki\Core\Service\ConsoleService;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\DumpRewriter;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\Wiki;

require_once 'tests/YesWikiTestCase.php';

#[CoversMethod(ArchiveService::class, '__construct')]
#[CoversMethod(ArchiveService::class, 'archive')]
#[CoversMethod(ArchiveService::class, 'setWikiStatus')]
class ArchiveServiceTest extends YesWikiTestCase
{
    public function testArchiveServiceExisting(): array
    {
        $wiki = $this->getWiki();
        $this->assertTrue($wiki->services->has(ArchiveService::class));

        return ['wiki' => $wiki, 'archiveService' => $wiki->services->get(ArchiveService::class)];
    }

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
    ) {
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
        $this->assertMatchesRegularExpression('/^.*' . preg_quote(constant("\\YesWiki\\Core\\Service\\ArchiveService::{$locationSuffix}") . '.zip', '/') . '$/', $location);
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

    public static function archiveProvider()
    {
        if (!class_exists(ArchiveService::class, false)) {
            include_once 'includes/services/ArchiveService.php';
        }
        $defaultFoldersToInclude = constant('\\YesWiki\\Core\\Service\\ArchiveService::FOLDERS_TO_INCLUDE');
        $defaultFoldersToExclude = constant('\\YesWiki\\Core\\Service\\ArchiveService::FOLDERS_TO_EXCLUDE');

        return [
            'archive only root files' => [
                true, false, [], $defaultFoldersToInclude,
                'ARCHIVE_ONLY_FILES_SUFFIX', -1,
                ['wakka.config.php'],
                ['archive' => ['foldersToInclude' => $defaultFoldersToInclude, 'foldersToExclude' => array_merge($defaultFoldersToExclude, $defaultFoldersToInclude)]],
            ],
            'archive only root files with database' => [
                true, true, [], $defaultFoldersToInclude,
                'ARCHIVE_SUFFIX', -1,
                ['wakka.config.php', 'private', 'private/backups', 'private/backups/.htaccess', 'private/backups/README.md', 'private/backups/content.sql', 'private/backups/restore.json'],
                ['archive' => ['foldersToInclude' => $defaultFoldersToInclude, 'foldersToExclude' => array_merge($defaultFoldersToExclude, $defaultFoldersToInclude)]],
            ],
            'archive only database' => [
                false, true, [], [],
                'ARCHIVE_ONLY_DATABASE_SUFFIX', 6,
                ['private', 'private/backups', 'private/backups/.htaccess', 'private/backups/README.md', 'private/backups/content.sql', 'private/backups/restore.json'],
                null,
            ],
        ];
    }

    #[Depends('testArchiveServiceExisting')]
    public function testADumpKeepsNullsAndQuotesEveryOtherValue(array $services)
    {
        $db = $services['wiki']->services->get(DbService::class);
        $table = trim($db->prefixTable('testdumpvalues'));
        $db->query("CREATE TABLE `$table` (`id` int NOT NULL, `text` mediumtext, `number` decimal(10,2) DEFAULT NULL)");
        $db->query("INSERT INTO `$table` (`id`, `text`, `number`) VALUES (1, 'kept', 12.34), (2, NULL, NULL)");

        try {
            $sql = $services['wiki']->services->get(DbService::class)->getSQLContentBackupMethod()['sql'] ?? '';

            $this->assertStringContainsString("('1', 'kept', '12.34')", $sql, 'every value is quoted, whatever its type');
            $this->assertStringContainsString("('2', NULL, NULL)", $sql, 'a NULL stays a NULL');
        } finally {
            $db->query("DROP TABLE IF EXISTS `$table`");
        }
    }

    /**
     * Two other wikis sharing the database, as they are found in the wild: one with the core
     * tables under a longer prefix, one whose tables a newer YesWiki names differently.
     *
     * @return array<string,string> the statement that creates each, keyed by table name
     */
    private static function neighbourTables(string $prefix): array
    {
        $tables = [];
        foreach (['pages', 'acls', 'links', 'nature', 'referrers', 'triples', 'users'] as $coreTable) {
            $tables["{$prefix}youpi__$coreTable"] = 'CREATE TABLE `%s` (`id` int NOT NULL, `tag` varchar(191) NOT NULL)';
        }
        $tables["{$prefix}ecto__pages"] = 'CREATE TABLE `%s` (`id` int NOT NULL, `tag` varchar(191) NOT NULL, `metadata` longtext DEFAULT NULL CHECK (json_valid(`metadata`)))';
        $tables["{$prefix}ecto__triples"] = 'CREATE TABLE `%s` (`id` int NOT NULL, `tag` varchar(191) NOT NULL)';
        $tables["{$prefix}ecto__search_index"] = 'CREATE TABLE `%s` (`id` int NOT NULL, `tag` varchar(191) NOT NULL)';

        return $tables;
    }

    /**
     * @param array<string,string> $tables
     */
    private static function createNeighbours(DbService $db, array $tables): void
    {
        foreach ($tables as $table => $creation) {
            $db->query("DROP TABLE IF EXISTS `$table`");
            $db->query(sprintf($creation, $table));
            $db->query("INSERT INTO `$table` (`id`, `tag`) VALUES (1, 'THE NEIGHBOUR')");
        }
    }

    /**
     * @param array<string,string> $tables
     */
    private static function dropNeighbours(DbService $db, array $tables): void
    {
        foreach (array_keys($tables) as $table) {
            $db->query("DROP TABLE IF EXISTS `$table`");
        }
    }

    #[Depends('testArchiveServiceExisting')]
    public function testADumpHoldsThisWikisTablesAndNoOther(array $services)
    {
        $db = $services['wiki']->services->get(DbService::class);
        $prefix = trim($db->prefixTable(''));
        $neighbours = self::neighbourTables($prefix);
        $extension = "{$prefix}myextension";

        try {
            self::createNeighbours($db, $neighbours);
            $db->query("DROP TABLE IF EXISTS `$extension`");
            $db->query("CREATE TABLE `$extension` (`id` int NOT NULL)");

            $sql = $db->getSQLContentBackupMethod()['sql'] ?? '';
            preg_match_all('/^CREATE TABLE `([^`]+)`/m', $sql, $matches);
            $dumped = $matches[1];

            foreach (DumpRewriter::CORE_TABLES as $coreTable) {
                $this->assertContains("$prefix$coreTable", $dumped, "the wiki's own $coreTable has to be in its backup");
            }
            $this->assertContains($extension, $dumped, 'a table of an extension of this wiki belongs to it');
            foreach (array_keys($neighbours) as $table) {
                $this->assertNotContains($table, $dumped, "$table belongs to another wiki");
            }
            $this->assertCount(count(DumpRewriter::CORE_TABLES) + 1, $dumped, 'nothing else was swept in');
        } finally {
            self::dropNeighbours($db, $neighbours);
            $db->query("DROP TABLE IF EXISTS `$extension`");
        }
    }

    #[Depends('testArchiveServiceExisting')]
    public function testRestoringABackupLeavesTheOtherWikisOfTheDatabaseAlone(array $services)
    {
        $db = $services['wiki']->services->get(DbService::class);
        $prefix = trim($db->prefixTable(''));
        $neighbours = self::neighbourTables($prefix);
        $countPages = fn () => $db->loadSingle('SELECT COUNT(*) AS n FROM ' . $db->prefixTable('pages'))['n'];
        $countTriples = fn () => $db->loadSingle('SELECT COUNT(*) AS n FROM ' . $db->prefixTable('triples'))['n'];

        $output = '';
        $location = $services['archiveService']->archive($output, false, true);
        $this->assertFileExists($location);
        $pagesBefore = $countPages();
        $triplesBefore = $countTriples();

        try {
            self::createNeighbours($db, $neighbours);
            $services['archiveService']->restoreArchive(basename($location), false, true, false);

            $this->assertSame($pagesBefore, $countPages(), 'the wiki got its own pages back');
            $this->assertSame($triplesBefore, $countTriples());
            foreach (array_keys($neighbours) as $table) {
                $rows = $db->loadAll("SELECT `tag` FROM `$table`");
                $this->assertCount(1, $rows, "$table still stands, with its row");
                $this->assertSame('THE NEIGHBOUR', $rows[0]['tag']);
            }
        } finally {
            self::dropNeighbours($db, $neighbours);
            @unlink($location);
        }
    }

    #[Depends('testArchiveServiceExisting')]
    public function testRestoringAnOlderBackupDoesNotBringBackAnotherWikisTables(array $services)
    {
        $db = $services['wiki']->services->get(DbService::class);
        $prefix = trim($db->prefixTable(''));
        $neighbours = self::neighbourTables($prefix);

        $output = '';
        $location = $services['archiveService']->archive($output, false, true);
        $this->assertFileExists($location);

        try {
            self::createNeighbours($db, $neighbours);

            // an archive as older versions took them: the neighbours' tables swept in
            $zip = new \ZipArchive();
            $this->assertTrue($zip->open($location) === true);
            $entry = ArchiveService::PRIVATE_FOLDER_NAME_IN_ZIP . '/' . ArchiveService::SQL_FILENAME_IN_PRIVATE_FOLDER_IN_ZIP;
            $swept = $zip->getFromName($entry);
            foreach ($neighbours as $table => $creation) {
                $swept .= "\n" . sprintf($creation, $table) . ";\n"
                    . "INSERT INTO `$table` (`id`, `tag`) VALUES (2, 'FROM THE OLD BACKUP');\n";
            }
            $zip->addFromString($entry, $swept);
            $zip->close();

            $services['archiveService']->restoreArchive(basename($location), false, true, false);

            foreach (array_keys($neighbours) as $table) {
                $rows = $db->loadAll("SELECT `tag` FROM `$table`");
                $this->assertCount(1, $rows, "$table was not restored over");
                $this->assertSame('THE NEIGHBOUR', $rows[0]['tag'], 'the live row of the other wiki stands');
            }
        } finally {
            self::dropNeighbours($db, $neighbours);
            @unlink($location);
        }
    }

    #[Depends('testArchiveServiceExisting')]
    public function testRestoringAFullBackupRemovesWhatItDoesNotContain(array $services)
    {
        $root = $this->makeTemporaryTree([
            'custom/kept.txt' => 'in the backup',
            'custom/gone.txt' => 'added since',
            'custom/gonedir/deep.txt' => 'added since',
            'untouched/keep.txt' => 'a folder the backup knows nothing about',
        ]);
        $zipPath = "$root/backup.zip";
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE);
        $zip->addFromString('custom/kept.txt', 'in the backup');
        $zip->close();

        try {
            $zip->open($zipPath);
            $this->callProtected($services['archiveService'], 'removeFilesAbsentFromArchive', [$zip, $root, microtime(true) + 30]);
            $zip->close();

            $this->assertFileExists("$root/custom/kept.txt");
            $this->assertFileDoesNotExist("$root/custom/gone.txt");
            $this->assertDirectoryDoesNotExist("$root/custom/gonedir");
            $this->assertFileExists("$root/untouched/keep.txt", 'a folder the backup does not contain is left alone');
        } finally {
            $this->removeTemporaryTree($root);
        }
    }

    #[Depends('testArchiveServiceExisting')]
    public function testRestoringABackupKeepsWhatTiesThisInstallationToItsServer(array $services)
    {
        $root = $this->makeTemporaryTree([]);
        $configFile = "$root/wakka.config.php";
        file_put_contents($configFile, "<?php\n\n\$wakkaConfig = " . var_export([
            'wakka_name' => 'this wiki',
            'mysql_database' => 'mine',
            'base_url' => 'https://mine.example/?',
            'table_prefix' => 'mine_',
            'archive' => ['privatePath' => '/here/private/backups'],
            'timezone' => 'Europe/Paris',
        ], true) . ";\n");

        $zipPath = "$root/backup.zip";
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE);
        $zip->addFromString('wakka.config.php', "<?php\n\n\$wakkaConfig = " . var_export([
            'wakka_name' => 'the backed up wiki',
            'mysql_database' => '',
            'base_url' => 'https://theirs.example/?',
            'table_prefix' => 'theirs_',
            'archive' => ['privatePath' => '/there/private/backups'],
            'default_language' => 'en',
        ], true) . ";\n");
        $zip->close();

        try {
            $zip->open($zipPath);
            $this->callProtected($services['archiveService'], 'restoreConfiguration', [$zip, $configFile]);
            $zip->close();

            $restored = [];
            eval(str_replace(['<?php', '$wakkaConfig'], ['', '$restored'], file_get_contents($configFile)));

            $this->assertSame('the backed up wiki', $restored['wakka_name'], 'settings come back from the backup');
            $this->assertSame('en', $restored['default_language'], 'settings only the backup has come back too');
            $this->assertArrayNotHasKey('timezone', $restored, 'settings the backup does not have are dropped');
            $this->assertSame('mine', $restored['mysql_database'], 'the database of this installation is kept');
            $this->assertSame('https://mine.example/?', $restored['base_url'], 'the address of this installation is kept');
            $this->assertSame('mine_', $restored['table_prefix']);
            $this->assertSame(['privatePath' => '/here/private/backups'], $restored['archive'], 'the backups folder of this installation is kept');
        } finally {
            $this->removeTemporaryTree($root);
        }
    }

    /**
     * @param array<string,string> $files
     */
    private function makeTemporaryTree(array $files): string
    {
        $root = sys_get_temp_dir() . '/yeswiki_restore_test_' . bin2hex(random_bytes(6));
        mkdir($root, 0o777, true);
        foreach ($files as $path => $content) {
            $full = "$root/$path";
            if (!is_dir(dirname($full))) {
                mkdir(dirname($full), 0o777, true);
            }
            file_put_contents($full, $content);
        }

        return $root;
    }

    private function removeTemporaryTree(string $root): void
    {
        if (!is_dir($root)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($root);
    }

    /**
     * @param array<int,mixed> $arguments
     */
    private function callProtected(object $target, string $method, array $arguments): mixed
    {
        $reflection = new \ReflectionMethod($target, $method);

        return $reflection->invokeArgs($target, $arguments);
    }

    #[Depends('testArchiveServiceExisting')]
    public function testAnArchiveIsNamedAfterTheWikiThatMadeIt(array $services)
    {
        $output = '';
        $location = $services['archiveService']->archive($output, false, true);
        $this->assertFileExists($location);

        try {
            $parts = ArchiveFilename::parse(basename($location));
            $this->assertNotEmpty($parts, "'" . basename($location) . "' is not a readable backup name");
            $this->assertSame(
                ArchiveFilename::slug($services['wiki']->services->get(ParameterBagInterface::class)->get('base_url')),
                $parts['source']
            );
            $this->assertSame('only_db', $parts['type']);
        } finally {
            @unlink($location);
        }
    }

    #[Depends('testArchiveServiceExisting')]
    public function testAnArchiveIsRenamedToTheTablePrefixOfTheWikiRestoringIt(array $services)
    {
        $output = '';
        $location = $services['archiveService']->archive($output, false, true);
        $this->assertFileExists($location);

        try {
            $zip = new \ZipArchive();
            $this->assertTrue($zip->open($location) === true);
            $entry = ArchiveService::PRIVATE_FOLDER_NAME_IN_ZIP . '/' . ArchiveService::INFO_FILENAME_IN_PRIVATE_FOLDER_IN_ZIP;
            $info = json_decode($zip->getFromName($entry), true);
            $prefix = trim($services['wiki']->services->get(ParameterBagInterface::class)->get('table_prefix'));
            $this->assertSame($prefix, $info['table_prefix']);
            $sqlContent = $zip->getFromName(ArchiveService::PRIVATE_FOLDER_NAME_IN_ZIP . '/' . ArchiveService::SQL_FILENAME_IN_PRIVATE_FOLDER_IN_ZIP);
            $zip->close();

            $this->assertSame($prefix, DumpRewriter::detectPrefix($sqlContent));
            $renamed = DumpRewriter::rewriteTables($sqlContent, $prefix, 'anotherprefix_');
            $this->assertNotEmpty(DumpRewriter::tables($renamed));
            foreach (DumpRewriter::tables($renamed) as $table) {
                $this->assertStringStartsWith('anotherprefix_', $table);
            }
        } finally {
            @unlink($location);
        }
    }

    /**
     * retrieve data from location
     * delete the zip file because only for tests.
     *
     * @return array $data
     */
    private function getDataFromLocation(string $location, Wiki $wiki): array
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
                    // create tmp folder in cache
                    do {
                        $tmpFolderName = 'tmp_folder_to_delete_' . md5(time());
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
                        $files = array_map(function ($path) use ($tmpFolderName) {
                            return str_replace('\\', '/', preg_replace("/^cache(?:\/|\\\\)" . preg_quote($tmpFolderName, '/') . "(?:\/|\\\\)/", '', $path));
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
        if (!in_array(basename($path), ['.', '..']) && !preg_match("/(?:^|\/|\\\\)\.{1,2}(?:^|\/|\\\\)/", $path)) {
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

    private function checkWakkaContent($contentDefinition, $contentToCheck)
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

    #[Depends('testArchiveServiceExisting')]
    #[Depends('testArchive')]
    #[DataProvider('notInParallelProvider')]
    public function testNotArchiveInParallel(
        string $status,
        array $services
    ) {
        $params = $services['wiki']->services->get(ParameterBagInterface::class);
        $configService = $services['wiki']->services->get(ConfigurationService::class);
        $consoleService = $services['wiki']->services->get(ConsoleService::class);
        $previousStatus = $params->has('wiki_status') ? $params->get('wiki_status') : null;
        $this->setWikiStatus($configService, $status);

        $defaultFoldersToInclude = constant('\\YesWiki\\Core\\Service\\ArchiveService::FOLDERS_TO_INCLUDE');

        $results = $consoleService->startConsoleSync('core:archive', [
            '-f',
            '-x', implode(',', $defaultFoldersToInclude),
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
        $this->assertTrue($atLeastOneStdErr, "No error in \"ArchiveService\" when \"wiki_status\" = \"$status\" ; results: " . json_encode($results));
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

    public static function notInParallelProvider()
    {
        return [
            'archiving' => ['archiving'],
            'hibernate' => ['hibernate'],
            'updating' => ['updating'],
        ];
    }

    #[Depends('testArchiveServiceExisting')]
    #[Depends('testArchive')]
    #[DataProvider('hideConfigValuesProvider')]
    public function testhideConfigValuesParams(
        bool $paramsFromWakka,
        ?array $hideConfigValuesParam,
        array $wakkaContent,
        array $services
    ) {
        $params = $services['wiki']->services->get(ParameterBagInterface::class);
        $configService = $services['wiki']->services->get(ConfigurationService::class);
        $consoleService = $services['wiki']->services->get(ConsoleService::class);

        $defaultFoldersToInclude = constant('\\YesWiki\\Core\\Service\\ArchiveService::FOLDERS_TO_INCLUDE');

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
            $consoleParams[] = '-a';
            $consoleParams[] = json_encode($hideConfigValuesParam);
        }
        $results = $consoleService->startConsoleSync('core:archive', $consoleParams);
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

        $this->assertNotEmpty($location, 'Bad format of stdout');
        $this->assertTrue(is_file($location), 'Extracted location is not a file !');
        $data = $this->getDataFromLocation($location, $services['wiki']);
        $error = $data['error'] ?? '';
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

    public static function hideConfigValuesProvider()
    {
        return [
            'default' => [
                true,
                null,
                ['mysql_host' => '', 'mysql_database' => '', 'mysql_user' => '', 'mysql_password' => '', 'archive' => ['hideConfigValues' => ['mysql_host' => '', 'mysql_database' => '', 'mysql_user' => '', 'mysql_password' => '', 'contact_smtp_host' => '', 'contact_smtp_user' => '', 'contact_smtp_pass' => '', 'api_allowed_keys' => []]]],
            ],
            'specific' => [
                true,
                ['mysql_host' => '', 'mysql_database' => '', 'mysql_user' => '', 'mysql_password' => '', 'custom_key' => ''],
                ['mysql_host' => '', 'mysql_database' => '', 'mysql_user' => '', 'mysql_password' => '', 'archive' => ['hideConfigValues' => ['mysql_host' => '', 'mysql_database' => '', 'mysql_user' => '', 'mysql_password' => '', 'custom_key' => '']]],
            ],
            'specific via command line' => [
                false,
                ['mysql_host' => '', 'mysql_database' => '', 'mysql_user' => '', 'mysql_password' => '', 'custom_key_2' => '', 'custom_key_3' => ''],
                ['mysql_host' => '', 'mysql_database' => '', 'mysql_user' => '', 'mysql_password' => '', 'archive' => ['hideConfigValues' => ['mysql_host' => '', 'mysql_database' => '', 'mysql_user' => '', 'mysql_password' => '', 'custom_key_2' => '', 'custom_key_3' => '']]],
            ],
        ];
    }
}
