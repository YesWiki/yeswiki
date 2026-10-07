<?php

namespace YesWiki\Test\Identity;

use AltchaOrg\Altcha\Algorithm\Pbkdf2;
use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\Challenge;
use AltchaOrg\Altcha\Payload;
use AltchaOrg\Altcha\SolveChallengeOptions;
use Symfony\Component\HttpFoundation\Request;
use YesWiki\Files\Service\Storage;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\BotGuard;
use YesWiki\Identity\Service\GroupManager;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\RequestScope;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** BotGuard's token, honeypot, ALTCHA, counters and exemption. */
class BotGuardTest extends YesWikiTestCase
{
    private const MEMBER = 'BotGuardTestMember';

    private BotGuard $guard;
    private DbService $db;
    private string $root;
    private int $now;
    private int $start;
    private int $maxTripleId;

    protected function setUp(): void
    {
        parent::setUp();
        $wiki = $this->getWiki();
        $this->db = $wiki->services->get(DbService::class);
        $this->guard = $wiki->services->get(BotGuard::class);
        $this->root = sys_get_temp_dir() . '/botguard-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/private/keys', 0777, true);
        $this->start = $this->now = time();
        $this->guard->useForTests(Storage::rootedAt($this->root), fn () => $this->now, false);
        $this->maxTripleId = (int)($this->db->loadSingle('SELECT MAX(id) AS id FROM ' . $this->db->prefixTable('triples'))['id'] ?? 0);
        $wiki->services->get(AuthenticationService::class)->logout();
    }

    protected function tearDown(): void
    {
        $services = $this->getWiki()->services;
        $services->get(AuthenticationService::class)->logout();
        $userManager = $services->get(UserManager::class);
        if ($user = $userManager->getOneByName(self::MEMBER)) {
            $userManager->delete($user);
        }
        $this->guard->useForTests($services->get(Storage::class));
        $this->db->query('DELETE FROM ' . $this->db->prefixTable('triples') . ' WHERE id > ? AND resource LIKE ?', [$this->maxTripleId, BotGuard::RESOURCE_PREFIX . '%']);
        (new \Symfony\Component\Filesystem\Filesystem())->remove($this->root);
        parent::tearDown();
    }

    /** @return array{token: string, honeypot: string, honeypotKey: string, altcha: string} */
    private function names(?int $at = null): array
    {
        return $this->guard->namesFor(date('Y-m-d', $at ?? $this->now));
    }

    private function renderedToken(): string
    {
        preg_match('/name="' . $this->names()['token'] . '" value="([^"]+)"/', $this->guard->fields(), $m);

        return $m[1];
    }

    /** @param array<string, mixed> $post */
    private function submit(array $post): ?string
    {
        return $this->guard->check(new Request([], $post));
    }

    /** @return array<string, string> */
    private function validPost(?string $token = null): array
    {
        return [$this->names()['token'] => $token ?? $this->renderedToken(), $this->names()['honeypot'] => '', 'bf_titre' => 'x'];
    }

    private function logIn(string $name): void
    {
        $services = $this->getWiki()->services;
        $userManager = $services->get(UserManager::class);
        if (!$userManager->getOneByName($name)) {
            $userManager->create($name, strtolower($name) . '@example.com', 'Un mot de passe 1!');
        }
        $services->get(AuthenticationService::class)->login(self::requireUser($userManager->getOneByName($name)));
    }

    public function testTheKeyIsCreatedOnceInProtectedStorage(): void
    {
        $this->guard->fields();
        $key = file_get_contents($this->root . '/' . BotGuard::KEY_FILE);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', (string)$key);

        $this->guard->useForTests(Storage::rootedAt($this->root), fn () => $this->now, false);
        $this->guard->fields();
        $this->assertSame($key, file_get_contents($this->root . '/' . BotGuard::KEY_FILE));
    }

    public function testEachWikiOfAFarmHasItsOwnNames(): void
    {
        $here = $this->names();
        $elsewhere = sys_get_temp_dir() . '/botguard-other-' . bin2hex(random_bytes(6));
        mkdir($elsewhere . '/private/keys', 0777, true);
        try {
            $this->guard->useForTests(Storage::rootedAt($elsewhere), fn () => $this->now, false);
            $this->assertNotSame($here['token'], $this->names()['token']);
        } finally {
            (new \Symfony\Component\Filesystem\Filesystem())->remove($elsewhere);
        }
    }

    public function testAnUnwritableKeyFolderRefusesEverySubmission(): void
    {
        if (function_exists('posix_getuid') && posix_getuid() === 0) {
            $this->markTestSkipped('root writes anywhere');
        }
        chmod($this->root . '/private/keys', 0555);
        try {
            $this->guard->useForTests(Storage::rootedAt($this->root), fn () => $this->now, false);

            $this->assertSame('', $this->guard->fields());
            $this->assertSame(BotGuard::REFUSED_NO_SECRET, $this->submit(['anything' => 'x']));
        } finally {
            chmod($this->root . '/private/keys', 0777);
        }
    }

    public function testAValidSubmissionPassesOnce(): void
    {
        $post = $this->validPost();
        $this->now += 10;

        $this->assertNull($this->submit($post));
        $this->assertSame(BotGuard::REFUSED_TOKEN_REUSED, $this->submit($post));
    }

    public function testASubmissionWithoutTokenIsRefused(): void
    {
        $this->assertSame(BotGuard::REFUSED_TOKEN_MISSING, $this->submit(['bf_titre' => 'x']));
    }

    public function testATamperedTokenIsRefused(): void
    {
        $token = $this->renderedToken();
        $this->now += 10;
        [$issuedAt, $id, $signature] = explode('.', $token);

        $this->assertSame(BotGuard::REFUSED_TOKEN_INVALID, $this->submit($this->validPost(((int)$issuedAt - 1000) . ".$id.$signature")));
        $this->assertSame(BotGuard::REFUSED_TOKEN_INVALID, $this->submit($this->validPost('nonsense')));
    }

    public function testASubmissionUnderThreeSecondsIsRefused(): void
    {
        $post = $this->validPost();
        $this->now += 2;

        $this->assertSame(BotGuard::REFUSED_TOO_FAST, $this->submit($post));
    }

    public function testATokenOlderThanADayIsRefused(): void
    {
        $post = $this->validPost();
        $this->now += BotGuard::MAX_AGE + 1;
        $yesterday = $this->names($this->now - 86400);
        $post = [$yesterday['token'] => $post[$this->names($this->start)['token']]];

        $this->assertSame(BotGuard::REFUSED_TOKEN_EXPIRED, $this->submit($post));
    }

    public function testYesterdaysFieldNamesAreAcceptedAndOlderOnesAreNot(): void
    {
        $this->now = (int)mktime(23, 0, 0, 3, 10, 2027);
        $post = $this->validPost();
        $this->now = (int)mktime(10, 0, 0, 3, 11, 2027);

        $this->assertNull($this->submit($post));

        $this->now = (int)mktime(23, 0, 0, 3, 11, 2027);
        $old = $this->validPost();
        $this->now = (int)mktime(0, 30, 0, 3, 13, 2027);

        $this->assertSame(BotGuard::REFUSED_TOKEN_MISSING, $this->submit($old));
    }

    public function testTheNamesChangeEveryDay(): void
    {
        $this->assertNotSame($this->names($this->start)['token'], $this->names($this->start + 86400)['token']);
    }

    public function testAFilledHoneypotIsRefused(): void
    {
        $post = $this->validPost();
        $post[$this->names()['honeypot']] = 'ABC123';
        $this->now += 10;

        $this->assertSame(BotGuard::REFUSED_HONEYPOT, $this->submit($post));
    }

    public function testTheHoneypotIsHiddenFromKeyboardScreenReadersAndAutofill(): void
    {
        $fields = $this->guard->fields();

        $this->assertStringContainsString('class="yw-bot-guard" aria-hidden="true"', $fields);
        $this->assertStringContainsString('tabindex="-1"', $fields);
        $this->assertStringContainsString('autocomplete="off"', $fields);
        $this->assertStringNotContainsString('type="hidden" id="' . $this->names()['honeypot'], $fields);
        $this->assertDoesNotMatchRegularExpression('/(tel|email|organi[sz]ation|url|name)/i', $this->names()['honeypot']);
    }

    public function testOnlyTheFirstOfTwoClaimsOnOneTokenWins(): void
    {
        $post = $this->validPost();
        [, $id] = explode('.', $post[$this->names()['token']]);
        $this->db->query(
            'INSERT INTO ' . $this->db->prefixTable('triples') . ' (resource, property, value) VALUES (?, ?, ?)',
            [BotGuard::RESOURCE_PREFIX . 'token:' . $id, BotGuard::USED_TOKEN_PROPERTY, '2099-01-01 00:00:00|elsewhere']
        );
        $this->now += 10;

        $this->assertSame(BotGuard::REFUSED_TOKEN_REUSED, $this->submit($post));
    }

    public function testAUsedTokenIsOneRowInTriplesKeyedByItsResource(): void
    {
        $post = $this->validPost();
        [, $id] = explode('.', $post[$this->names()['token']]);
        $this->now += 10;
        $this->submit($post);
        $this->submit($post);

        $rows = $this->db->loadAll(
            'SELECT value FROM ' . $this->db->prefixTable('triples') . ' WHERE resource = ? AND property = ?',
            [BotGuard::RESOURCE_PREFIX . 'token:' . $id, BotGuard::USED_TOKEN_PROPERTY]
        );
        $this->assertCount(1, $rows, 'a replay is refused before it writes a row of its own');
    }

    public function testRefusalsAreCountedPerReason(): void
    {
        $before = $this->guard->refusedLastDays(7);
        $this->submit(['bf_titre' => 'x']);
        $this->submit(['bf_titre' => 'y']);
        $post = $this->validPost();
        $this->now++;
        $this->submit($post);

        $totals = $this->guard->refusedLastDays(7);

        $this->assertSame(2, $totals[BotGuard::REFUSED_TOKEN_MISSING] - ($before[BotGuard::REFUSED_TOKEN_MISSING] ?? 0));
        $this->assertSame(1, $totals[BotGuard::REFUSED_TOO_FAST] - ($before[BotGuard::REFUSED_TOO_FAST] ?? 0));
    }

    public function testRefusalsAreListedPerDayNewestFirst(): void
    {
        $this->now += 100 * 86400;
        $this->submit(['bf_titre' => 'x']);
        $this->now += 2 * 86400;
        $this->submit(['bf_titre' => 'x']);
        $post = $this->validPost();
        $this->now++;
        $this->submit($post);
        $today = date('Y-m-d', $this->now);

        $this->assertSame([
            $today => [BotGuard::REFUSED_TOKEN_MISSING => 1, BotGuard::REFUSED_TOO_FAST => 1],
            date('Y-m-d', $this->now - 86400) => [],
            date('Y-m-d', $this->now - 2 * 86400) => [BotGuard::REFUSED_TOKEN_MISSING => 1],
        ], $this->guard->refusedPerDay(3));
        $this->assertSame([$today], array_keys($this->guard->refusedPerDay(1)));
        $this->assertCount(BotGuard::COUNTERS_KEPT_DAYS, $this->guard->refusedPerDay(1000));
    }

    public function testPurgeDropsExpiredTokensAndOldCounters(): void
    {
        $post = $this->validPost();
        $this->now += 10;
        $this->submit($post);
        $this->submit(['bf_titre' => 'x']);

        $this->now += BotGuard::COUNTERS_KEPT_DAYS * 86400 + 86400;
        $this->guard->purge();

        $left = $this->db->loadAll(
            'SELECT * FROM ' . $this->db->prefixTable('triples') . ' WHERE id > ? AND resource LIKE ?',
            [$this->maxTripleId, BotGuard::RESOURCE_PREFIX . '%']
        );
        $this->assertSame([], $left);
    }

    public function testPurgeKeepsTokensThatStillMatter(): void
    {
        $post = $this->validPost();
        $this->now += 10;
        $this->submit($post);
        $this->guard->purge();

        $this->assertSame(BotGuard::REFUSED_TOKEN_REUSED, $this->submit($post));
    }

    public function testPlaceFieldsKeepsFieldsTheTemplatePlacedAndInsertsThemOtherwise(): void
    {
        $fields = $this->guard->fields();
        $placed = '<form id="a">' . $fields . '<button></button></form>';

        $this->assertSame($placed, $this->guard->placeFields($placed, $fields, 'a'));
        $this->assertSame('<form id="a"><button></button>' . $fields . '</form>', $this->guard->placeFields('<form id="a"><button></button></form>', $fields, 'a'));
    }

    public function testInsertIntoAddsTheFieldsBeforeEveryForm(): void
    {
        $html = $this->guard->insertInto('<form id="a"></form><p></p><FORM id="b"></FORM>');

        $this->assertSame(2, substr_count($html, 'name="' . $this->names()['token'] . '"'));
    }

    public function testInsertIntoCanTargetOneForm(): void
    {
        $html = $this->guard->insertInto('<form id="search"></form><form class="x" id="ACEditor"><textarea></textarea></form><form id="login"></form>', 'ACEditor');

        $this->assertSame(1, substr_count($html, 'name="' . $this->names()['token'] . '"'));
        $this->assertMatchesRegularExpression('/<form class="x" id="ACEditor"><textarea><\/textarea>.*<div class="yw-bot-guard-fields">/s', $html);
        $this->assertSame('<form id="a"></form>', $this->guard->insertInto('<form id="a"></form>', 'missing'));
    }

    public function testWithoutFieldsDropsEveryGuardFieldAndNothingElse(): void
    {
        $post = $this->validPost() + [$this->names()['altcha'] => 'solution', 'bf_texte' => 'kept'];

        $this->assertSame(['bf_titre' => 'x', 'bf_texte' => 'kept'], $this->guard->withoutFields($post));
    }

    private function solvedAltcha(string $fields): string
    {
        if (preg_match('/challenge="([^"]+)"/', $fields, $m) !== 1) {
            $this->fail('the fields carry no ALTCHA challenge');
        }
        $challenge = Challenge::fromArray(json_decode(html_entity_decode($m[1], ENT_QUOTES), true));
        $solution = (new Altcha('unused'))->solveChallenge(new SolveChallengeOptions(algorithm: new Pbkdf2(), challenge: $challenge));
        $this->assertNotNull($solution);

        return (new Payload($challenge, $solution))->toBase64();
    }

    /** @return array<string, string> */
    private function altchaPost(): array
    {
        $this->guard->useForTests(null, fn () => $this->now, true);
        $fields = $this->guard->fields();
        preg_match('/name="' . $this->names()['token'] . '" value="([^"]+)"/', $fields, $m);

        return [
            $this->names()['token'] => $m[1],
            $this->names()['altcha'] => $this->solvedAltcha($fields),
        ];
    }

    public function testASolvedAltchaPasses(): void
    {
        $post = $this->altchaPost();
        $this->now += 10;

        $this->assertNull($this->submit($post));
    }

    public function testAMissingOrWrongAltchaIsRefused(): void
    {
        $post = $this->altchaPost();
        $this->now += 10;
        $withoutSolution = $post;
        unset($withoutSolution[$this->names()['altcha']]);

        $this->assertSame(BotGuard::REFUSED_ALTCHA, $this->submit($withoutSolution));

        $broken = json_decode((string)base64_decode($post[$this->names()['altcha']]), true);
        $broken['solution']['derivedKey'] = str_repeat('0', 64);
        $post[$this->names()['altcha']] = base64_encode((string)json_encode($broken));
        $this->assertSame(BotGuard::REFUSED_ALTCHA, $this->submit($post));
    }

    public function testASolvedAltchaCannotBeMovedToAnotherToken(): void
    {
        $first = $this->altchaPost();
        $second = $this->altchaPost();
        $this->now += 10;
        $second[$this->names()['altcha']] = $first[$this->names()['altcha']];

        $this->assertSame(BotGuard::REFUSED_ALTCHA, $this->submit($second));
    }

    public function testTheAltchaWidgetIsOnlyRenderedWhenEnabled(): void
    {
        $this->assertStringNotContainsString('<altcha-widget', $this->guard->fields());

        $this->guard->useForTests(null, fn () => $this->now, true);
        $this->assertStringContainsString('<altcha-widget name="' . $this->names()['altcha'] . '"', $this->guard->fields());
    }

    public function testASecondCheckOfTheSameRequestGivesTheSameAnswer(): void
    {
        $post = $this->validPost();
        $this->now += 10;
        $request = new Request([], $post);

        $this->assertNull($this->guard->check($request));
        $this->assertNull($this->guard->check($request));
        $this->assertSame(BotGuard::REFUSED_TOKEN_REUSED, $this->submit($post));
    }

    public function testAltchaIsOnlyAskedWhereBrowsersCanComputeIt(): void
    {
        $this->assertTrue($this->guard->isSecureContext('https://wiki.example.org/?'));
        $this->assertTrue($this->guard->isSecureContext('http://localhost/yeswiki/?'));
        $this->assertTrue($this->guard->isSecureContext('http://127.0.0.1:8000/?'));
        $this->assertFalse($this->guard->isSecureContext('http://wiki.example.org/?'));
        $this->assertFalse($this->guard->isSecureContext('http://yeswiki-web/?'));
    }

    public function testALoggedInMemberGetsTheFullGuard(): void
    {
        $this->guard->useForTests(null, fn () => $this->now, true);
        $this->logIn(self::MEMBER);
        $fields = $this->guard->fields();

        $this->assertTrue($this->guard->applies());
        $this->assertStringContainsString('name="' . $this->names()['token'] . '"', $fields);
        $this->assertStringContainsString('name="' . $this->names()['honeypot'] . '"', $fields);
        $this->assertStringContainsString('<altcha-widget name="' . $this->names()['altcha'] . '"', $fields);
        $this->assertSame(BotGuard::REFUSED_TOKEN_MISSING, $this->submit([]));
    }

    public function testALoggedInMemberStillNeedsATokenWhenAltchaIsOff(): void
    {
        $this->logIn(self::MEMBER);
        $post = $this->validPost();

        $this->assertSame(BotGuard::REFUSED_TOKEN_MISSING, $this->submit([]));
        $this->assertSame(BotGuard::REFUSED_TOO_FAST, $this->submit($post));
        $this->now += 10;
        $this->assertNull($this->submit($post));
    }

    public function testAnAdminGoesThrough(): void
    {
        $services = $this->getWiki()->services;
        $admins = $services->get(GroupManager::class)->getMembers('admins');
        $services->get(AuthenticationService::class)->login(self::requireUser($services->get(UserManager::class)->getOneByName($admins[0])));

        $this->assertFalse($this->guard->applies());
        $this->assertSame('', $this->guard->fields());
        $this->assertNull($this->submit([]));
    }

    public function testTheExemptionDoesNotOutliveTheRequest(): void
    {
        $services = $this->getWiki()->services;
        $admins = $services->get(GroupManager::class)->getMembers('admins');
        $services->get(AuthenticationService::class)->login(self::requireUser($services->get(UserManager::class)->getOneByName($admins[0])));
        $this->assertNull($this->submit([]));

        $services->get(AuthenticationService::class)->logout();
        $services->get(RequestScope::class)->startNewRequest();

        $this->assertSame(BotGuard::REFUSED_TOKEN_MISSING, $this->submit([]));
    }
}
