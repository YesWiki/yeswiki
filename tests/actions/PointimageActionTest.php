<?php

namespace YesWiki\Test\Actions;

use YesWiki\Core\Exception\ExitException;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * {{pointimage}} writes markers on its own page, and only for a visitor allowed to write there.
 */
class PointimageActionTest extends YesWikiTestCase
{
    private const IMAGE = 'pointimage-test.png';
    private const TARGET_TAG = 'PointimageTargetPage';
    private const TARGET_BODY = 'untouched';

    private $wiki;
    private $pageManager;
    private $aclService;
    private $datapagetag;

    protected function setUp(): void
    {
        $this->wiki = $this->getWiki();
        $this->pageManager = $this->wiki->services->get(PageManager::class);
        $this->aclService = $this->wiki->services->get(AclService::class);

        $this->datapagetag = $this->wiki->GetPageTag() . 'PIpointimagetest';

        $this->pageManager->save(self::TARGET_TAG, self::TARGET_BODY, '', true);
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
        $_POST = [];
    }

    private function cleanDataPage(): void
    {
        $this->pageManager->deleteOrphaned($this->datapagetag);
        $this->aclService->delete($this->datapagetag);
    }

    private function post(string $pagetag, array $extra = []): void
    {
        $_POST = array_merge([
            'title' => 'marker title',
            'description' => 'marker description',
            'pagetag' => $this->wiki->config['base_url'] . $pagetag,
            'image_x' => '12',
            'image_y' => '34',
            'color' => 'green',
        ], $extra);
    }

    private function runAction(): string
    {
        try {
            return $this->wiki->Action('pointimage', 1, ['file' => self::IMAGE]);
        } catch (ExitException $e) {
            return '';
        }
    }

    public function testAVisitorCannotWriteOnAPageOfTheirChoosing()
    {
        $this->post(self::TARGET_TAG);

        $this->runAction();

        $this->assertSame(self::TARGET_BODY, $this->pageManager->getOne(self::TARGET_TAG)['body']);
    }

    public function testAPostWithoutAValidTokenWritesNothing()
    {
        $this->aclService->save($this->datapagetag, 'write', '*');
        $this->post($this->datapagetag);

        $this->runAction();

        $this->assertNull($this->pageManager->getOne($this->datapagetag));
    }

    public function testTheFormCarriesACsrfToken()
    {
        $output = $this->runAction();

        $this->assertMatchesRegularExpression(
            '/<input type="hidden" name="csrf-token" value="[^"]+" \/>/',
            $output,
            '`csrf-token` input missing from the pointimage form'
        );
    }

    public function testAStoredMarkerCannotCarryHtmlIntoTheImage()
    {
        $this->pageManager->save(
            $this->datapagetag,
            "~~\"\"<!--12-34-\"><script>alert(1)</script>--><!--title--><img src=x onerror=alert(1)><!--/title-->\"\"\n\"\"<!--desc-->\"\"hello\"\"<!--/desc-->\n\"\"~~",
            '',
            true
        );

        $output = $this->runAction();

        $this->assertStringContainsString('img-marker', $output);
        $this->assertStringNotContainsString('<script>', $output);
        $this->assertStringNotContainsString('<img src=x', $output);
        $this->assertStringContainsString('&amp;lt;img src=x', $output);
        $this->assertStringContainsString('background:&quot;&gt;&lt;script&gt;', $output);
    }
}
