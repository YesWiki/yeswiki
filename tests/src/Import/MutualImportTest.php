<?php

namespace YesWiki\Test\Import;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use YesWiki\Content\Service\BazarListService;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\ListManager;
use YesWiki\Federation\Service\EntryOrigin;
use YesWiki\Files\Service\Storage;
use YesWiki\Identity\Service\GroupManager;
use YesWiki\Import\Service\EntryMerger;
use YesWiki\Import\Service\Importer;
use YesWiki\Import\Service\ImporterManager;
use YesWiki\Import\Service\YesWikiToYesWikiImporter;
use YesWiki\Kernel\Service\TripleStore;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Wikis that import each other merge edits field by field and never bring an entry back home. */
class MutualImportTest extends YesWikiTestCase
{
    private const FORM_ID = '999811';
    private const REMOTE = 'https://remote.example/';
    private const OTHER = 'https://other.example/';
    private const IMAGE = 'MutualImportTest_imagebf_image_affiche.png';
    private const TEMPLATE = [
        ['type' => 'texte', 'name' => 'bf_titre', 'label' => 'Titre'],
        ['type' => 'texte', 'name' => 'bf_lieu', 'label' => 'Lieu'],
    ];

    private string $admin = '';

    protected function setUp(): void
    {
        parent::setUp();
        $admins = self::getWiki()->services->get(GroupManager::class)->getMembers('admins');
        if ($admins === []) {
            $this->markTestSkipped('no admin account in the test wiki');
        }
        $this->admin = (string)$admins[0];
    }

    protected function tearDown(): void
    {
        $services = self::getWiki()->services;
        $triples = $services->get(TripleStore::class);
        foreach ($this->localTags() as $tag) {
            foreach ([TripleStore::SOURCE_URL_URI, TripleStore::ORIGIN_URL_URI] as $property) {
                $triples->delete($tag, $property, null, '', '');
            }
        }
        if ($services->get(Storage::class)->exists('files/' . self::IMAGE)) {
            $services->get(Storage::class)->delete('files/' . self::IMAGE);
        }
        if (!empty($services->get(FormManager::class)->getOne(self::FORM_ID))) {
            $services->get(FormManager::class)->delete(self::FORM_ID);
        }
        self::actAs(null);
        parent::tearDown();
    }

    /** @return list<string> */
    private function localTags(): array
    {
        $entries = self::getWiki()->services->get(EntryManager::class)->search(['formsIds' => [self::FORM_ID]]);

        return array_values(array_map(fn (array $entry): string => (string)$entry['tag'], $entries));
    }

    /**
     * @param list<array<string, mixed>> $entries
     * @param array<string, mixed>       $options
     * @param list<array<string, mixed>> $template
     */
    private function sync(string $source, string $remote, array $entries, array $options = [], array $template = self::TEMPLATE, ?ImporterManager $manager = null): string
    {
        $services = self::getWiki()->services;
        $mapping = [];
        foreach ($template as $field) {
            $property = ($field['type'] === 'image' ? 'image' : '') . $field['name'];
            $mapping[$property] = $property;
        }
        $params = new ParameterBag([
            'base_url' => 'https://here.example/?',
            'dataSources' => [$source => $options + [
                'importer' => 'YesWikiToYesWiki',
                'url' => $remote . '?api/forms/7/entries/json',
                'formId' => self::FORM_ID,
                'syncMode' => 'allow_local',
                'localAdminUser' => $this->admin,
                'fieldsMapping' => $mapping,
            ]],
        ]);
        $data = ['form' => ['label' => 'Remote', 'template' => $template], 'entries' => $entries];
        $importer = new class($source, $params, $services, $services->get(EntryManager::class), $manager ?? $services->get(ImporterManager::class), $services->get(FormManager::class), $services->get(ListManager::class), $data) extends YesWikiToYesWikiImporter {
            /** @param array{form: array<string, mixed>, entries: array<mixed>} $fixture */
            public function __construct(string $source, ParameterBag $params, \Psr\Container\ContainerInterface $services, EntryManager $entries, ImporterManager $manager, FormManager $forms, ListManager $lists, private array $fixture)
            {
                parent::__construct($source, $params, $services, $entries, $manager, $forms, $lists);
            }

            /** @return array{form: array<string, mixed>, entries: array<mixed>} */
            public function getData(): array
            {
                return $this->fixture;
            }
        };
        ob_start();
        $mapped = $importer->mapData($importer->getData());
        $importer->syncFormModel();
        $importer->syncData($mapped);

        return (string)ob_get_clean();
    }

    /** @return array<string, mixed> */
    private static function remoteEntry(string $wiki, string $tag, string $lieu, ?string $origin = null): array
    {
        return ['url' => $wiki . '?' . $tag, 'bf_titre' => $tag, 'bf_lieu' => $lieu] + ($origin === null ? [] : ['origin_url' => $origin]);
    }

    /** @return array<string, mixed> */
    private function onlyLocalEntry(): array
    {
        $tags = $this->localTags();
        $this->assertCount(1, $tags);

        return self::getWiki()->services->get(EntryManager::class)->getOne($tags[0], false, null, false) ?? [];
    }

    private function editLocally(string $field, string $value): void
    {
        $entry = $this->onlyLocalEntry();
        self::actAs($this->admin);
        self::getWiki()->services->get(EntryManager::class)->update($entry['tag'], [$field => $value, 'antispam' => 1] + $entry, false, true);
        self::actAs(null);
    }

