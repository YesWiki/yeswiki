<?php

namespace YesWiki\Test\Core\Service;

use AltchaOrg\Altcha\Algorithm\Pbkdf2;
use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\Challenge;
use AltchaOrg\Altcha\Payload;
use AltchaOrg\Altcha\SolveChallengeOptions;
use Symfony\Component\HttpFoundation\Request;
use YesWiki\Core\Service\BotGuard;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\GroupManager;
use YesWiki\Core\Service\UserManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

class BotGuardTest extends YesWikiTestCase
{
    private BotGuard $guard;
    private DbService $db;
    private string $configFile;
    private int $now;
    private int $start;
    private int $maxTripleId;

    protected function setUp(): void
    {
        $wiki = $this->getWiki();
        $this->db = $wiki->services->get(DbService::class);
        $this->guard = $wiki->services->get(BotGuard::class);
        $this->configFile = tempnam(sys_get_temp_dir(), 'botguard');
        file_put_contents($this->configFile, "<?php\n\n\$wakkaConfig = ['wakka_name' => 'test'];\n");
        $this->start = $this->now = time();
        $this->guard->useConfigFileAndClock($this->configFile, fn () => $this->now);
        $this->guard->useAltcha(false);
        $this->maxTripleId = (int)($this->db->loadSingle('SELECT MAX(id) AS id FROM' . $this->db->prefixTable('triples'))['id'] ?? 0);
    }

    protected function tearDown(): void
    {
        unset($_SESSION['user']);
        $userManager = $this->getWiki()->services->get(UserManager::class);
        foreach (['BotGuardTestMember', 'BotGuardTestOther'] as $name) {
            if ($user = $userManager->getOneByName($name)) {
                $userManager->delete($user);
            }
        }
        $this->guard->useAltcha(true);
        $this->db->query('DELETE FROM' . $this->db->prefixTable('triples') . 'WHERE id > ' . $this->maxTripleId);
        @unlink($this->configFile);
    }

    private function names(?int $at = null): array
    {
        return $this->guard->namesFor(date('Y-m-d', $at ?? $this->now));
    }

    private function renderedToken(): string
    {
        preg_match('/name="' . $this->names()['token'] . '" value="([^"]+)"/', $this->guard->fields(), $m);

        return $m[1];
    }

    private function submit(array $post): ?string
    {
        return $this->guard->check(new Request([], $post));
    }

    private function validPost(?string $token = null): array
    {
        return [$this->names()['token'] => $token ?? $this->renderedToken(), $this->names()['honeypot'] => '', 'bf_titre' => 'x'];
    }

    public function testTheSecretIsGeneratedOnceAndWrittenToTheConfig()
    {
        $this->guard->fields();
        $content = file_get_contents($this->configFile);

        $this->assertMatchesRegularExpression("/'bot_guard_secret' => '[0-9a-f]{64}'/", $content);

        $this->guard->useConfigFileAndClock($this->configFile, fn () => $this->now);
        $this->guard->fields();
        $this->assertSame($content, file_get_contents($this->configFile));
    }

    public function testAnUnwritableConfigRefusesEverySubmission()
    {
        chmod($this->configFile, 0444);
        $this->guard->useConfigFileAndClock($this->configFile, fn () => $this->now);

        $this->assertSame('', $this->guard->fields());
        $this->assertSame(BotGuard::REFUSED_NO_SECRET, $this->submit(['anything' => 'x']));
    }

    public function testAValidSubmissionPassesOnce()
    {
        $post = $this->validPost();
        $this->now += 10;

        $this->assertNull($this->submit($post));
        $this->assertSame(BotGuard::REFUSED_TOKEN_REUSED, $this->submit($post));
    }

    public function testASubmissionWithoutTokenIsRefused()
    {
        $this->assertSame(BotGuard::REFUSED_TOKEN_MISSING, $this->submit(['bf_titre' => 'x']));
    }

    public function testATamperedTokenIsRefused()
    {
        $token = $this->renderedToken();
        $this->now += 10;
        [$issuedAt, $id, $signature] = explode('.', $token);

        $this->assertSame(BotGuard::REFUSED_TOKEN_INVALID, $this->submit($this->validPost(($issuedAt - 1000) . ".$id.$signature")));
        $this->assertSame(BotGuard::REFUSED_TOKEN_INVALID, $this->submit($this->validPost('nonsense')));
    }

