<?php

namespace YesWiki\Test\Attach\Libs;

use Symfony\Component\HttpFoundation\Request;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * The ajax upload only accepts a well-formed entry-creation tag and never writes outside the upload directory.
 */
class AjaxUploadTest extends YesWikiTestCase
{
    private const PAGE_TAG = 'AjaxUploadTestPage';
    private const ESCAPED_DIR = 'cache';
    private const ESCAPED_PREFIX = 'AjaxUploadTestEscaped';

    private $wiki;
    private $previousGet;
    private $previousRequest;
    private $previousTag;
    private $previousPage;

    protected function setUp(): void
    {
        $this->wiki = $this->getWiki();
        $GLOBALS['wiki'] = $this->wiki;
        $this->wiki->services->get(PageManager::class)->save(self::PAGE_TAG, 'page', '', true);
        $aclService = $this->wiki->services->get(AclService::class);
        $aclService->save(self::PAGE_TAG, 'read', '*');
        $aclService->save(self::PAGE_TAG, 'write', '@admins');
        unset($_SESSION['user']);

        $this->previousGet = $_GET;
        $this->previousRequest = $this->wiki->request;
        $this->previousTag = $this->wiki->tag;
        $this->previousPage = $this->wiki->page;
        $this->wiki->tag = self::PAGE_TAG;
        $this->wiki->page = $this->wiki->services->get(PageManager::class)->getOne(self::PAGE_TAG);

        if (!class_exists('attach')) {
            include 'tools/attach/libs/attach.lib.php';
        }
        require_once 'tools/attach/libs/qq.lib.php';
    }

    protected function tearDown(): void
    {
        foreach (glob(self::ESCAPED_DIR . '/*' . self::ESCAPED_PREFIX . '*') as $leftover) {
            unlink($leftover);
        }
        $this->wiki->services->get(PageManager::class)->deleteOrphaned(self::PAGE_TAG);
        $this->wiki->services->get(AclService::class)->delete(self::PAGE_TAG);
        $_GET = $this->previousGet;
        $this->wiki->request = $this->previousRequest;
        $this->wiki->tag = $this->previousTag;
        $this->wiki->page = $this->previousPage;
    }

    private function runHandler(string $tempTag): array
    {
        $this->wiki->request = new Request(['tempTag' => $tempTag]);
        $_GET = ['tempTag' => $tempTag];

        return json_decode(htmlspecialchars_decode($this->wiki->Method('ajaxupload'), ENT_NOQUOTES), true);
    }

    private function prefix(): string
    {
        return $this->wiki->config['temp_tag_for_entry_creation'];
    }

    public function testAWellFormedTempTagOpensTheUpload()
    {
        $result = $this->runHandler($this->prefix() . '_abc123');

        $this->assertNotSame(_t('NO_RIGHT_TO_WRITE_IN_THIS_PAGE'), $result['error'] ?? null);
    }

    public function testATempTagHidingATraversalOnAnotherLineIsRefused()
    {
        $result = $this->runHandler('../../' . self::ESCAPED_DIR . "/x\n" . $this->prefix() . '_abc123');

        $this->assertSame(_t('NO_RIGHT_TO_WRITE_IN_THIS_PAGE'), $result['error'] ?? null);
    }

    public function testATempTagWithATrailingNewlineIsRefused()
    {
        $result = $this->runHandler($this->prefix() . "_abc123\n");

        $this->assertSame(_t('NO_RIGHT_TO_WRITE_IN_THIS_PAGE'), $result['error'] ?? null);
    }

    public function testTheUploaderNeverWritesOutsideTheUploadDirectory()
    {
        $_GET = ['tempTag' => '../' . self::ESCAPED_DIR . '/' . self::ESCAPED_PREFIX];
        $uploader = new \qqFileUploader(['txt'], 100000, true);
        $file = new class {
            public function getName()
            {
                return 'poc.txt';
            }

            public function getSize()
            {
                return 5;
            }

            public function save($path)
            {
                return file_put_contents($path, 'owned') !== false;
            }
        };
        (new \ReflectionProperty(\qqFileUploader::class, 'file'))->setValue($uploader, $file);

        $result = $uploader->handleUpload('files');

        $this->assertArrayHasKey('error', $result);
        $this->assertSame([], glob(self::ESCAPED_DIR . '/*' . self::ESCAPED_PREFIX . '*'));
    }
}
