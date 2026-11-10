<?php

namespace YesWiki\Kernel\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Kernel\Entity\Messages;
use YesWiki\Kernel\Service\Lock\Locks;

/** Runs the migrations a wiki has not run yet (see src/migrations/README.md), one run at a time across every replica. */
class MigrationService
{
    public const TRIPLES_MIGRATION_ID = 'migration';

    public const LOCK = 'migrations';

    public const WAIT_SECONDS = 600;
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

        $locks = $this->container->get(Locks::class);
        $held = $this->waitFor($locks);
        try {
            $messages = $this->runPending();
            $this->container->get(SchemaStep::class)->record(YESWIKI_PROGRAM_DIR . '/src/');
        } finally {
            if ($held) {
                $locks->release(self::LOCK);
            }
        }

        return $messages;
    }

    /** Takes the migrations lock, waiting while another run holds it; false when this database offers no lock at all. */
    private function waitFor(Locks $locks): bool
    {
        $deadline = microtime(true) + self::WAIT_SECONDS;
        while (true) {
            $heldElsewhere = false;
            if ($locks->tryAcquire(self::LOCK, $heldElsewhere)) {
                return true;
            }
            if (!$heldElsewhere) {
                return false;
            }
            if (microtime(true) >= $deadline) {
                throw new \RuntimeException('Another migrate has held the migrations lock for ' . self::WAIT_SECONDS . ' seconds');
            }
            usleep(250000);
        }
    }

    private function runPending(): Messages
    {
        $messages = new Messages();
        $tripleStore = $this->container->get(TripleStore::class);
        $completedMigrations = $this->getCompletedMigrations();

        $folders = array_merge([YESWIKI_PROGRAM_DIR . '/src/'], $this->container->get(ExtensionRegistry::class)->all());
        foreach ($folders as $folder) {
            $folder = $folder . 'migrations/';

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
                    foreach ($instance->notes() as $note) {
                        $messages->add($note, 'AU_NOTE');
                    }
                    $this->container->get(Journal::class)->audit(Journal::MIGRATION_APPLIED, $vFile, [], 'migration');
                    $tripleStore->create($vFile, TripleStore::TYPE_URI, self::TRIPLES_MIGRATION_ID, '', '');
                } catch (\Exception $e) {
                    $messages->add("Migration $className ($vDate) failed with error {$e->getMessage()}", 'AU_ERROR');
                }
            }
        }

        return $messages;
    }
}
