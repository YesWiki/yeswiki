<?php

namespace YesWiki\Test\Core\Controller;

use PHPUnit\Framework\Attributes\CoversMethod;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use YesWiki\Content\Api\FileApiController;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\FileManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiRuntime;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Regression test for ticket 17 (attach absorbed into core): uploaded files are their own independent Content entry (FileManager), not tied 1:1 to the page they were uploaded from.
 */
#[CoversMethod(FileApiController::class, 'uploadFile')]
#[CoversMethod(FileApiController::class, 'downloadFile')]
#[CoversMethod(FileApiController::class, 'getFiles')]
class ApiControllerFilesTest extends YesWikiTestCase
{
    private const PRIVATE_PAGE_TAG = 'ApiControllerFilesTestPrivatePage';

    /** The fixtures go when the tests do. */
    public static function tearDownAfterClass(): void
    {
        $pageManager = self::getWiki()->services->get(PageManager::class);
        foreach ([self::PRIVATE_PAGE_TAG] as $tag) {
            $pageManager->deleteOrphaned($tag);
        }
    }

    public function testWikiExisting(): YesWikiRuntime
    {
        $wiki = $this->getWiki();
        $this->assertTrue($wiki->services->has(FileApiController::class));

        $pageManager = $wiki->services->get(PageManager::class);
        $pageManager->save(self::PRIVATE_PAGE_TAG, [PageBody::CONTENT => 'content'], '', true);
        $wiki->services->get(AclService::class)->save(self::PRIVATE_PAGE_TAG, 'read', '@admins');

        return $wiki;
    }

