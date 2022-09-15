<?php

namespace YesWiki\Test\Core\Commands;

use YesWiki\Core\Service\ArchiveService;
use YesWiki\Core\Service\ConfigurationService;
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
        $data = $this->getDataFromLocation($location,$services['wiki']);
        $this->assertEmpty($data['error'] ?? "");
        $this->assertArrayNotHasKey('error',$data);
        $this->assertMatchesRegularExpression("/^.*".preg_quote(constant("\\YesWiki\\Core\\Service\\ArchiveService::{$locationSuffix}").".zip","/")."$/", $location);
        if (!is_null($nbFiles) && $nbFiles > -1){
            $this->assertArrayHasKey('files',$data);
            $this->assertCount($nbFiles,$data['files']);
            foreach ($filesToFind as $path) {
                $this->assertContains($path,$data['files']);
            }
            if (!is_null($wakkaContent)){
                $this->assertArrayHasKey('wakkaContent',$data);
                $this->checkWakkaContent($wakkaContent,$data['wakkaContent']);
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
                    'mysql_host' => '',
                    'mysql_database' => '',
                    'mysql_user' => '',
                    'mysql_password' => '',
                ]
            ],
            'archive only wakka.config.php with database' => [
                'savefiles' => true,
                'savedatabase' => true,
                'extrafiles' => ['wakka.config.php'],
                'excludedfiles' => ['*','.*'],
                'locationSuffix' => "ARCHIVE_SUFFIX",
                'nbFiles' => 1,
                'filesToFind' => ['wakka.config.php'],
                'wakkaContent' => [
                    'archive' => [
                        'extrafiles' => ['wakka.config.php'],
                        'excludedfiles' => ['*','.*'],
                    ],
                    'mysql_host' => '',
                    'mysql_database' => '',
                    'mysql_user' => '',
                    'mysql_password' => '',
                ]
            ],
            'archive only database' => [
                'savefiles' => false,
                'savedatabase' => true,
                'extrafiles' => ['wakka.config.php'],
                'excludedfiles' => ['*','.*'],
                'locationSuffix' => "ARCHIVE_ONLY_DATABASE_SUFFIX",
                'nbFiles' => 1,
                'filesToFind' => ['wakka.config.php'],
                'wakkaContent' => [
                    'archive' => [
                        'extrafiles' => ['wakka.config.php'],
                        'excludedfiles' => ['*','.*'],
                    ],
                    'mysql_host' => '',
                    'mysql_database' => '',
                    'mysql_user' => '',
                    'mysql_password' => '',
                ]
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
                    'mysql_host' => '',
                    'mysql_database' => '',
                    'mysql_user' => '',
                    'mysql_password' => '',
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
    private function getDataFromLocation(string $location,Wiki $wiki): array
    {
        $data = [];
        if (!empty($location) && file_exists($location)){
            if (!preg_match("/^.*\.zip$/",$location)){
                $data['error'] = "\"\$location\" (\"$location\") is not a zip file !";
            } else {
                $zip = new ZipArchive;
                if ($zip->open($location) !== TRUE) {
                    $data['error'] = "\"\$location\" (\"$location\") is not openable !";
                } else {
                    // create tmp folder in cache
                    do {
                        $tmpFolderName = "tmp_folder_to_delete_".md5(time());
                    } while (file_exists("cache/$tmpFolderName"));
                    if (!$zip->extractTo("cache/$tmpFolderName")){
                        $data['error'] = "\"\$location\" (\"$location\") is not extractable !";
                        $zip->close();
                    } else {
                        $zip->close();
                        $files = [];
                        foreach([
                            "*",".?*",
                            "*/*",".[\\.]*/*","*/.[\\.]*",".[\\.]*/.[\\.]*",
                            "*/*/*",".[\\.]*/*/*","*/.[\\.]*/*",".[\\.]*/.[\\.]*/*",
                            "*/*/*/*",".[\\.]*/*/*/*","*/.[\\.]*/*/*",".*/.[\\.]*/*/*",
                        ] as $globCatch){
                            foreach(glob("cache/$tmpFolderName/$globCatch") as $path){
                                if (!in_array(basename($path),['.','..']) && !in_array($path,$files)){
                                    $files[] = $path;
                                }
                            }
                        }
                        $files = array_map(function ($path) use ($tmpFolderName){
                            return str_replace("\\","/",preg_replace("/^cache(?:\/|\\\\)".preg_quote($tmpFolderName,"/")."(?:\/|\\\\)/","",$path));
                        },$files);
                        $data['files'] = $files;

                        // wakka content
                        if (file_exists("cache/$tmpFolderName/wakka.config.php") && is_file("cache/$tmpFolderName/wakka.config.php")){
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
        if (!in_array(basename($path),['.','..']) && !preg_match("/(?:^|\/|\\\\)\.{1,2}(?:^|\/|\\\\)/",$path)){
            if (file_exists($path)){
                if (is_dir($path)){
                    $dh = opendir($path);
                    while (false !== ($file = readdir($dh))) {
                        $this->recursiveDelete("$path/$file");
                    }
                    closedir($dh);
                    rmdir($path);
                } elseif (is_file($path)){
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
        if (is_array($contentDefinition)){
            $this->assertIsArray($contentToCheck);
            foreach($contentDefinition as $key => $value){
                $this->assertArrayHasKey($key,$contentToCheck);
                $this->checkWakkaContent($contentDefinition[$key],$contentToCheck[$key]);
            }
        } elseif (is_scalar($contentDefinition)) {
            $this->assertEquals($contentDefinition,$contentToCheck);
        }
    }
}