    public function testASubmissionUnderThreeSecondsIsRefused()
    {
        $post = $this->validPost();
        $this->now += 2;

        $this->assertSame(BotGuard::REFUSED_TOO_FAST, $this->submit($post));
    }

    public function testATokenOlderThanADayIsRefused()
    {
        $post = $this->validPost();
        $this->now += BotGuard::MAX_AGE + 1;
        $yesterday = $this->names($this->now - 86400);
        $post = [$yesterday['token'] => $post[$this->names($this->start)['token']]];

        $this->assertSame(BotGuard::REFUSED_TOKEN_EXPIRED, $this->submit($post));
    }

    public function testYesterdaysFieldNamesAreAcceptedAndOlderOnesAreNot()
    {
        $this->now = mktime(23, 0, 0, 3, 10, 2027);
        $post = $this->validPost();
        $this->now = mktime(10, 0, 0, 3, 11, 2027);

        $this->assertNull($this->submit($post));

        $this->now = mktime(23, 0, 0, 3, 11, 2027);
        $old = $this->validPost();
        $this->now = mktime(0, 30, 0, 3, 13, 2027);

        $this->assertSame(BotGuard::REFUSED_TOKEN_MISSING, $this->submit($old));
    }

    public function testTheNamesChangeEveryDay()
    {
        $this->assertNotSame($this->names($this->start)['token'], $this->names($this->start + 86400)['token']);
    }

    public function testAFilledHoneypotIsRefused()
    {
        $post = $this->validPost();
        $post[$this->names()['honeypot']] = 'ABC123';
        $this->now += 10;

        $this->assertSame(BotGuard::REFUSED_HONEYPOT, $this->submit($post));
    }

    public function testTheHoneypotIsHiddenFromKeyboardScreenReadersAndAutofill()
    {
        $fields = $this->guard->fields();

        $this->assertStringContainsString('aria-hidden="true"', $fields);
        $this->assertStringContainsString('tabindex="-1"', $fields);
        $this->assertStringContainsString('autocomplete="off"', $fields);
        $this->assertStringNotContainsString('type="hidden" id="' . $this->names()['honeypot'], $fields);
        $this->assertDoesNotMatchRegularExpression('/(tel|email|organi[sz]ation|url|name)/i', $this->names()['honeypot']);
    }

    public function testOnlyTheFirstOfTwoClaimsOnOneTokenWins()
    {
        $post = $this->validPost();
        [, $id] = explode('.', $post[$this->names()['token']]);
        $this->db->query(
            'INSERT INTO' . $this->db->prefixTable('triples') . "(resource, property, value) VALUES ('botGuard:token:$id', '" . BotGuard::USED_TOKEN_PROPERTY . "', '2099-01-01 00:00:00')"
        );
        $this->now += 10;

        $this->assertSame(BotGuard::REFUSED_TOKEN_REUSED, $this->submit($post));
    }

    public function testRefusalsAreCountedPerReason()
    {
        $before = $this->guard->refusedLastDays(7);
        $this->submit(['bf_titre' => 'x']);
        $this->submit(['bf_titre' => 'x']);
        $post = $this->validPost();
        $this->now++;
        $this->submit($post);

        $totals = $this->guard->refusedLastDays(7);

        $this->assertSame(2, $totals[BotGuard::REFUSED_TOKEN_MISSING] - ($before[BotGuard::REFUSED_TOKEN_MISSING] ?? 0));
        $this->assertSame(1, $totals[BotGuard::REFUSED_TOO_FAST] - ($before[BotGuard::REFUSED_TOO_FAST] ?? 0));
    }

    public function testRefusalsAreListedPerDayNewestFirst()
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

    public function testPurgeDropsExpiredTokensAndOldCounters()
    {
        $post = $this->validPost();
        $this->now += 10;
        $this->submit($post);
        $this->submit(['bf_titre' => 'x']);

        $this->now += BotGuard::COUNTERS_KEPT_DAYS * 86400 + 86400;
        $this->guard->purge();

        $left = $this->db->loadAll('SELECT * FROM' . $this->db->prefixTable('triples') . 'WHERE id > ' . $this->maxTripleId);
        $this->assertSame([], $left);
    }