    public function testEditsOnBothSidesAreMergedFieldByField(): void
    {
        $this->sync('a', self::REMOTE, [self::remoteEntry(self::REMOTE, 'Fete', 'Lyon')]);
        $this->editLocally('bf_titre', 'Grande fête');

        $this->sync('a', self::REMOTE, [self::remoteEntry(self::REMOTE, 'Fete', 'Paris')]);

        $entry = $this->onlyLocalEntry();
        $this->assertSame('Grande fête', $entry['bf_titre']);
        $this->assertSame('Paris', $entry['bf_lieu']);
    }

    public function testAConflictKeepsTheLocalValueAndIsLoggedOnce(): void
    {
        $this->sync('a', self::REMOTE, [self::remoteEntry(self::REMOTE, 'Fete', 'Lyon')]);
        $this->editLocally('bf_lieu', 'Villeurbanne');

        $first = $this->sync('a', self::REMOTE, [self::remoteEntry(self::REMOTE, 'Fete', 'Paris')]);
        $second = $this->sync('a', self::REMOTE, [self::remoteEntry(self::REMOTE, 'Fete', 'Paris')]);

        $this->assertSame('Villeurbanne', $this->onlyLocalEntry()['bf_lieu']);
        $this->assertStringContainsString('conflit sur "bf_lieu"', $first);
        $this->assertStringNotContainsString('conflit', $second);
    }

    public function testTheRemotePolicyTakesTheRemoteValue(): void
    {
        $this->sync('a', self::REMOTE, [self::remoteEntry(self::REMOTE, 'Fete', 'Lyon')], ['conflictPolicy' => EntryMerger::POLICY_REMOTE]);
        $this->editLocally('bf_lieu', 'Villeurbanne');

        $this->sync('a', self::REMOTE, [self::remoteEntry(self::REMOTE, 'Fete', 'Paris')], ['conflictPolicy' => EntryMerger::POLICY_REMOTE]);

        $this->assertSame('Paris', $this->onlyLocalEntry()['bf_lieu']);
    }

    public function testAnEntryReachingHereTwiceIsCreatedOnce(): void
    {
        $this->sync('a', self::REMOTE, [self::remoteEntry(self::REMOTE, 'Fete', 'Lyon')]);

        $log = $this->sync('b', self::OTHER, [self::remoteEntry(self::OTHER, 'FeteCopie', 'Lyon', self::REMOTE . '?Fete')]);

        $this->assertCount(1, $this->localTags());
        $this->assertStringContainsString('déjà présente ici par un autre chemin', $log);
    }

    public function testTheOriginIsPassedOnAndKeepsAnEntryFromComingHome(): void
    {
        $here = 'https://here.example/?';
        $this->sync('b', self::OTHER, [
            self::remoteEntry(self::OTHER, 'Venue', 'Lyon', self::REMOTE . '?Venue'),
            self::remoteEntry(self::OTHER, 'Retour', 'Lyon', $here . 'Retour'),
        ]);
        $services = self::getWiki()->services;

        $entries = $services->get(EntryOrigin::class)->withOriginUrls($services->get(EntryManager::class)->search(['formsIds' => [self::FORM_ID]]));
        $origins = array_column($entries, 'origin_url', 'bf_titre');
        $this->assertSame(self::REMOTE . '?Venue', $origins['Venue']);

        $kept = $services->get(BazarListService::class)->getEntries(['id' => self::FORM_ID, 'excludeSource' => $here]);
        $this->assertSame(['Venue'], array_column($kept, 'bf_titre'));
    }

    public function testAWipeWaitsForTheSourceToAnswer(): void
    {
        $this->sync('a', self::REMOTE, [self::remoteEntry(self::REMOTE, 'Fete', 'Lyon')]);
        $manager = self::getWiki()->services->get(ImporterManager::class);
        $wipe = new \ReflectionMethod(ImporterManager::class, 'wipe');
        $importer = new class extends Importer {
            public function __construct()
            {
            }
        };

        ob_start();
        $wipe->invoke($manager, $importer, ['formId' => self::FORM_ID], false);
        $skipped = (string)ob_get_clean();
        $this->assertCount(1, $this->localTags());
        $this->assertStringContainsString(self::FORM_ID, $skipped);

        ob_start();
        $wipe->invoke($manager, $importer, ['formId' => self::FORM_ID], true);
        ob_end_clean();
        $this->assertEmpty(self::getWiki()->services->get(FormManager::class)->getOne(self::FORM_ID));
    }

    public function testAFailedDownloadKeepsTheLocalFile(): void
    {
        $template = [...self::TEMPLATE, ['type' => 'image', 'name' => 'bf_image', 'label' => 'Image']];
        $services = self::getWiki()->services;
        $services->get(Storage::class)->write('files/' . self::IMAGE, 'png');
        $this->sync('a', self::REMOTE, [self::remoteEntry(self::REMOTE, 'Fete', 'Lyon')], [], $template);
        $this->editLocally('imagebf_image', self::IMAGE);
        $failing = new class($services) extends ImporterManager {
            public function __construct(\Psr\Container\ContainerInterface $services)
            {
                $this->services = $services;
            }

            public function downloadFile(string $sourceUrl, bool $noSSLCheck = false, int $timeoutInSec = 30, bool $replaceExisting = false, ?string $destFileName = null, array $headers = []): string
            {
                return '';
            }
        };

        $log = $this->sync('a', self::REMOTE, [self::remoteEntry(self::REMOTE, 'Fete', 'Lyon') + ['imagebf_image' => 'nouvelle.png']], [], $template, $failing);

        $this->assertSame(self::IMAGE, $this->onlyLocalEntry()['imagebf_image']);
        $this->assertStringContainsString('est conservé', $log);
    }
}
