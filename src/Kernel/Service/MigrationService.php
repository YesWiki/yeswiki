<?php

namespace YesWiki\Kernel\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Kernel\Entity\Messages;

/** Runs the migrations a wiki has not run yet (see src/migrations/README.md), then records the Program's release in its configuration. */
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

        $this->recordProgramRelease($messages);

        return $messages;
    }

    /** Writes the Program's composer.json version as `yeswiki_release`, which a shared Program's upgrade leaves stale in every other instance. */
    private function recordProgramRelease(Messages $messages): void
    {
        $configurationService = $this->container->get(ConfigurationService::class);
        $configuration = $configurationService->getConfiguration(ConfigurationFileProvider::getConfigFileFromEnv());
        $configuration->load();

        $previous = (string)($configuration['yeswiki_release'] ?? '');
        $release = $this->container->get(ProgramVersion::class)->release($previous);
        if ($release === $previous) {
            return;
        }

        $configuration['yeswiki_release'] = $release;
        if ($configurationService->write($configuration)) {
            $messages->add("yeswiki_release goes from '{$previous}' to '{$release}'", 'AU_OK');
        } else {
            $messages->add("yeswiki_release could not be written as '{$release}'", 'AU_ERROR');
        }
    }
}