    public function testPurgeKeepsTokensThatStillMatter()
    {
        $post = $this->validPost();
        $this->now += 10;
        $this->submit($post);
        $this->guard->purge();

        $this->assertSame(BotGuard::REFUSED_TOKEN_REUSED, $this->submit($post));
    }

    public function testPlaceFieldsKeepsFieldsTheTemplatePlacedAndInsertsThemOtherwise()
    {
        $fields = $this->guard->fields();
        $placed = '<form id="a">' . $fields . '<button></button></form>';

        $this->assertSame($placed, $this->guard->placeFields($placed, $fields, 'a'));
        $this->assertSame('<form id="a"><button></button>' . $fields . '</form>', $this->guard->placeFields('<form id="a"><button></button></form>', $fields, 'a'));
    }

    public function testInsertIntoAddsTheFieldsBeforeEveryForm()
    {
        $html = $this->guard->insertInto('<form id="a"></form><p></p><FORM id="b"></FORM>');

        $this->assertSame(2, substr_count($html, 'name="' . $this->names()['token'] . '"'));
    }

    private function solvedAltcha(string $fields): string
    {
        preg_match('/challenge="([^"]+)"/', $fields, $m);
        $challenge = Challenge::fromArray(json_decode(html_entity_decode($m[1], ENT_QUOTES), true));
        $solution = (new Altcha('unused'))->solveChallenge(new SolveChallengeOptions(algorithm: new Pbkdf2(), challenge: $challenge));

        return (new Payload($challenge, $solution))->toBase64();
    }

    private function altchaPost(): array
    {
        $this->guard->useAltcha(true);
        $fields = $this->guard->fields();
        preg_match('/name="' . $this->names()['token'] . '" value="([^"]+)"/', $fields, $m);

        return [
            $this->names()['token'] => $m[1],
            $this->names()['altcha'] => $this->solvedAltcha($fields),
        ];
    }

    public function testASolvedAltchaPasses()
    {
        $post = $this->altchaPost();
        $this->now += 10;

        $this->assertNull($this->submit($post));
    }

    public function testAMissingOrWrongAltchaIsRefused()
    {
        $post = $this->altchaPost();
        $this->now += 10;
        $withoutSolution = $post;
        unset($withoutSolution[$this->names()['altcha']]);

        $this->assertSame(BotGuard::REFUSED_ALTCHA, $this->submit($withoutSolution));

        $broken = json_decode(base64_decode($post[$this->names()['altcha']]), true);
        $broken['solution']['derivedKey'] = str_repeat('0', 64);
        $post[$this->names()['altcha']] = base64_encode(json_encode($broken));
        $this->assertSame(BotGuard::REFUSED_ALTCHA, $this->submit($post));
    }

    public function testASolvedAltchaCannotBeMovedToAnotherToken()
    {
        $first = $this->altchaPost();
        $second = $this->altchaPost();
        $this->now += 10;
        $second[$this->names()['altcha']] = $first[$this->names()['altcha']];

        $this->assertSame(BotGuard::REFUSED_ALTCHA, $this->submit($second));
    }

    public function testTheAltchaWidgetIsOnlyRenderedWhenEnabled()
    {
        $this->assertStringNotContainsString('<altcha-widget', $this->guard->fields());

        $this->guard->useAltcha(true);
        $this->assertStringContainsString('<altcha-widget name="' . $this->names()['altcha'] . '"', $this->guard->fields());
    }

    public function testASecondCheckOfTheSameRequestGivesTheSameAnswer()
    {
        $post = $this->validPost();
        $this->now += 10;
        $request = new Request([], $post);

        $this->assertNull($this->guard->check($request));
        $this->assertNull($this->guard->check($request));
        $this->assertSame(BotGuard::REFUSED_TOKEN_REUSED, $this->submit($post));
    }

    public function testInsertIntoCanTargetOneForm()
    {
        $html = $this->guard->insertInto('<form id="search"></form><form class="x" id="ACEditor"><textarea></textarea></form><form id="login"></form>', 'ACEditor');

        $this->assertSame(1, substr_count($html, 'name="' . $this->names()['token'] . '"'));
        $this->assertMatchesRegularExpression('/<form class="x" id="ACEditor"><textarea><\/textarea><div class="yw-bot-guard-fields">/', $html);
        $this->assertSame('<form id="a"></form>', $this->guard->insertInto('<form id="a"></form>', 'missing'));
    }

