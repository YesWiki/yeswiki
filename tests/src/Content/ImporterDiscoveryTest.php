<?php

namespace YesWiki\Test\Content;

use PHPUnit\Framework\Attributes\Depends;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\ListManager;
use YesWiki\Import\Service\ImporterManager;
use YesWiki\Import\Service\YesWikiToYesWikiImporter;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\YesWikiRuntime;

require_once 'tests/YesWikiTestCase.php';

/** How an importer is found, and how a data source is configured. */
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

        $this->assertArrayHasKey('syncOnMaintenance', $options);
        $this->assertFalse($options['syncOnMaintenance']);
        $this->assertArrayNotHasKey('syncIntervalInMin', $options);
    }
}

/** Stands in for fulltextsearch's SealImporter: named like one, unrelated to a data source. */
class SealImporter
{
}
