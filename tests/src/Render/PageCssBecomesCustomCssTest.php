<?php

namespace YesWiki\Test\Render;

use YesWiki\Content\Service\PageManager;
use YesWiki\Files\Service\Storage;
use YesWiki\Render\Service\CustomCssService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The `PageCss` page folds into custom.css and is deleted, including after Markdown put a `\` on its lines. */
class PageCssBecomesCustomCssTest extends YesWikiTestCase
{
    private const TAG = 'PageCssAbsorbTest';

    private const CSS = ".banner {\n  color: #54368d;\n}\n.footer a {\n  text-decoration: none;\n}";

    private const MANGLED = ".banner {\\\n  color: #54368d;\\\n}\\\n.footer a {\\\n  text-decoration: none;\\\n}";

    private string $instance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->instance = sys_get_temp_dir() . '/page-css-' . bin2hex(random_bytes(4));
        mkdir($this->instance . '/custom', 0755, true);
    }

    protected function tearDown(): void
    {
        $this->pages()->deleteOrphaned(self::TAG);
        exec('rm -rf ' . escapeshellarg($this->instance));
        parent::tearDown();
    }

    private function pages(): PageManager
    {
        return $this->getWiki()->services->get(PageManager::class);
    }

    private function service(): CustomCssService
    {
        return new CustomCssService(Storage::rootedAt($this->instance));
    }

    private function page(string $css): void
    {
        $this->pages()->save(self::TAG, ['content' => $css], '', true);
        $this->assertNotNull($this->pages()->getOne(self::TAG, null, false, true));
    }

    private function assertPageIsGone(): void
    {
        $this->pages()->forget(self::TAG);
        $this->assertNull($this->pages()->getOne(self::TAG, null, false, true), 'the page is deleted');
    }

    public function testAMangledPageAlreadyInCustomCssIsJustDeleted(): void
    {
        $service = $this->service();
        $service->write("/* house */\n" . self::CSS . "\n");
        $this->page(self::MANGLED);

        $this->assertSame(CustomCssService::ALREADY_THERE, $service->absorbPage($this->pages(), self::TAG, true));
        $this->assertSame("/* house */\n" . self::CSS . "\n", $service->read());
        $this->assertPageIsGone();
    }

    public function testAMangledPageWithNoCustomCssIsWrittenWithoutItsBackslashes(): void
    {
        $service = $this->service();
        $this->page(self::MANGLED);

        $this->assertSame(CustomCssService::WRITTEN, $service->absorbPage($this->pages(), self::TAG, true));
        $this->assertSame(self::CSS . "\n", $service->read());
        $this->assertStringNotContainsString('\\', $service->read());
        $this->assertPageIsGone();
    }

    public function testAMangledPageCustomCssLacksIsAppended(): void
    {
        $service = $this->service();
        $service->write("body { margin: 0; }\n");
        $this->page(self::MANGLED);

        $this->assertSame(CustomCssService::APPENDED, $service->absorbPage($this->pages(), self::TAG, true));
        $this->assertSame("body { margin: 0; }\n\n" . self::CSS . "\n", $service->read());
        $this->assertPageIsGone();
    }

    public function testRunningAgainFindsNothingToDo(): void
    {
        $service = $this->service();
        $this->page(self::MANGLED);
        $service->absorbPage($this->pages(), self::TAG, true);
        $written = $service->read();

        $this->assertSame(CustomCssService::NO_PAGE, $service->absorbPage($this->pages(), self::TAG, true));
        $this->assertSame($written, $service->read());
    }

    public function testTheFirstMigrationAppendsRatherThanAskingForAMergeByHand(): void
    {
        $service = $this->service();
        $service->write("body { margin: 0; }\n");
        $this->page(self::CSS);

        $this->assertSame(CustomCssService::APPENDED, $service->absorbPage($this->pages(), self::TAG, false));
        $this->assertStringEndsWith(self::CSS . "\n", $service->read());
        $this->assertPageIsGone();
    }

    public function testWhitespaceDoesNotMakeTheSameCssNew(): void
    {
        $service = $this->service();
        $service->write('.banner{color:#54368d;}');

        $this->assertTrue($service->contains(".banner{color:#54368d;}\n"));
        $this->assertTrue($service->contains('  .banner{color:#54368d;}  \\'));
        $this->assertFalse($service->contains('.banner{color:red;}'));
    }
}
