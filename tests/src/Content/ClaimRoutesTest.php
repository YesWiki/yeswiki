<?php

namespace YesWiki\Test\Content;

use PHPUnit\Framework\Attributes\CoversMethod;
use Symfony\Component\HttpFoundation\Request;
use YesWiki\Content\Api\PageApiController;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Ticket 35: what the `/PageName/claim` handler did, as two API routes.
 *
 * Both are privileged in a way the handler's name hid. Claiming an *unowned* page grants the caller
 * write access to something they did not have it on, so "has no owner yet" is the entire security
 * model and the negative case matters more than the positive one. Setting comment access hands out
 * a permission, so it is owner-or-admin only.
 */
#[CoversMethod(PageApiController::class, 'claimPage')]
#[CoversMethod(PageApiController::class, 'setCommentsAccess')]
class ClaimRoutesTest extends YesWikiTestCase
{
    private const UNOWNED = 'TestTicket35Unowned';
    private const OWNED = 'TestTicket35Owned';

    /** @var list<callable> */
    private array $cleanups = [];

    protected function tearDown(): void
    {
        foreach (array_reverse($this->cleanups) as $cleanup) {
            $cleanup();
        }
        $this->cleanups = [];
    }

    private function controller(): PageApiController
    {
        return $this->getWiki()->services->get(PageApiController::class);
    }

    /**
     * A real account. PageManager::setOwner() returns early and silently when the named user does
     * not exist, so a fixture owner has to be an account that actually exists -- naming a string
     * leaves the page unowned and quietly turns a "cannot steal an owned page" test into a
     * "can claim an unowned page" one.
     */
    private function createUser(): string
    {
        $wiki = $this->getWiki();
        $userManager = $wiki->services->get(UserManager::class);
        $name = 'Owner' . bin2hex(random_bytes(4));
        $userManager->create($name, strtolower($name) . '@example.com', 'a-solid-password');
        $this->cleanups[] = function () use ($userManager, $name): void {
            $existing = $userManager->getOneByName($name);
            if (!empty($existing)) {
                $userManager->delete($existing);
            }
        };

        return $name;
    }

    private function signIn(): string
    {
        $wiki = $this->getWiki();
        $userManager = $wiki->services->get(UserManager::class);
        $authenticationService = $wiki->services->get(AuthenticationService::class);
        $name = 'Claimer' . bin2hex(random_bytes(4));
        $userManager->create($name, strtolower($name) . '@example.com', 'a-solid-password');
        $authenticationService->login(self::requireUser($userManager->getOneByName($name)));

        $this->cleanups[] = function () use ($userManager, $authenticationService, $name): void {
            $authenticationService->logout();
            $existing = $userManager->getOneByName($name);
            if (!empty($existing)) {
                $userManager->delete($existing);
            }
        };

        return $name;
    }

    private function page(string $tag, string $owner): void
    {
        $wiki = $this->getWiki();
        $pageManager = $wiki->services->get(PageManager::class);
        $pageManager->save($tag, ['content' => 'a page for the claim tests'], '', true);
        if ($owner === '') {
            // save() makes whoever is signed in the owner, and setOwner('') returns early because
            // '' is not an account -- so an *unowned* page cannot be produced through either. The
            // column is cleared directly, which is the state a page imported or migrated without
            // an owner is actually in.
            $dbService = $wiki->services->get(\YesWiki\Kernel\Service\DbService::class);
            $dbService->query(
                'UPDATE ' . $dbService->prefixTable('pages') . 'SET owner = ? WHERE tag = ?',
                ['', $tag]
            );
            // the owner is memoised per request, so the direct UPDATE has to be reflected
            $pageManager->cacheOwner(['tag' => $tag, 'owner' => '']);
        } else {
            $pageManager->setOwner($tag, $owner);
        }

        $this->cleanups[] = function () use ($pageManager, $tag): void {
            $pageManager->deleteOrphaned($tag);
        };
    }

    /** @param array<string, string> $parameters */
    private function post(array $parameters = []): void
    {
        $wiki = $this->getWiki();
        $currentRequest = $wiki->services->get(CurrentRequest::class);
        $previous = $currentRequest->get();
        $currentRequest->replace(Request::create('/?api', 'POST', $parameters));
        $this->cleanups[] = static function () use ($currentRequest, $previous): void {
            $currentRequest->replace($previous);
        };
    }

    public function testAnUnownedPageCanBeClaimed(): void
    {
        $name = $this->signIn();
        $this->page(self::UNOWNED, '');
        $this->post();

        $response = $this->controller()->claimPage(self::UNOWNED);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(
            $name,
            $this->getWiki()->services->get(PageManager::class)->getOwner(self::UNOWNED),
            'the caller must actually become the owner'
        );
    }

    /** The case that matters: claiming must not take a page away from whoever owns it. */
    public function testAnOwnedPageCannotBeClaimed(): void
    {
        $owner = $this->createUser();
        $this->signIn();
        $this->page(self::OWNED, $owner);
        $this->post();

        $response = $this->controller()->claimPage(self::OWNED);

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame(
            $owner,
            $this->getWiki()->services->get(PageManager::class)->getOwner(self::OWNED),
            'the existing owner must be untouched'
        );
    }

    public function testClaimingAPageThatDoesNotExistIsNotFound(): void
    {
        $this->signIn();
        $this->post();

        $this->assertSame(404, $this->controller()->claimPage('NoSuchPageAtAll' . bin2hex(random_bytes(3)))->getStatusCode());
    }

    public function testCommentsAccessIsRefusedToSomeoneWhoIsNeitherOwnerNorAdmin(): void
    {
        $owner = $this->createUser();
        $this->signIn();
        $this->page(self::OWNED, $owner);
        $this->post(['access' => '+']);

        $response = $this->controller()->setCommentsAccess(self::OWNED, $this->getWiki()->services->get(CurrentRequest::class)->get());

        $this->assertSame(403, $response->getStatusCode());
    }

    public function testTheOwnerCanOpenAndCloseComments(): void
    {
        $name = $this->signIn();
        $this->page(self::UNOWNED, $name);
        $aclService = $this->getWiki()->services->get(AclService::class);

        $this->post(['access' => '+']);
        $opened = $this->controller()->setCommentsAccess(self::UNOWNED, $this->getWiki()->services->get(CurrentRequest::class)->get());
        $this->assertSame(200, $opened->getStatusCode());
        $this->assertSame('+', $aclService->load(self::UNOWNED, 'comment')['list'] ?? null);

        $this->post(['access' => 'closed']);
        $closed = $this->controller()->setCommentsAccess(self::UNOWNED, $this->getWiki()->services->get(CurrentRequest::class)->get());
        $this->assertSame(200, $closed->getStatusCode());
        $this->assertSame('comments-closed', $aclService->load(self::UNOWNED, 'comment')['list'] ?? null);
    }

    /**
     * A group name nobody has would be stored verbatim and match no one, so comments would read as
     * open in the admin screen and be closed in practice. Refused instead.
     */
    public function testAnUnknownGroupIsRefused(): void
    {
        $name = $this->signIn();
        $this->page(self::UNOWNED, $name);
        $this->post(['access' => '@no-such-group-exists']);

        $response = $this->controller()->setCommentsAccess(self::UNOWNED, $this->getWiki()->services->get(CurrentRequest::class)->get());

        $this->assertSame(400, $response->getStatusCode());
    }
}
