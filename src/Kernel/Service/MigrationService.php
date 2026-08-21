<?php

namespace YesWiki\Kernel\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Kernel\Entity\Messages;

// This is a simple mecanism to perform migrations
// See src/migrations/README.md for how to create a new migration
class MigrationService
{
    public const TRIPLES_MIGRATION_ID = 'migration';
    private DbService $dbService;
    private ParameterBagInterface $params;

    protected ContainerInterface $container;

    public function __construct(ContainerInterface $container, DbService $dbService, ParameterBagInterface $params)
    {
        $this->container = $container;
        $this->dbService = $dbService;
        $this->params = $params;
    }

    /**
     * @return array<array-key, mixed> the migration ids already recorded as run
     */
    public function getCompletedMigrations(): array
    {
        $tripleStore = $this->container->get(TripleStore::class);

        return array_map(function ($data) {
            return $data['resource'];
        }, $tripleStore->getMatching(null, TripleStore::TYPE_URI, self::TRIPLES_MIGRATION_ID));
    }

    public function run(): Messages
    {
        if ($this->container->get(HibernationService::class)->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }

        $messages = new Messages();
        $tripleStore = $this->container->get(TripleStore::class);
        $completedMigrations = $this->getCompletedMigrations();

        // Get all Php files in migrations folder (in root or in any extension)
        // Run the file if it was not already run in the past
        $folders = array_merge([YESWIKI_PROGRAM_DIR . '/src/'], $this->container->get(ExtensionRegistry::class)->all()); // root folder + extensions folders
        foreach ($folders as $folder) {
            $folder = $folder . 'migrations/';
            if (file_exists($folder) && $dh = opendir($folder)) {
                $vFiles = [];

                while (($file = readdir($dh)) !== false) {
                    if ($file == '0000000000000_DemoMigration.php') {
                        continue;
                    }

                    if (preg_match("/^([a-zA-Z0-9_-]+)\.php$/", $file, $matches)) {
                        $fileName = $matches[1]; // 2024040500000_TestMigration

                        if (in_array($fileName, $completedMigrations)) {
                            continue;
                        }

                        $vFiles[] = $matches[1];
                    }
                }

                sort($vFiles);

                foreach ($vFiles as $vFile) {
                    $vFilename = $vFile . '.php';

                    $filePath = $folder . $vFilename; // extensions/publication/2024040500000_TestMigration.php
                    require_once $filePath;

                    preg_match("/^([\d]*)/", $vFile, $vMatches);
                    $vDate = $vMatches[1] ?? 'unknow date';

                    $className = preg_replace('/^[\d_]*/', '', $vFile) ?? ''; // TestMigration
                    if (!class_exists($className)) {
                        throw new \Exception("Error while loading $filePath. The class inside should be $className");
                    }

                    // Run Migration
                    try {
                        /** @var \YesWiki\Core\YesWikiMigration $instance */
                        $instance = new $className();
                        $instance->setServices($this->container);
                        $instance->setDbService($this->dbService);
                        $instance->setParams($this->params);
                        $instance->run();
                        $messages->add("Migration $className ($vDate)", 'AU_OK');
                        $tripleStore->create($vFile, TripleStore::TYPE_URI, self::TRIPLES_MIGRATION_ID, '', '');
                    } catch (\Exception $e) {
                        $messages->add("Migration $className ($vDate) failed with error {$e->getMessage()}", 'AU_ERROR');
                    }
                }
            }
        }

        return $messages;
    }
}
