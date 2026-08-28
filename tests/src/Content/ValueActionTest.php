<?php

namespace YesWiki\Test\Content;

use YesWiki\Content\Service\RemotePageCache;
use YesWiki\Render\Service\ActionRunner;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * `{{value}}` reads a remote entry: it must refuse an address inside the network and must treat the field name as a name, not as part of its regex.
 *
 * Ported from doryphore-dev's ValeurActionTest; the action is `value` here and the remote page is seeded through RemotePageCache rather than a `$GLOBALS['externalpage']` entry.
 */
class ValueActionTest extends YesWikiTestCase
{
    private const REMOTE_URL = 'http://value-action-test.example.com';
    private const REMOTE_PAGE = '<div data-id="bf_ville"><span class="BAZ_label">Ville</span>'
        . '<span class="BAZ_texte">Bordeaux</span></div>';

    private \YesWiki\Core\YesWikiRuntime $wiki;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wiki = $this->getWiki();
        $this->seedRemotePage(self::REMOTE_PAGE);
    }

    private function seedRemotePage(string $page): void
    {
        $this->wiki->services->get(RemotePageCache::class)->startNewRequest();
        // the cache is keyed on the action's `url` argument, and only the fetch appends `/html`
        $this->wiki->services->get(RemotePageCache::class)->get(
            self::REMOTE_URL,
            static fn () => $page
        );
    }

    /**
     * @param array<string, string> $arguments
     */
    private function value(array $arguments): string
    {
        return (string)$this->wiki->services->get(ActionRunner::class)
            ->action('value', array_merge(['url' => self::REMOTE_URL], $arguments));
    }

    public function testItReadsTheNamedField(): void
    {
        $this->assertSame('Bordeaux', trim($this->value(['field' => 'bf_ville'])));
    }

    public function testAFieldNameIsNotAPattern(): void
    {
        $this->assertSame('nothing', trim($this->value(['field' => 'bf_.*', 'default' => 'nothing'])));
    }

    public function testAFieldNameCannotBreakTheRegex(): void
    {
        $output = $this->value(['field' => 'bf_ville/', 'default' => 'nothing']);

        $this->assertSame('nothing', trim($output));
        $this->assertSame(PREG_NO_ERROR, preg_last_error(), preg_last_error_msg());
    }

    public function testAnAddressInsideTheNetworkIsRefused(): void
    {
        foreach (['http://127.0.0.1:9999', 'http://localhost:9999', 'http://192.168.1.110:9999', 'http://[::1]:9999'] as $url) {
            $this->wiki->services->get(RemotePageCache::class)->startNewRequest();
            $output = (string)$this->wiki->services->get(ActionRunner::class)
                ->action('value', ['url' => $url, 'field' => 'bf_titre']);

            $this->assertStringContainsString('alert-danger', $output, "$url was not refused");
        }
    }

    public function testANonHttpSchemeIsRefused(): void
    {
        $this->wiki->services->get(RemotePageCache::class)->startNewRequest();
        $output = (string)$this->wiki->services->get(ActionRunner::class)
            ->action('value', ['url' => 'file:///etc/passwd', 'field' => 'bf_titre']);

        $this->assertStringContainsString('alert-danger', $output);
    }
}
