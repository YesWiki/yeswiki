<?php

namespace YesWiki\Test\AutoUpdate\Service;

use YesWiki\AutoUpdate\Entity\Messages;
use YesWiki\AutoUpdate\Entity\PackageTool;
use YesWiki\AutoUpdate\Entity\Release;
use YesWiki\AutoUpdate\Entity\Repository;
use YesWiki\AutoUpdate\Service\AutoUpdateService;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\Wiki;

require_once 'tests/YesWikiTestCase.php';

/**
 * A wiki lists in `forbidden_extensions` the extensions it may never install, and the update page stops offering them.
 */
class AutoUpdateServiceForbiddenTest extends YesWikiTestCase
{
    private Wiki $wiki;
    private mixed $previous;
    private AutoUpdateService $service;
    private string $previousDir;
    private string $wikiDir;

    protected function setUp(): void
    {
        $this->wiki = $this->getWiki();
        $GLOBALS['wiki'] = $this->wiki;
        foreach ([PackageTool::class, Release::class, Repository::class, Messages::class, AutoUpdateService::class] as $class) {
            class_exists($class);
        }
        $this->previousDir = getcwd();
        $this->wikiDir = sys_get_temp_dir() . '/yeswiki_forbidden_' . uniqid();
        mkdir($this->wikiDir . '/tools/bazar', 0777, true);
        file_put_contents($this->wikiDir . '/wakka.config.php', "<?php\n\$wakkaConfig = [];\n");
        chdir($this->wikiDir);
        $this->previous = $this->wiki->config['forbidden_extensions'] ?? null;
        $this->service = $this->wiki->services->get(AutoUpdateService::class);
        $this->service->repository = new Repository('https://repository.example/doryphore');
        $this->service->repository->add(new Release('4.1.1'), 'https://repository.example/doryphore/', 'extension-ferme-4.1.1.zip', '', '');
        $this->service->repository->add(new Release('4.1.1'), 'https://repository.example/doryphore/', 'extension-bazar-4.1.1.zip', '', '');
        $this->service->repository->add(new Release('4.1.1'), 'https://repository.example/doryphore/', 'extension-qrcode-4.1.1.zip', '', '');
    }

    protected function tearDown(): void
    {
        chdir($this->previousDir);
        unlink($this->wikiDir . '/wakka.config.php');
        rmdir($this->wikiDir . '/tools/bazar');
        rmdir($this->wikiDir . '/tools');
        rmdir($this->wikiDir);
        if ($this->previous === null) {
            unset($this->wiki->config['forbidden_extensions']);
        } else {
            $this->wiki->config['forbidden_extensions'] = $this->previous;
        }
    }

    public function testAForbiddenExtensionIsRefusedBeforeAnythingIsDownloaded()
    {
        $this->wiki->config['forbidden_extensions'] = ['ferme'];

        $messages = $this->service->upgrade('ferme')->toArray();

        $this->assertCount(1, $messages);
        $this->assertStringContainsString('ferme', $messages[0]['text']);
        $this->assertSame(_t('AU_ERROR'), $messages[0]['status']);
    }

    public function testCoreCannotBeForbiddenAndAnythingButAListForbidsNothing()
    {
        $this->wiki->config['forbidden_extensions'] = ['ferme', 'yeswiki'];
        $this->assertSame(['ferme'], $this->service->forbiddenExtensions());

        $this->wiki->config['forbidden_extensions'] = 'ferme';
        $this->assertSame([], $this->service->forbiddenExtensions());

        unset($this->wiki->config['forbidden_extensions']);
        $this->assertSame([], $this->service->forbiddenExtensions());
    }

    public function testTheUpdatePageHidesAForbiddenExtensionUnlessItIsAlreadyInstalled()
    {
        $tools = $this->service->repository->getToolsPackages();

        $names = array_map(fn ($package) => $package->name, $tools->withoutForbidden(['ferme', 'bazar'])->toArray());

        $this->assertSame(['bazar', 'qrcode'], $names);
    }
}
