<?php

namespace YesWiki\Test\Actions;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Exception\ExitException;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Render\Service\ActionRunner;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * `{{pointimage}}` writes markers on its own page, and only for a visitor allowed to write there (GHSA-7547-q56p-h99v).
 *
 * A valid token cannot be presented from the command line -- the checker reads the real request -- so what is pinned here is that a write is refused without one, that the page written to is never the one the request names, and that a stored marker cannot carry markup into the page.
 */
class PointimageActionTest extends YesWikiTestCase
{
    private const IMAGE = 'pointimage-test.png';
    private const TARGET_TAG = 'PointimageTargetPage';
    private const TARGET_BODY = 'untouched';
    private const HOST_TAG = 'PointimageHostPage';
    private const DATA_BODY = 'no markers yet';

    private \YesWiki\Core\YesWikiRuntime $wiki;
    private PageManager $pageManager;
    private AclService $aclService;
    private string $datapagetag = '';
    /** @var array<string, mixed> */
    private array $previousPost = [];
    private string $previousTag = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->wiki = $this->getWiki();
        $this->pageManager = $this->wiki->services->get(PageManager::class);
        $this->aclService = $this->wiki->services->get(AclService::class);
        $this->previousPost = $_POST;
        $this->previousTag = $this->wiki->services->get(PageContext::class)->getTag();

        // the action writes its markers on a page named after the page it is rendered on
        $this->wiki->services->get(PageContext::class)->setTag(self::HOST_TAG);
        $this->datapagetag = self::HOST_TAG . 'PIpointimagetest';

        $this->pageManager->save(self::TARGET_TAG, [PageBody::CONTENT => self::TARGET_BODY], '', true);
        $this->aclService->save(self::TARGET_TAG, 'write', '@admins');
        $this->cleanDataPage();

        unset($_SESSION['user']);
        $_POST = [];
    }

    protected function tearDown(): void
    {
        $this->pageManager->deleteOrphaned(self::TARGET_TAG);
        $this->aclService->delete(self::TARGET_TAG);
        $this->cleanDataPage();
        $_POST = $this->previousPost;
        $this->wiki->services->get(PageContext::class)->setTag($this->previousTag);
        parent::tearDown();
    }

    private function cleanDataPage(): void
    {
        $this->pageManager->deleteOrphaned($this->datapagetag);
        $this->aclService->delete($this->datapagetag);
    }

    /** @param array<string, string> $extra */
    private function post(string $pagetag, array $extra = []): void
    {
        $_POST = array_merge([
            'title' => 'marker title',
            'description' => 'marker description',
            'pagetag' => (string)$this->wiki->services->get(RuntimeConfig::class)['base_url'] . $pagetag,
            'image_x' => '12',
            'image_y' => '34',
            'color' => 'green',
        ], $extra);
    }

    private function runAction(): string
    {
        try {
            return (string)$this->wiki->services->get(ActionRunner::class)
                ->action('pointimage', ['file' => self::IMAGE]);
        } catch (ExitException $e) {
            return '';
        }
    }

    private function bodyOf(string $tag): ?string
    {
        $page = $this->pageManager->getOne($tag, null, false, true);

        return $page === null ? null : PageBody::content($page['body'] ?? []);
    }

    public function testAVisitorCannotWriteOnAPageOfTheirChoosing(): void
    {
        $this->post(self::TARGET_TAG);

        $this->runAction();

        $this->assertSame(self::TARGET_BODY, $this->bodyOf(self::TARGET_TAG));
    }

    /** Even where the visitor may write, a post carrying no valid token adds nothing. */
    public function testAPostWithoutAValidTokenWritesNothing(): void
    {
        $this->pageManager->save($this->datapagetag, [PageBody::CONTENT => self::DATA_BODY], '', true);
        $this->aclService->save($this->datapagetag, 'write', '*');
        $this->post($this->datapagetag);

        $this->runAction();

        $this->assertSame(self::DATA_BODY, $this->bodyOf($this->datapagetag));
    }

    public function testTheFormCarriesACsrfToken(): void
    {
        $output = $this->runAction();

        $this->assertMatchesRegularExpression(
            '/<input type="hidden" name="csrf-token" value="[^"]+" \/>/',
            $output,
            'the csrf-token input is missing from the pointimage form'
        );
    }

    public function testAStoredMarkerCannotCarryHtmlIntoTheImage(): void
    {
        $this->pageManager->save(
            $this->datapagetag,
            [PageBody::CONTENT => "~~\"\"<!--12-34-\"><script>alert(1)</script>--><!--title--><img src=x onerror=alert(1)><!--/title-->\"\"\n\"\"<!--desc-->\"\"hello\"\"<!--/desc-->\n\"\"~~"],
            '',
            true
        );

        $output = $this->runAction();

        $this->assertStringContainsString('img-marker', $output);
        $this->assertStringNotContainsString('<script>', $output);
        $this->assertStringNotContainsString('<img src=x', $output);
    }
}
