<?php

namespace YesWiki\AutoUpdate\Service;

use Exception;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\AutoUpdate\Entity\Messages;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Security\Controller\SecurityController;
use YesWiki\Wiki;

// This is a simple mecanism to perform migrations
// See includes/migrations/README.md for how to create a new migration
class MigrationService
{
    public const TRIPLES_MIGRATION_ID = 'migration';
    private $wiki;
    private $dbService;
    private $params;

    public function __construct(Wiki $wiki, DbService $dbService, ParameterBagInterface $params)
    {
        $this->wiki = $wiki;
        $this->dbService = $dbService;
        $this->params = $params;
    }

    public function run()
    {
        if ($this->wiki->services->get(SecurityController::class)->isWikiHibernated()) {
            throw new Exception(_t('WIKI_IN_HIBERNATION'));
        }

        $messages = new Messages();

        $tripleStore = $this->wiki->services->get(TripleStore::class);
        $completedMigrations = array_map(function ($data) {
            return $data['resource'];
        }, $tripleStore->getMatching(null, TripleStore::TYPE_URI, self::TRIPLES_MIGRATION_ID));

        // Get all Php files in migrations folder (in root or in any tools)
        // Run the file if it was not already run in the past
        $folders = array_merge(['includes/'], $this->wiki->extensions); // root folder + extensions folders
        foreach ($folders as $folder) {
            $folder = $folder . 'migrations/';
            if (file_exists($folder) && $dh = opendir($folder)) {
                while (($file = readdir($dh)) !== false) {
                    if (preg_match("/^([a-zA-Z0-9_-]+)\.php$/", $file, $matches)) {
                        $fileName = $matches[1]; // 2024040500000_TestMigration
                        if (in_array($fileName, $completedMigrations)) {
                            continue;
                        }

                        $filePath = $folder . $file; // tools/publication/2024040500000_TestMigration.php
                        require_once $filePath;

                        $className = preg_replace('/^[\d_]*/', '', $fileName); // TestMigration
                        if (!class_exists($className)) {
                            throw new Exception("Error while loading $filePath. The class inside should be $className");
                        }

                        // Run Migration
                        try {
                            $instance = new $className();
                            $instance->setWiki($this->wiki);
                            $instance->setDbService($this->dbService);
                            $instance->setParams($this->params);
                            $instance->run();
                            $messages->add("Migration $className", 'AU_OK');
                            $tripleStore->create($fileName, TripleStore::TYPE_URI, self::TRIPLES_MIGRATION_ID, '', '');
                        } catch (Exception $e) {
                            $messages->add("Migration $className failed with error {$e->getMessage()}", 'AU_ERROR');
                        }
                    }
                }
            }
        }

        return $messages;
    }
}