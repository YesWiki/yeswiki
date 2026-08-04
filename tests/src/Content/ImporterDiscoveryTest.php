<?php

namespace YesWiki\Test\Content;

use PHPUnit\Framework\Attributes\Depends;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\ImporterManager;
use YesWiki\Content\Service\ListManager;
use YesWiki\Content\Service\YesWikiToYesWikiImporter;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\YesWikiRuntime;

require_once 'tests/YesWikiTestCase.php';

/**
 * How an importer is found, and how a data source is configured.
 *
 * Discovery is by service id -- any service whose id ends in "Importer" -- because an
 * importer cannot be a normal container service: it is constructed per data source, with
 * that source's config key. So the definitions exist only to be *listed*, and if one of them
 * ever picks up autowiring, or the id naming drifts, this is what says so; the symptom
 * otherwise is an admin page whose importer list is silently short.
 */
class ImporterDiscoveryTest extends YesWikiTestCase
{
    public function testWikiExisting(): YesWikiRuntime
    {
        $wiki = $this->getWiki();
        $this->assertTrue($wiki->services->has(YesWikiRuntime::class));

        return $wiki->services->get(YesWikiRuntime::class);
    }

    #[Depends('testWikiExisting')]
    public function testCoreImportersAreDiscoverable(YesWikiRuntime $wiki): void
    {
        $importers = $wiki->services->get(ImporterManager::class)->getAvailableImporters();

        foreach (['Rss', 'Imap', 'YesWikiList', 'YesWikiToYesWiki'] as $name) {
            $this->assertArrayHasKey($name, $importers, "the $name importer is not discoverable");
        }
        $this->assertSame(YesWikiToYesWikiImporter::class, $importers['YesWikiToYesWiki']);
    }

    #[Depends('testWikiExisting')]
    public function testSomethingElseCalledAnImporterIsNotOffered(YesWikiRuntime $wiki): void
    {
        // "importer" is a word other code uses for other things: yeswiki-extension-fulltextsearch
        // registers SealImporter and SealBatchImporter, which feed a search index and take a
        // different constructor entirely. Matching on the name alone offered them to the admin
        // as data source types, and constructing one would fatal.
        $container = new class extends Container {
            public function getServiceIds(): array
            {
                return [YesWikiToYesWikiImporter::class, SealImporter::class, 'some.plain.importer'];
            }
        };
        $importerManager = new ImporterManager(
            $wiki->services->get(ParameterBagInterface::class),
            $container,
            $wiki->services->get(EntryManager::class),
            $wiki->services->get(FormManager::class),
            $wiki->services->get(ListManager::class)
        );

        $this->assertSame(['YesWikiToYesWiki'], array_keys($importerManager->getAvailableImporters()));
    }

    #[Depends('testWikiExisting')]
    public function testEveryImporterOffersTheCommonSyncFields(YesWikiRuntime $wiki): void
    {
        $importerManager = $wiki->services->get(ImporterManager::class);

        foreach (array_keys($importerManager->getAvailableImporters()) as $name) {
            $fields = $importerManager->getAdminFieldsFor((string)$name);
            // an importer declares its own fields; when to sync it is not one of them
            $this->assertArrayHasKey('syncOnMaintenance', $fields, "$name lost syncOnMaintenance");
            $this->assertArrayHasKey('syncIntervalInMin', $fields, "$name lost syncIntervalInMin");
        }
    }

    #[Depends('testWikiExisting')]
    public function testAdminInputBecomesADataSource(YesWikiRuntime $wiki): void
    {
        $importerManager = $wiki->services->get(ImporterManager::class);
        $fields = ['YesWikiToYesWiki' => $importerManager->getAdminFieldsFor('YesWikiToYesWiki')];

        $options = $importerManager->collectSourceOptionsFromInput('YesWikiToYesWiki', $fields, [
            'urlYesWikiToYesWiki' => 'https://remote.example.org/?api/forms/12/entries/json',
            'auth_userYesWikiToYesWiki' => 'admin',
            'auth_passwordYesWikiToYesWiki' => 'secret',
            'syncModeYesWikiToYesWiki' => 'allow_local',
            'syncOnMaintenanceYesWikiToYesWiki' => '1',
            'syncIntervalInMinYesWikiToYesWiki' => '1440',
            'formId' => '12',
        ]);

        // the pasted entries url carries the remote form id: it is split out, not stored raw
        $this->assertSame('https://remote.example.org', $options['url']);
        $this->assertSame('12', $options['remoteFormId']);
        $this->assertSame(['user' => 'admin', 'password' => 'secret'], $options['auth']);
        $this->assertTrue($options['syncOnMaintenance']);
        $this->assertSame('1440', $options['syncIntervalInMin']);
    }

    #[Depends('testWikiExisting')]
    public function testAnUntickedCheckboxIsNotAnEnabledSource(YesWikiRuntime $wiki): void
    {
        $importerManager = $wiki->services->get(ImporterManager::class);
        $fields = ['Rss' => $importerManager->getAdminFieldsFor('Rss')];

        $options = $importerManager->collectSourceOptionsFromInput('Rss', $fields, [
            'urlRss' => 'https://example.org/feed',
            'formId' => '3',
        ]);

        // an unposted checkbox is false, not absent: a source saved with the box unticked must
        // not read as "never configured" and inherit some future default
        $this->assertArrayHasKey('syncOnMaintenance', $options);
        $this->assertFalse($options['syncOnMaintenance']);
        $this->assertArrayNotHasKey('syncIntervalInMin', $options);
    }
}

/** Stands in for fulltextsearch's SealImporter: named like one, unrelated to a data source. */
class SealImporter
{
}
