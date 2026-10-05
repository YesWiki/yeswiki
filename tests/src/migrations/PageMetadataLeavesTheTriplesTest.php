<?php

namespace YesWiki\Test\Core\Migrations;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\TripleStore;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Doryphore's per-page metadata triple carried into `pages.metadata`, merged under what the page already has.
 */
class PageMetadataLeavesTheTriplesTest extends YesWikiTestCase
{
    private const PROPERTY = 'http://outils-reseaux.org/_vocabulary/metadata';

    /** @var list<string> */
    private array $pages = [];

    public static function setUpBeforeClass(): void
    {
        self::getWiki();
        require_once 'src/migrations/20261005130100_PageMetadataLeavesTheTriples.php';
    }

    protected function tearDown(): void
    {
        $pageManager = $this->getWiki()->services->get(PageManager::class);
        foreach ($this->pages as $tag) {
            $pageManager->deleteOrphaned($tag);
        }
    }

    public function testCarriesUsableValuesWithoutOverwriting(): void
    {
        $services = $this->getWiki()->services;
        $pageManager = $services->get(PageManager::class);
        $home = $this->page('PmltHome');
        $header = $this->page('PmltHeaderAccueil');
        $themed = $this->page('PmltThemed');
        $pageManager->setMetadata($home, ['PageFooter' => 'KeptFooter']);

        $tripleStore = $services->get(TripleStore::class);
        $tripleStore->create($home, self::PROPERTY, (string)json_encode([
            'PageHeader' => $header,
            'PageFooter' => 'SomeOtherFooter',
            'theme' => 'margot',
            'style' => 'margot.css',
            'squelette' => '1col.tpl.html',
            'bgimg' => 'missing.jpg',
        ]), '', '');
        $tripleStore->create($themed, self::PROPERTY, (string)json_encode([
            'theme' => 'yeswiki',
            'style' => 'yeswiki.css',
            'squelette' => '1col.twig',
            'PageHeader' => 'PageHeader',
            'PageFooter' => 'PmltNoSuchPage' . uniqid(),
        ]), '', '');

        $migration = $this->migration();
        $report = $migration->carryAll([$home, $themed]);

        $this->assertSame([$home, $themed], $report['carried']);
        $this->assertSame(['PageFooter' => 'KeptFooter', 'PageHeader' => $header], $this->metadataOf($home));
        $this->assertSame(['squelette' => '1col.twig', 'style' => 'yeswiki.css', 'theme' => 'yeswiki'], $this->metadataOf($themed));
        $this->assertContains("{$home}.theme", $report['dropped']);
        $this->assertContains("{$home}.bgimg", $report['dropped']);
        $this->assertContains("{$themed}.PageFooter", $report['dropped']);
        $this->assertSame([], $migration->carryAll([$home, $themed])['carried'], 'a second run adds nothing');
    }

    private function migration(): \PageMetadataLeavesTheTriples
    {
        $services = $this->getWiki()->services;
        $migration = new \PageMetadataLeavesTheTriples();
        $migration->setServices($services);
        $migration->setParams($services->get(ParameterBagInterface::class));
        $migration->setDbService($services->get(DbService::class));

        return $migration;
    }

    private function page(string $prefix): string
    {
        $tag = $prefix . uniqid();
        $this->getWiki()->services->get(PageManager::class)->save($tag, [PageBody::CONTENT => 'x'], '', true);
        $this->pages[] = $tag;

        return $tag;
    }

    /** @return array<string, mixed> */
    private function metadataOf(string $tag): array
    {
        $pageManager = $this->getWiki()->services->get(PageManager::class);
        $pageManager->forget($tag);

        $metadata = $pageManager->getMetadata($tag) ?? [];
        unset($metadata['acls']);
        ksort($metadata);

        return $metadata;
    }
}
