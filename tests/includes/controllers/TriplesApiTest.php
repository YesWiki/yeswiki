<?php

namespace YesWiki\Test\Core\Controller;

use Symfony\Component\HttpFoundation\Request;
use YesWiki\Core\Controller\ApiController;
use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Core\Service\UserManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * The triples API only ever reads and deletes the triples of the caller.
 */
class TriplesApiTest extends YesWikiTestCase
{
    private const PROPERTY = 'https://yeswiki.net/vocabulary/test-favorite';
    private const RESOURCE = 'TriplesApiTestPage';
    private const OWNER = 'TriplesApiOwner';
    private const OTHER = 'TriplesApiOther';
    private const WILDCARD = '%wner';
    private const GROUP_MEMBER = 'TriplesApiAdmin';

    private $wiki;
    private $tripleStore;
    private $userManager;
    private $controller;

    protected function setUp(): void
    {
        $this->wiki = $this->getWiki();
        $GLOBALS['wiki'] = $this->wiki;
        $this->tripleStore = $this->wiki->services->get(TripleStore::class);
        $this->userManager = $this->wiki->services->get(UserManager::class);
        $this->controller = new ApiController();
        $this->controller->setWiki($this->wiki);

        foreach ([self::OWNER, self::OTHER, self::WILDCARD] as $name) {
            $this->user($name);
        }
        $this->tripleStore->create(self::RESOURCE, self::PROPERTY, json_encode(['user' => self::OWNER, 'date' => '2026-01-01 00:00:00']), '', '');
        $this->tripleStore->create(GROUP_PREFIX . ADMIN_GROUP . 'TriplesApi', WIKINI_VOC_PREFIX . WIKINI_VOC_ACLS, self::GROUP_MEMBER, '', '');
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
        $this->wiki->services->get(AuthController::class)->logout();
    }

    private function user(string $name): void
    {
        if (!$this->userManager->getOneByName($name)) {
            $this->userManager->create([
                'name' => $name,
                'email' => 'triplesapi' . md5($name) . '@example.com',
                'password' => 'a-long-enough-password',
            ]);
        }
    }

    private function logIn(string $name): void
    {
        $this->wiki->services->get(AuthController::class)->login($this->userManager->getOneByName($name));
    }

    /**
     * The parameters go in the query string as well as the body, so a test does not depend on
     * which of the two the route happens to read.
     */
    private function request(array $body): void
    {
        $this->wiki->request = Request::create('/?' . http_build_query($body), 'POST', $body);
    }

    private function ownerTriples(): array
    {
        return $this->tripleStore->getMatching(self::RESOURCE, self::PROPERTY, null, '=', '=', '=');
    }

    private function decode($response): array
    {
        return json_decode($response->getContent(), true) ?? [];
    }

    public function testAnEmptyFilterDoesNotWidenADeleteToEverybodysTriples()
    {
        $this->logIn(self::OTHER);
        $this->request(['property' => self::PROPERTY, 'filters' => ['x' => '']]);

        $deleted = $this->decode($this->controller->deleteTriples(self::RESOURCE));

        $this->assertSame([], $deleted);
        $this->assertCount(1, $this->ownerTriples());
    }

    public function testAGroupMembershipTripleCannotBeDeletedThroughTheTriplesApi()
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

    public function testANameHoldingASqlWildcardDoesNotMatchAnotherUsersTriples()
    {
        $this->logIn(self::WILDCARD);
        $this->request(['property' => self::PROPERTY]);

        $read = $this->decode($this->controller->getTriplesByResource(self::RESOURCE));
        $deleted = $this->decode($this->controller->deleteTriples(self::RESOURCE));

        $this->assertSame([], $read);
        $this->assertSame([], $deleted);
        $this->assertCount(1, $this->ownerTriples());
    }

    public function testAUserStillReadsAndDeletesTheirOwnTriples()
    {
        $this->logIn(self::OWNER);
        $this->request(['property' => self::PROPERTY]);

        $read = $this->decode($this->controller->getTriplesByResource(self::RESOURCE));
        $deleted = $this->decode($this->controller->deleteTriples(self::RESOURCE));

        $this->assertCount(1, $read);
        $this->assertCount(1, $deleted);
        $this->assertSame([], $this->ownerTriples());
    }

    public function testPostedParametersAreReadFromTheBody()
    {
        $this->logIn(self::OWNER);
        $this->wiki->request = Request::create('/', 'POST', ['property' => self::PROPERTY]);

        $response = $this->controller->setTriple('TriplesApiTestOtherPage');

        $this->assertSame(200, $response->getStatusCode(), 'the property was not read from the posted body');
        $this->tripleStore->delete('TriplesApiTestOtherPage', self::PROPERTY, null, '', '');
    }
}
