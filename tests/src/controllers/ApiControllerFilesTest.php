<?php

namespace YesWiki\Test\Core\Controller;

use PHPUnit\Framework\Attributes\CoversMethod;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use YesWiki\Content\Api\FileApiController;
use YesWiki\Content\Service\FileManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\YesWikiRuntime;

require_once 'tests/YesWikiTestCase.php';

/**
 * Regression test for ticket 17 (attach absorbed into core): uploaded files are their
 * own independent Content entry (FileManager), not tied 1:1 to the page they were
 * uploaded from. Covers the real security fix this ticket makes: downloading now
 * enforces the file's own read ACL (seeded from the owning page at upload time) --
 * previously doDownload() performed no ownership ACL check at all.
 */
#[CoversMethod(FileApiController::class, 'uploadFile')]
#[CoversMethod(FileApiController::class, 'downloadFile')]
#[CoversMethod(FileApiController::class, 'getFiles')]
class ApiControllerFilesTest extends YesWikiTestCase
{
    private const PRIVATE_PAGE_TAG = 'ApiControllerFilesTestPrivatePage';

    public function testWikiExisting(): YesWikiRuntime
    {
        $wiki = $this->getWiki();
        $this->assertTrue($wiki->services->has(FileApiController::class));

        $pageManager = $wiki->services->get(PageManager::class);
        $pageManager->save(self::PRIVATE_PAGE_TAG, 'content', '', true);
        $wiki->services->get(AclService::class)->save(self::PRIVATE_PAGE_TAG, 'read', '@admins');

        return $wiki;
    }

    #[\PHPUnit\Framework\Attributes\Depends('testWikiExisting')]
    public function testUploadThenDownloadEnforcesOwningPageAclByDefault(YesWikiRuntime $wiki)
    {
        $controller = $wiki->services->get(FileApiController::class);
        $authenticationService = $wiki->services->get(AuthenticationService::class);
        $userManager = $wiki->services->get(UserManager::class);
        $admin = current(array_filter($userManager->getAll(), fn ($u) => $wiki->services->get(AclService::class)->isAdmin($u['name'])));
        $this->assertNotFalse($admin, 'need an existing admin user to exercise the write path');

        $tmpPath = sys_get_temp_dir() . '/ApiControllerFilesTest-' . uniqid() . '.txt';
        file_put_contents($tmpPath, 'hello world');
        $uploadedFile = new UploadedFile($tmpPath, 'my document.txt', 'text/plain', null, true);
        $fileManager = $wiki->services->get(FileManager::class);
        $tag = null;

        try {
            $authenticationService->login($admin);

            $request = Request::create('/api/files', 'POST', ['pageTag' => self::PRIVATE_PAGE_TAG]);
            $request->files->set('upFile', $uploadedFile);
            $response = $controller->uploadFile($request);
            $entry = json_decode($response->getContent(), true);
            $tag = $entry['tag'];

            $this->assertSame(201, $response->getStatusCode());
            $this->assertSame('my document.txt', $entry['original_filename']);
            $this->assertTrue($fileManager->isFileTag($tag));

            // the new file entry's read ACL was seeded from the private owning page --
            // an anonymous/non-admin request must be denied, not silently served
            $authenticationService->logout();
            $this->expectException(\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException::class);
            $controller->downloadFile(Request::create("/api/files/$tag/download"), $tag);
        } finally {
            $authenticationService->logout();
            if ($tag !== null) {
                $fileManager->delete($tag);
            }
            if (file_exists($tmpPath)) {
                unlink($tmpPath);
            }
        }
    }

    #[\PHPUnit\Framework\Attributes\Depends('testWikiExisting')]
    public function testPublicOptOutAllowsAnonymousDownload(YesWikiRuntime $wiki)
    {
        $controller = $wiki->services->get(FileApiController::class);
        $authenticationService = $wiki->services->get(AuthenticationService::class);
        $userManager = $wiki->services->get(UserManager::class);
        $aclService = $wiki->services->get(AclService::class);
        $admin = current(array_filter($userManager->getAll(), fn ($u) => $wiki->services->get(AclService::class)->isAdmin($u['name'])));

        $tmpPath = sys_get_temp_dir() . '/ApiControllerFilesTest-' . uniqid() . '.txt';
        file_put_contents($tmpPath, 'public content');
        $uploadedFile = new UploadedFile($tmpPath, 'public-file.txt', 'text/plain', null, true);
        $fileManager = $wiki->services->get(FileManager::class);
        $tag = null;

        try {
            $authenticationService->login($admin);

            $request = Request::create('/api/files', 'POST', ['pageTag' => self::PRIVATE_PAGE_TAG]);
            $request->files->set('upFile', $uploadedFile);
            $response = $controller->uploadFile($request);
            $entry = json_decode($response->getContent(), true);
            $tag = $entry['tag'];

            // explicit public opt-out: override the seeded (private) ACL to '*'
            $aclService->save($tag, 'read', '*');

            $authenticationService->logout();
            $downloadResponse = $controller->downloadFile(Request::create("/api/files/$tag/download"), $tag);
            $this->assertSame(200, $downloadResponse->getStatusCode());
        } finally {
            $authenticationService->logout();
            if ($tag !== null) {
                $fileManager->delete($tag);
            }
            if (file_exists($tmpPath)) {
                unlink($tmpPath);
            }
        }
    }

    #[\PHPUnit\Framework\Attributes\Depends('testWikiExisting')]
    public function testGetFilesOnlyListsFilesTheRequesterCanRead(YesWikiRuntime $wiki)
    {
        $controller = $wiki->services->get(FileApiController::class);
        $authenticationService = $wiki->services->get(AuthenticationService::class);
        $userManager = $wiki->services->get(UserManager::class);
        $admin = current(array_filter($userManager->getAll(), fn ($u) => $wiki->services->get(AclService::class)->isAdmin($u['name'])));

        $tmpPath = sys_get_temp_dir() . '/ApiControllerFilesTest-' . uniqid() . '.txt';
        file_put_contents($tmpPath, 'listing test');
        $uploadedFile = new UploadedFile($tmpPath, 'unique-listing-marker.txt', 'text/plain', null, true);
        $fileManager = $wiki->services->get(FileManager::class);
        $tag = null;

        try {
            $authenticationService->login($admin);
            $request = Request::create('/api/files', 'POST', ['pageTag' => self::PRIVATE_PAGE_TAG]);
            $request->files->set('upFile', $uploadedFile);
            $response = $controller->uploadFile($request);
            $tag = json_decode($response->getContent(), true)['tag'];

            $listResponse = $controller->getFiles(Request::create('/api/files', 'GET', ['search' => 'unique-listing-marker']));
            $asAdmin = json_decode($listResponse->getContent(), true);
            $this->assertNotEmpty($asAdmin, 'the admin who uploaded it must see it in the search results');

            $authenticationService->logout();
            $listResponseAnon = $controller->getFiles(Request::create('/api/files', 'GET', ['search' => 'unique-listing-marker']));
            $asAnon = json_decode($listResponseAnon->getContent(), true);
            $this->assertEmpty($asAnon, 'an anonymous requester must not see a file from a private page');
        } finally {
            $authenticationService->logout();
            if ($tag !== null) {
                $fileManager->delete($tag);
            }
            if (file_exists($tmpPath)) {
                unlink($tmpPath);
            }
        }
    }
}
