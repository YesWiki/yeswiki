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

        $folders = array_merge([YESWIKI_PROGRAM_DIR . '/src/'], $this->container->get(ExtensionRegistry::class)->all());
        foreach ($folders as $folder) {
            $folder = $folder . 'migrations/';

            // Through the scanner, like every other code directory: `src/migrations/` and an
            // extension's own hold PHP that gets `require_once`d, not a wiki's data (ticket 49).
            $vFiles = [];

            foreach ($this->container->get(ClassDirectoryScanner::class)->filesIn($folder) as $file) {
                if ($file === '0000000000000_DemoMigration.php') {
                    continue;
                }

                if (preg_match("/^([a-zA-Z0-9_-]+)\.php$/", $file, $matches)) {
                    $fileName = $matches[1];

                    if (in_array($fileName, $completedMigrations)) {
                        continue;
                    }

                    $vFiles[] = $matches[1];
                }
            }

            sort($vFiles);

            foreach ($vFiles as $vFile) {
                $vFilename = $vFile . '.php';

                $filePath = $folder . $vFilename;
                require_once $filePath;

                preg_match("/^([\d]*)/", $vFile, $vMatches);
                $vDate = $vMatches[1] ?? 'unknow date';

                $className = preg_replace('/^[\d_]*/', '', $vFile) ?? '';
                if (!class_exists($className)) {
                    throw new \Exception("Error while loading $filePath. The class inside should be $className");
                }

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

        return $messages;
    }
}
