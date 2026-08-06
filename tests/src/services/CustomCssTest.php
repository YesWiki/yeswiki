<?php

namespace YesWiki\Test\Core\Service;

use Symfony\Component\Security\Csrf\CsrfTokenManager;
use YesWiki\Kernel\Service\AssetRegistry;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Render\Service\CoreAssets;
use YesWiki\Render\Service\CustomCssService;
use YesWiki\Render\Service\ThemeManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * The wiki's own stylesheet is a file now, not the `PageCss` page (ticket 30).
 *
 * Two things have to hold and neither is obvious from reading the code. It must be linked
 * **last**, because that is the cascade position the inlined page had and a webmaster's CSS
 * is written expecting to win. And it must be linked as a **file**, not inlined: `PageCss`
 * had to be inlined only because the `/raw` handler serves `text/plain`, and carrying that
 * workaround forward would have thrown away the point of the move.
 */
class CustomCssTest extends YesWikiTestCase
{
    private ?string $saved = null;
    private bool $existed = false;

    protected function setUp(): void
    {
        parent::setUp();
        $service = $this->service();
        $this->existed = $service->exists();
        $this->saved = $this->existed ? $service->read() : null;
    }

    protected function tearDown(): void
    {
        // this wiki's real stylesheet: put back exactly what was there
        $service = $this->service();
        if ($this->existed) {
            $service->write((string)$this->saved);
        } elseif ($service->exists()) {
            $service->write('');
        }
        parent::tearDown();
    }

    private function service(): CustomCssService
    {
        return $this->getWiki()->services->get(CustomCssService::class);
    }

    public function testItRoundTripsWhatWasWritten(): void
    {
        $service = $this->service();
        $css = ":root { --probe: 1; }\n.probe { color: red; }\n";

        $service->write($css);

        $this->assertTrue($service->exists());
        $this->assertSame($css, $service->read());
        $this->assertStringEndsWith('/' . CustomCssService::FILENAME, $service->path());
    }

    /** An empty box means "no custom CSS", not "a stylesheet with nothing in it". */
    public function testWritingNothingRemovesTheFile(): void
    {
        $service = $this->service();
        $service->write('.probe { color: red; }');
        $this->assertTrue($service->exists());

        $service->write("   \n  ");

        $this->assertFalse($service->exists(), 'an empty stylesheet must not leave a <link> to nothing behind');
        $this->assertSame('', $service->read());
    }

    public function testItIsLinkedLastAndAsAFile(): void
    {
        $wiki = $this->getWiki();
        $this->service()->write('.probe { color: red; }');

        $html = $this->renderedAssets();

        preg_match_all('~[\w./-]*\.css~', $html, $matches);
        $stylesheets = array_values(array_unique($matches[0]));
        $custom = array_values(array_filter(
            $stylesheets,
            static fn (string $file): bool => str_ends_with($file, 'custom/styles/' . CustomCssService::FILENAME)
        ));

        $this->assertCount(1, $custom, 'the wiki stylesheet must be linked exactly once');
        $this->assertSame(
            end($stylesheets),
            $custom[0],
            'it must be last: it is what a webmaster writes expecting to override everything above it'
        );
        $this->assertStringNotContainsString(
            '.probe { color: red; }',
            $html,
            'it must be a linked file, not inlined -- inlining was a workaround for /raw serving text/plain'
        );
    }

    public function testAnAbsentStylesheetIsNotLinked(): void
    {
        $this->service()->write('');

        $html = $this->renderedAssets();

        $this->assertStringNotContainsString(
            'custom/styles/' . CustomCssService::FILENAME,
            $html,
            'a wiki with no custom CSS must not link a file that is not there'
        );
    }

    /**
     * A fresh CoreAssets, for the reason CoreAssetsTest gives: "register once per request"
     * is instance state and the container hands out a shared one.
     */
    private function renderedAssets(): string
    {
        $services = $this->getWiki()->services;
        $registry = $services->get(AssetRegistry::class);
        $registry->drain();

        (new CoreAssets(
            $registry,
            $services->get(ThemeManager::class),
            $services->get(CustomCssService::class),
            $services->get(RuntimeConfig::class),
            $services->get(PageContext::class),
            $services->get(CsrfTokenManager::class),
        ))->register();

        return $registry->drain()->toHtml();
    }
}
