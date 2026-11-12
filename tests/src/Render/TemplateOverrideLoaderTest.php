<?php

namespace YesWiki\Test\Render;

use YesWiki\Content\Service\PageManager;
use YesWiki\Render\Service\TemplateEngine;
use YesWiki\Render\Service\TemplateOverrides;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Ticket 18: Twig reads the database's override first, then the file on disk, then the program, and a new revision shows without a restart. */
class TemplateOverrideLoaderTest extends YesWikiTestCase
{
    private const FOLDER = 'custom/templates/core/yw-test-loader';

    private function engine(): TemplateEngine
    {
        return self::getWiki()->services->get(TemplateEngine::class);
    }

    private function overrides(): TemplateOverrides
    {
        return self::getWiki()->services->get(TemplateOverrides::class);
    }

    protected function tearDown(): void
    {
        $pages = self::getWiki()->services->get(PageManager::class);
        foreach (['core.yw-test-loader.only-in-the-database', 'core.yw-test-loader.both', 'yw-test-loader-top'] as $tag) {
            $pages->deleteOrphaned($tag);
        }
        exec('rm -rf ' . escapeshellarg(self::FOLDER));
        $this->overrides()->startNewRequest();
        parent::tearDown();
    }

    public function testAnOverrideOnlyTheDatabaseHoldsRenders(): void
    {
        $this->assertFalse($this->engine()->hasTemplate('@core/yw-test-loader/only-in-the-database.twig'));

        $this->overrides()->save('core/yw-test-loader/only-in-the-database.twig', 'from the database: {{ word }}');

        $this->assertTrue($this->engine()->hasTemplate('@core/yw-test-loader/only-in-the-database.twig'));
        $this->assertSame('from the database: hello', $this->engine()->render('@core/yw-test-loader/only-in-the-database.twig', ['word' => 'hello']));
    }

    /** A worker keeps its Twig environment between requests: the next revision must still be the one it renders. */
    public function testANewRevisionRendersInTheSameProcess(): void
    {
        $this->overrides()->save('core/yw-test-loader/only-in-the-database.twig', 'first');
        $this->assertSame('first', $this->engine()->render('@core/yw-test-loader/only-in-the-database.twig'));

        $this->overrides()->save('core/yw-test-loader/only-in-the-database.twig', 'second');
        $this->overrides()->startNewRequest();

        $this->assertSame('second', $this->engine()->render('@core/yw-test-loader/only-in-the-database.twig'));
    }

    public function testTheDatabaseWinsOverTheFileAndDeletingItBringsTheFileBack(): void
    {
        mkdir(self::FOLDER, 0o755, true);
        file_put_contents(self::FOLDER . '/both.twig', 'from the disk');
        $this->engine()->addOverrideDirectory('custom/templates/core/', 'core');
        $this->assertSame('from the disk', $this->engine()->render('@core/yw-test-loader/both.twig'));

        $this->overrides()->save('core/yw-test-loader/both.twig', 'from the database');
        $this->assertSame('from the database', $this->engine()->render('@core/yw-test-loader/both.twig'));

        $this->overrides()->delete('core/yw-test-loader/both.twig');
        $this->assertSame('from the disk', $this->engine()->render('@core/yw-test-loader/both.twig'));
    }

    public function testTheCustomNamespaceReadsTheTopOfTheFolder(): void
    {
        $this->overrides()->save('yw-test-loader-top.twig', 'at the top');

        $this->assertSame('at the top', $this->engine()->render('@custom/yw-test-loader-top.twig'));
    }
}
