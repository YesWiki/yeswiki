<?php

namespace YesWiki\Test\Content;

use Symfony\Component\HttpFoundation\Request;
use YesWiki\Content\Api\TripleApiController;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\TripleStore;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The triples API only ever reads and deletes the triples of the caller (GHSA-9j7h-ccj2-jxv6): the sql it runs is a LIKE, so the rows it comes back with still have to be checked one by one. */
class TripleApiTest extends YesWikiTestCase
{
    private const PROPERTY = 'https://yeswiki.net/vocabulary/test-favorite';
    private const RESOURCE = 'TriplesApiTestPage';
    private const OWNER = 'TriplesApiOwner';
    private const OTHER = 'TriplesApiOther';
    private const WILDCARD = '%wner';
    private const GROUP_MEMBER = 'TriplesApiAdmin';

    private \YesWiki\Core\YesWikiRuntime $wiki;
    private TripleStore $tripleStore;
    private UserManager $userManager;
    private TripleApiController $controller;
    private ?Request $previousRequest = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wiki = $this->getWiki();
        $this->tripleStore = $this->wiki->services->get(TripleStore::class);
        $this->userManager = $this->wiki->services->get(UserManager::class);
        $this->previousRequest = $this->wiki->services->get(CurrentRequest::class)->get();

        $this->controller = new TripleApiController();
        $this->controller->setServices($this->wiki->services);

        foreach ([self::OWNER, self::OTHER, self::WILDCARD] as $name) {
            $this->user($name);
        }
        $this->tripleStore->create(
            self::RESOURCE,
            self::PROPERTY,
            (string)json_encode(['user' => self::OWNER, 'date' => '2026-01-01 00:00:00']),
            '',
            ''
        );
        $this->tripleStore->create(
            GROUP_PREFIX . ADMIN_GROUP . 'TriplesApi',
            WIKINI_VOC_PREFIX . WIKINI_VOC_ACLS,
            self::GROUP_MEMBER,
            '',
            ''
        );
    }

    protected function tearDown(): void
    {
        foreach ($this->tripleStore->getMatching(self::RESOURCE, self::PROPERTY, null, '=', '=', '=') as $triple) {
            $this->tripleStore->delete($triple['resource'], $triple['property'], $triple['value'], '', '');
        }
        $this->tripleStore->delete(GROUP_PREFIX . ADMIN_GROUP . 'TriplesApi', WIKINI_VOC_PREFIX . WIKINI_VOC_ACLS, null, '', '');
        foreach ([self::OWNER, self::OTHER, self::WILDCARD] as $name) {
            if ($user = $this->userManager->getOneByName($name)) {
                $this->userManager->delete($user);
            }
        }
        $this->wiki->services->get(AuthenticationService::class)->logout();
        if ($this->previousRequest !== null) {
            $this->wiki->services->get(CurrentRequest::class)->replace($this->previousRequest);
        }
        parent::tearDown();
    }

    private function user(string $name): void
    {
        if (!$this->userManager->getOneByName($name)) {
            $this->userManager->create($name, 'triplesapi' . md5($name) . '@example.com', 'a-long-enough-password');
        }
    }

    private function logIn(string $name): void
    {
        $user = $this->userManager->getOneByName($name);
        $this->assertNotNull($user, "the fixture account $name was not created");
        $this->wiki->services->get(AuthenticationService::class)->login($user);
    }

    /**
     * The parameters go in the query string as well as the body, so a test does not depend on which of the two the route happens to read.
     *
     * @param array<string, mixed> $body
     */
    private function request(array $body): void
    {
        $this->wiki->services->get(CurrentRequest::class)->replace(
            Request::create('/?' . http_build_query($body), 'POST', $body)
        );
    }

    /** @return list<array<string, mixed>> */
    private function ownerTriples(): array
    {
        return $this->tripleStore->getMatching(self::RESOURCE, self::PROPERTY, null, '=', '=', '=');
    }

    /** @return array<mixed> */
    private function decode(mixed $response): array
    {
        return json_decode((string)$response->getContent(), true) ?? [];
    }

    public function testAnEmptyFilterDoesNotWidenADeleteToEverybodysTriples(): void
    {
        $this->logIn(self::OTHER);
        $this->request(['property' => self::PROPERTY, 'filters' => ['x' => '']]);

        $deleted = $this->decode($this->controller->deleteTriples(self::RESOURCE));

        $this->assertSame([], $deleted);
        $this->assertCount(1, $this->ownerTriples());
    }

    public function testAGroupMembershipTripleCannotBeDeletedThroughTheTriplesApi(): void
    {
        $resource = GROUP_PREFIX . ADMIN_GROUP . 'TriplesApi';
        $this->logIn(self::OTHER);
        $this->request(['property' => WIKINI_VOC_PREFIX . WIKINI_VOC_ACLS, 'filters' => ['x' => '']]);

        $this->controller->deleteTriples($resource);

        $this->assertSame(
            self::GROUP_MEMBER,
            $this->tripleStore->getOne($resource, WIKINI_VOC_PREFIX . WIKINI_VOC_ACLS, '', '')
        );
    }

    /** `%wner` is a LIKE wildcard that matches `TriplesApiOwner`; only the exact check keeps it out. */
    public function testANameHoldingASqlWildcardDoesNotMatchAnotherUsersTriples(): void
    {
        $this->logIn(self::WILDCARD);
        $this->request(['property' => self::PROPERTY]);

        $read = $this->decode($this->controller->getTriplesByResource(self::RESOURCE));
        $deleted = $this->decode($this->controller->deleteTriples(self::RESOURCE));

        $this->assertSame([], $read);
        $this->assertSame([], $deleted);
        $this->assertCount(1, $this->ownerTriples());
    }

    public function testAUserStillReadsAndDeletesTheirOwnTriples(): void
    {
        $this->logIn(self::OWNER);
        $this->request(['property' => self::PROPERTY]);

        $read = $this->decode($this->controller->getTriplesByResource(self::RESOURCE));
        $deleted = $this->decode($this->controller->deleteTriples(self::RESOURCE));

        $this->assertCount(1, $read);
        $this->assertCount(1, $deleted);
        $this->assertSame([], $this->ownerTriples());
    }
}