    #[\PHPUnit\Framework\Attributes\Depends('testWikiExisting')]
    public function testUploadThenDownloadEnforcesOwningPageAclByDefault(YesWikiRuntime $wiki): void
    {
        $controller = $wiki->services->get(FileApiController::class);
        $authenticationService = $wiki->services->get(AuthenticationService::class);
        $userManager = $wiki->services->get(UserManager::class);
        $admin = current(array_filter($userManager->getAll(), fn ($u) => $wiki->services->get(AclService::class)->isAdmin($u['name'])));
        $this->assertInstanceOf(\YesWiki\Identity\Entity\User::class, $admin, 'these routes need an admin account');
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
            $entry = json_decode($this->jsonBody($response), true);
            $tag = $entry['tag'];

            $this->assertSame(201, $response->getStatusCode());
            $this->assertSame('my document.txt', $entry['original_filename']);
            $this->assertTrue($fileManager->isFileTag($tag));

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
    public function testPublicOptOutAllowsAnonymousDownload(YesWikiRuntime $wiki): void
    {
        $controller = $wiki->services->get(FileApiController::class);
        $authenticationService = $wiki->services->get(AuthenticationService::class);
        $userManager = $wiki->services->get(UserManager::class);
        $aclService = $wiki->services->get(AclService::class);
        $admin = current(array_filter($userManager->getAll(), fn ($u) => $wiki->services->get(AclService::class)->isAdmin($u['name'])));
        $this->assertInstanceOf(\YesWiki\Identity\Entity\User::class, $admin, 'these routes need an admin account');

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
            $entry = json_decode($this->jsonBody($response), true);
            $tag = $entry['tag'];

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
    public function testGetFilesOnlyListsFilesTheRequesterCanRead(YesWikiRuntime $wiki): void
    {
        $controller = $wiki->services->get(FileApiController::class);
        $authenticationService = $wiki->services->get(AuthenticationService::class);
        $userManager = $wiki->services->get(UserManager::class);
        $admin = current(array_filter($userManager->getAll(), fn ($u) => $wiki->services->get(AclService::class)->isAdmin($u['name'])));
        $this->assertInstanceOf(\YesWiki\Identity\Entity\User::class, $admin, 'these routes need an admin account');

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
            $tag = json_decode($this->jsonBody($response), true)['tag'];

            $listResponse = $controller->getFiles(Request::create('/api/files', 'GET', ['search' => 'unique-listing-marker']));
            $asAdmin = json_decode($this->jsonBody($listResponse), true);
            $this->assertNotEmpty($asAdmin, 'the admin who uploaded it must see it in the search results');

            $authenticationService->logout();
            $listResponseAnon = $controller->getFiles(Request::create('/api/files', 'GET', ['search' => 'unique-listing-marker']));
            $asAnon = json_decode($this->jsonBody($listResponseAnon), true);
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

    /**
     * The picker filters by family and by extension, and both are derived here rather than stored -- so what the listing says a file is has to survive the MIME sniffer having no idea.
     */
    #[\PHPUnit\Framework\Attributes\Depends('testWikiExisting')]
    public function testFamilyIsDerivedFromTheExtensionWhenTheMimeTypeIsUseless(YesWikiRuntime $wiki): void
    {
        $this->assertSame('document', FileManager::familyOf('text/plain', 'accounts.csv'));
        $this->assertSame('image', FileManager::familyOf('application/octet-stream', 'holiday.JPG'));
        $this->assertSame('video', FileManager::familyOf('', 'clip.mp4'));
        $this->assertSame('audio', FileManager::familyOf('', 'interview.ogg'));

        $this->assertSame('image', FileManager::familyOf('image/png', 'screenshot'));
        $this->assertSame('document', FileManager::familyOf('application/vnd.oasis.opendocument.text', 'notes'));
        $this->assertSame('other', FileManager::familyOf('application/zip', 'backup.zip'));

        $this->assertSame('jpg', FileManager::extensionOf('holiday.JPG'));
        $this->assertSame('', FileManager::extensionOf('README'));
    }

    #[\PHPUnit\Framework\Attributes\Depends('testWikiExisting')]
    public function testGetFilesNarrowsByFamilyAndSearchesTheExtension(YesWikiRuntime $wiki): void
    {
        $controller = $wiki->services->get(FileApiController::class);
        $authenticationService = $wiki->services->get(AuthenticationService::class);
        $userManager = $wiki->services->get(UserManager::class);
        $admin = current(array_filter($userManager->getAll(), fn ($u) => $wiki->services->get(AclService::class)->isAdmin($u['name'])));
        $this->assertInstanceOf(\YesWiki\Identity\Entity\User::class, $admin, 'these routes need an admin account');

        $fileManager = $wiki->services->get(FileManager::class);
        $marker = 'familyfiltermarker';
        $uploads = ["$marker-photo.png" => 'image/png', "$marker-notes.txt" => 'text/plain'];
        $tags = [];
        $tmpPaths = [];

        try {
            $authenticationService->login($admin);
            foreach ($uploads as $filename => $mimeType) {
                $tmpPath = sys_get_temp_dir() . '/ApiControllerFilesTest-' . uniqid() . '-' . $filename;
                file_put_contents($tmpPath, 'family filter test');
                $request = Request::create('/api/files', 'POST', ['pageTag' => self::PRIVATE_PAGE_TAG]);
                $request->files->set('upFile', new UploadedFile($tmpPath, $filename, $mimeType, null, true));
                $uploaded = json_decode($this->jsonBody($controller->uploadFile($request)), true);
                $tags[] = $uploaded['tag'];
                $tmpPaths[] = $tmpPath;

                $this->assertArrayHasKey('family', $uploaded);
                $this->assertArrayHasKey('extension', $uploaded);
            }

            $all = $this->listFiles($controller, ['search' => $marker]);
            $this->assertCount(2, $all);

            $images = $this->listFiles($controller, ['search' => $marker, 'family' => 'image']);
            $this->assertSame(["$marker-photo.png"], array_column($images, 'original_filename'));
            $this->assertSame('png', $images[0]['extension']);

            $documents = $this->listFiles($controller, ['search' => $marker, 'family' => 'document']);
            $this->assertSame(["$marker-notes.txt"], array_column($documents, 'original_filename'));

            $byExtension = $this->listFiles($controller, ['search' => 'png']);
            $this->assertContains("$marker-photo.png", array_column($byExtension, 'original_filename'));
            $this->assertNotContains("$marker-notes.txt", array_column($byExtension, 'original_filename'));
        } finally {
            $authenticationService->logout();
            foreach ($tags as $tag) {
                $fileManager->delete($tag);
            }
            foreach ($tmpPaths as $tmpPath) {
                if (file_exists($tmpPath)) {
                    unlink($tmpPath);
                }
            }
        }
    }

    /**
     * @param array<string, string> $query
     *
     * @return list<array<string, mixed>>
     */
    private function listFiles(FileApiController $controller, array $query): array
    {
        return json_decode($this->jsonBody($controller->getFiles(Request::create('/api/files', 'GET', $query))), true);
    }

    /**
     * A route that failed answers with no body, and json_decode(false) is null -- which every
     * caller here would read as an empty result rather than as the failure it is.
     */
    private function jsonBody(Response $response): string
    {
        $content = $response->getContent();
        $this->assertIsString($content, 'the route must answer with a body');

        return $content;
    }
}
