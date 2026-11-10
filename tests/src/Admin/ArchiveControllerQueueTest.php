<?php

namespace YesWiki\Test\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use YesWiki\Admin\Api\ArchiveApiController;
use YesWiki\Admin\Controller\ArchiveController;
use YesWiki\Admin\Service\ArchiveService;
use YesWiki\Core\ApiResponse;
use YesWiki\Files\Service\Storage;
use YesWiki\Identity\Service\InputFilter;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\ThrowableFormatter;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * The HTTP layer of backups and restores the queue runs: what a POST asks for reaches the service as the right flags and folders, and a queue that refuses answers 500.
 *
 * The service is a double that notes what it is asked: how it queues and runs is ArchiveQueueTest's.
 */
#[CoversClass(ArchiveController::class)]
#[CoversClass(ArchiveApiController::class)]
class ArchiveControllerQueueTest extends YesWikiTestCase
{
    private ?string $action = null;

    protected function setUp(): void
    {
        parent::setUp();
        self::getWiki();
    }

    /**
     * @param array<string, mixed> $post
     * @param list<string>         $include
     * @param list<string>         $exclude
     * @param list<string>|null    $only
     */
    #[DataProvider('flagsAsTheBrowserSendsThem')]
    public function testTheFlagsAndFoldersOfTheRequestReachTheBackup(array $post, bool $files, bool $database, array $include, array $exclude, ?array $only): void
    {
        $archives = $this->createMock(ArchiveService::class);
        $archives->expects($this->once())->method('startArchive')->with($files, $database, $include, $exclude, $only)->willReturn('abc123');

        $response = $this->post($archives, 'startArchive', ['params' => $post]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['uid' => 'abc123'], json_decode((string)$response->getContent(), true));
    }

    /** @return array<string, array{array<string, mixed>, bool, bool, list<string>, list<string>, list<string>|null}> */
    public static function flagsAsTheBrowserSendsThem(): array
    {
        return [
            'both, as "true" and "1"' => [['savefiles' => 'true', 'savedatabase' => '1'], true, true, [], [], null],
            'only the database' => [['savefiles' => 'false', 'savedatabase' => 'true'], false, true, [], [], null],
            'a flag left out is off' => [['savefiles' => '1'], true, false, [], [], null],
            'folder lists as arrays or comma separated strings' => [
                ['savefiles' => '1', 'savedatabase' => '1', 'foldersToInclude' => ['custom', ' files '], 'foldersToExclude' => 'cache, tmp', 'onlyFolders' => 'custom,files'],
                true, true, ['custom', 'files'], ['cache', 'tmp'], ['custom', 'files'],
            ],
            'empty folder lists name none' => [['savefiles' => '1', 'onlyFolders' => []], true, false, [], [], null],
        ];
    }

    public function testABackupTheQueueRefusesAnswers500(): void
    {
        $archives = $this->createStub(ArchiveService::class);
        $archives->method('startArchive')->willReturn('');

        $response = $this->post($archives, 'startArchive', ['params' => ['savefiles' => '1']]);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(['error' => _t('AU_BACKUP_NOT_QUEUED')], json_decode((string)$response->getContent(), true));
    }

    /** @param array<string, mixed> $post */
    #[DataProvider('restoreFlags')]
    public function testTheFlagsOfARestoreReachTheService(array $post, bool $files, bool $database, bool $rewrite): void
    {
        $archives = $this->createMock(ArchiveService::class);
        $archives->expects($this->once())->method('startRestore')->with('backup.zip', $files, $database, $rewrite)->willReturn('xyz');

        $response = $this->post($archives, 'restore', $post, 'backup.zip');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['uid' => 'xyz'], json_decode((string)$response->getContent(), true));
    }

    /** @return array<string, array{array<string, mixed>, bool, bool, bool}> */
    public static function restoreFlags(): array
    {
        return [
            'everything unless told otherwise' => [[], true, true, true],
            'files only' => [['restoreFiles' => 'true', 'restoreDatabase' => 'false', 'rewriteUrls' => 'false'], true, false, false],
            'database without its urls rewritten' => [['restoreFiles' => '0', 'restoreDatabase' => '1', 'rewriteUrls' => '0'], false, true, false],
        ];
    }

    public function testARestoreTheQueueRefusesAnswers500(): void
    {
        $archives = $this->createStub(ArchiveService::class);
        $archives->method('startRestore')->willReturn('');

        $response = $this->post($archives, 'restore', [], 'backup.zip');

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(['error' => _t('AU_BACKUP_NOT_QUEUED')], json_decode((string)$response->getContent(), true));
    }

    /** What the browser polls: the service's word on the uid, as it is, with no run forced on the way. */
    public function testTheStatusOfARunIsTheServicesWordOnItsUid(): void
    {
        $archives = $this->createMock(ArchiveService::class);
        $status = ['started' => true, 'running' => true, 'finished' => false, 'failed' => false, 'state' => 'pending'];
        $archives->expects($this->once())->method('getUIDStatus')->with('abc123')->willReturn($status);
        $controller = $this->controller($archives, null);

        $response = $controller->getArchiveStatus('abc123');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($status, json_decode((string)$response->getContent(), true));
    }

    public function testTheStatusRouteHandsTheUidToTheController(): void
    {
        $archives = $this->createMock(ArchiveService::class);
        $archives->expects($this->once())->method('getUIDStatus')->with('abc123')->willReturn(['state' => 'handled']);
        $api = new ArchiveApiController();
        $api->setServices(new ServiceLocator([ArchiveController::class => fn (): ArchiveController => $this->controller($archives, null)]));

        $response = $api->getArchiveStatus('abc123');

        $this->assertSame(['state' => 'handled'], json_decode((string)$response->getContent(), true));
    }

    /**
     * @param array<string, mixed> $post
     */
    private function post(ArchiveService $archives, string $action, array $post, ?string $id = null): ApiResponse
    {
        $this->action = $action;

        return $this->controller($archives, Request::create('/?api/archives', 'POST', $post))->manageArchiveAction($id);
    }

    /** The controller on that service, the request's `action` being what filter_input() would have read from the POST. */
    private function controller(ArchiveService $archives, ?Request $request): ArchiveController
    {
        $controller = new ArchiveController($archives, new ParameterBag([]), $this->createStub(Storage::class));
        $current = new CurrentRequest();
        if ($request !== null) {
            $current->replace($request);
        }
        $filter = $this->createStub(InputFilter::class);
        $filter->method('filterInput')->willReturnCallback(fn (): mixed => $this->action);
        $controller->setServices(new ServiceLocator([
            CurrentRequest::class => static fn (): CurrentRequest => $current,
            InputFilter::class => static fn (): InputFilter => $filter,
            ThrowableFormatter::class => static fn (): ThrowableFormatter => new ThrowableFormatter(),
        ]));

        return $controller;
    }
}