    public function testAltchaIsOnlyAskedWhereBrowsersCanComputeIt()
    {
        $this->assertTrue($this->guard->isSecureContext('https://wiki.example.org/?'));
        $this->assertTrue($this->guard->isSecureContext('http://localhost/yeswiki/?'));
        $this->assertTrue($this->guard->isSecureContext('http://127.0.0.1:8000/?'));
        $this->assertFalse($this->guard->isSecureContext('http://wiki.example.org/?'));
        $this->assertFalse($this->guard->isSecureContext('http://yeswiki-web/?'));
    }

    private function logIn(string $name): void
    {
        $userManager = $this->getWiki()->services->get(UserManager::class);
        if (!$userManager->getOneByName($name)) {
            $userManager->create($name, strtolower($name) . '@example.org', 'Un mot de passe 1!');
        }
        $_SESSION['user'] = ['name' => $name];
    }

    private function loggedInPost(): array
    {
        return [BotGuard::LOGGED_IN_ALTCHA_FIELD => $this->solvedAltcha($this->guard->fields())];
    }

    public function testALoggedInUserOnlyGetsAltcha()
    {
        $this->guard->useAltcha(true);
        $this->logIn('BotGuardTestMember');
        $fields = $this->guard->fields();

        $this->assertSame(BotGuard::MODE_ALTCHA, $this->guard->mode());
        $this->assertStringContainsString('<altcha-widget name="' . BotGuard::LOGGED_IN_ALTCHA_FIELD . '"', $fields);
        $this->assertStringNotContainsString('type="hidden"', $fields);
        $this->assertStringNotContainsString('yw-bot-guard"', $fields);
    }

    public function testALoggedInUserPassesWithASolvedChallengeOnce()
    {
        $this->guard->useAltcha(true);
        $this->logIn('BotGuardTestMember');
        $post = $this->loggedInPost();

        $this->assertSame(BotGuard::REFUSED_ALTCHA, $this->submit([]));
        $this->assertNull($this->submit($post));
        $this->assertSame(BotGuard::REFUSED_ALTCHA_REUSED, $this->submit($post));
    }

    public function testALoggedInUserCannotUseAChallengeSolvedForAnotherAccount()
    {
        $this->guard->useAltcha(true);
        $this->logIn('BotGuardTestOther');
        $post = $this->loggedInPost();
        $this->logIn('BotGuardTestMember');

        $this->assertSame(BotGuard::REFUSED_ALTCHA, $this->submit($post));
    }

    public function testALoggedInUserIsNotLimitedToADay()
    {
        $this->guard->useAltcha(true);
        $this->logIn('BotGuardTestMember');
        $post = $this->loggedInPost();
        $this->now += 3 * 86400;

        $this->assertNull($this->submit($post));
    }

    public function testALoggedInUserGoesThroughWhenAltchaIsOff()
    {
        $this->logIn('BotGuardTestMember');

        $this->assertSame(BotGuard::MODE_NONE, $this->guard->mode());
        $this->assertSame('', $this->guard->fields());
        $this->assertNull($this->submit([]));
    }

    public function testAnAdminGoesThrough()
    {
        $this->guard->useAltcha(true);
        $admins = $this->getWiki()->services->get(GroupManager::class)->getMembers('admins');
        $_SESSION['user'] = ['name' => $admins[0]];

        $this->assertSame(BotGuard::MODE_NONE, $this->guard->mode());
        $this->assertSame('', $this->guard->fields());
        $this->assertNull($this->submit([]));
    }

    public function testAStrictFormAsksALoggedInUserForEverything()
    {
        $this->guard->useAltcha(true);
        $this->logIn('BotGuardTestMember');

        $this->assertSame(BotGuard::MODE_FULL, $this->guard->mode(true));
        $this->assertStringContainsString('name="' . $this->names()['token'] . '"', $this->guard->fields(true));
        $this->assertSame(BotGuard::REFUSED_TOKEN_MISSING, $this->guard->check(new Request([], $this->loggedInPost()), true));
    }

    public function testAStrictFormStillLetsAnAdminThrough()
    {
        $admins = $this->getWiki()->services->get(GroupManager::class)->getMembers('admins');
        $_SESSION['user'] = ['name' => $admins[0]];

        $this->assertSame(BotGuard::MODE_NONE, $this->guard->mode(true));
        $this->assertNull($this->guard->check(new Request([], []), true));
    }
}
