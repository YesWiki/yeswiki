<?php

namespace YesWiki\Core\Service;

use AltchaOrg\Altcha\Algorithm\Pbkdf2;
use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\CreateChallengeOptions;
use AltchaOrg\Altcha\Payload;
use AltchaOrg\Altcha\VerifySolutionOptions;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use YesWiki\Core\Controller\AuthController;

/**
 * Keeps robots out of forms: everyone but admins gets a signed single-use token, a honeypot and ALTCHA; admins get nothing.
 */
class BotGuard
{
    public const SECRET_KEY = 'bot_guard_secret';
    public const MIN_AGE = 3;
    public const MAX_AGE = 86400;
    public const COUNTERS_KEPT_DAYS = 30;
    public const MODE_NONE = 'none';
    public const MODE_FULL = 'full';
    public const ALTCHA_COST = 1000;
    public const ALTCHA_MIN_COUNTER = 500;
    public const ALTCHA_MAX_COUNTER = 1000;
    public const ALTCHA_SCRIPT = 'javascripts/vendor/altcha/altcha.i18n.min.js';
    public const USED_TOKEN_PROPERTY = 'http://yeswiki.net/_vocabulary/botGuard/usedToken';
    public const REFUSED_PROPERTY = 'http://yeswiki.net/_vocabulary/botGuard/refused/';
    public const COUNTER_RESOURCE = 'botGuard:';

    public const REFUSED_NO_SECRET = 'no-secret';
    public const REFUSED_HONEYPOT = 'honeypot';
    public const REFUSED_TOKEN_MISSING = 'token-missing';
    public const REFUSED_TOKEN_INVALID = 'token-invalid';
    public const REFUSED_TOO_FAST = 'too-fast';
    public const REFUSED_TOKEN_EXPIRED = 'token-expired';
    public const REFUSED_TOKEN_REUSED = 'token-reused';
    public const REFUSED_ALTCHA = 'altcha';
    public const REFUSED_GIBBERISH = 'gibberish';
    public const REFUSED_DOTTED_GMAIL = 'dotted-gmail';
    public const REFUSED_INJECTION = 'injection';
    public const REFUSED_LINK_IN_NAME = 'link-in-name';
    public const REFUSED_REPEATED = 'repeated';

    public const GIBBERISH_MIN_LENGTH = 10;
    public const GMAIL_MAX_DOTS = 3;

    public const HONEYPOTS = [
        'referral_code' => 'BOT_GUARD_HONEYPOT_REFERRAL_CODE',
        'member_number' => 'BOT_GUARD_HONEYPOT_MEMBER_NUMBER',
        'case_reference' => 'BOT_GUARD_HONEYPOT_CASE_REFERENCE',
        'how_heard' => 'BOT_GUARD_HONEYPOT_HOW_HEARD',
        'voucher' => 'BOT_GUARD_HONEYPOT_VOUCHER',
        'invitation' => 'BOT_GUARD_HONEYPOT_INVITATION',
    ];

    protected $params;
    protected $dbService;
    protected $authController;
    protected $configurationService;
    protected $templateEngine;
    protected $assetsManager;
    protected $userManager;
    protected $configFile;
    protected $secret;
    protected $now;
    protected $altchaEnabled;
    protected $checked;

    public function __construct(
        ParameterBagInterface $params,
        DbService $dbService,
        AuthController $authController,
        ConfigurationService $configurationService,
        TemplateEngine $templateEngine,
        AssetsManager $assetsManager,
        UserManager $userManager,
    ) {
        $this->params = $params;
        $this->dbService = $dbService;
        $this->authController = $authController;
        $this->configurationService = $configurationService;
        $this->templateEngine = $templateEngine;
        $this->assetsManager = $assetsManager;
        $this->userManager = $userManager;
        $this->configFile = ConfigurationFileProvider::getConfigFileFromEnv();
        $this->checked = new \WeakMap();
    }

    /**
     * Points the service at another configuration file and clock, for tests.
     */
    public function useConfigFileAndClock(string $configFile, ?callable $now = null): void
    {
        $this->configFile = $configFile;
        $this->secret = null;
        $this->now = $now;
    }

    /**
     * What the current visitor goes through: nothing for an admin, everything for anyone else.
     */
    public function mode(): string
    {
        $user = $this->authController->getLoggedUser();
        if (!empty($user) && $this->userManager->isInGroup(ADMIN_GROUP, $user['name'], false)) {
            return self::MODE_NONE;
        }

        return self::MODE_FULL;
    }

    /**
     * The HTML of the guard's fields for the current visitor.
     */
    public function fields(): string
    {
        if ($this->mode() === self::MODE_NONE) {
            return '';
        }
        $secret = $this->secret();
        if ($secret === null) {
            return '';
        }
        $names = $this->namesFor($this->day($this->time()));
        $issuedAt = $this->time();
        $id = bin2hex(random_bytes(16));
        $altchaChallenge = $this->altchaEnabled() ? $this->challenge($secret, ['token' => $id], $issuedAt + self::MAX_AGE) : null;

        return $this->templateEngine->render('@core/bot-guard-fields.twig', [
            'tokenName' => $names['token'],
            'token' => $issuedAt . '.' . $id . '.' . $this->sign($secret, (string)$issuedAt, $id),
            'honeypotName' => $names['honeypot'],
            'honeypotLabel' => _t(self::HONEYPOTS[$names['honeypotKey']]),
            'altchaName' => $names['altcha'],
            'altchaChallenge' => $altchaChallenge,
            'language' => $GLOBALS['prefered_language'] ?? 'fr',
        ]);
    }

    /**
     * Inserts the guard's fields before every </form> of the given HTML, or only into the form with the given id.
     */
    public function insertInto(string $html, ?string $formId = null): string
    {
        return $this->placeFields($html, $this->fields(), $formId);
    }

    /**
     * Leaves HTML whose template already placed the given fields as it is, else inserts them as insertInto() does.
     */
    public function placeFields(string $html, string $fields, ?string $formId = null): string
    {
        if ($fields === '' || str_contains($html, $fields)) {
            return $html;
        }
        if ($formId !== null) {
            if (!preg_match('/<form\b[^>]*\bid=["\']' . preg_quote($formId, '/') . '["\'][^>]*>/i', $html, $open, PREG_OFFSET_CAPTURE)) {
                return $html;
            }
            $close = stripos($html, '</form>', $open[0][1]);
            if ($close === false) {
                return $html;
            }

            return substr($html, 0, $close) . $fields . substr($html, $close);
        }

        return preg_replace('/<\/form>/i', $fields . '</form>', $html);
    }

    /**
     * Checks a submission and consumes its token: null when it passes, else the reason it was refused.
     */
    public function check(Request $request): ?string
    {
        if ($this->mode() === self::MODE_NONE) {
            return null;
        }
        $post = $request->request->all();
        if (isset($this->checked[$request]) && $this->checked[$request]['post'] === $post) {
            return $this->checked[$request]['reason'];
        }
        $reason = $this->checkOnce($request);
        $this->checked[$request] = ['post' => $post, 'reason' => $reason];

        return $reason;
    }

    /**
     * Checks what a visitor wrote in a message: null when it passes, else the reason it looks written by a robot.
     */
    public function checkMessage(string $email, string $name, string $subject, string $message): ?string
    {
        if ($this->mode() === self::MODE_NONE) {
            return null;
        }
        $texts = array_map('trim', [$name, $subject, $message]);
        $reason = match (true) {
            array_filter($texts, fn ($text) => $this->isInjection($text)) !== [] => self::REFUSED_INJECTION,
            $this->isLink($texts[0]) => self::REFUSED_LINK_IN_NAME,
            $this->isRepeated($texts) => self::REFUSED_REPEATED,
            array_filter($texts, fn ($text) => $this->isGibberish($text)) !== [], $this->isShortRandom($texts) => self::REFUSED_GIBBERISH,
            $this->isDottedGmail($email) => self::REFUSED_DOTTED_GMAIL,
            default => null,
        };
        if ($reason !== null) {
            $this->count($reason);
        }

        return $reason;
    }

    /**
     * Whether a text carries markup or the quote-and-bracket runs that scanners probe forms with.
     */
    protected function isInjection(string $text): bool
    {
        if (preg_match('/<\s*\/?\s*(script|svg|img|iframe|object|embed)\b|javascript:|\bon(load|error|click|focus|mouseover)\s*=/i', $text)) {
            return true;
        }
        preg_match_all('/[\'"()<>,.]{5,}/', $text, $runs);

        return array_filter($runs[0], fn ($run) => preg_match('/[\'"]/', $run) && preg_match('/[()<>]/', $run)) !== [];
    }

    /**
     * Whether a text holds a link, which no one types as their name.
     */
    protected function isLink(string $text): bool
    {
        return (bool)preg_match('/https?:\/\/|www\.|->|№|[a-z0-9-]+\.[a-z]{2,}\/\S/i', $text);
    }

    /**
     * Whether the name and subject are the same word and the message repeats it, as in Test, Test, "un test".
     */
    protected function isRepeated(array $texts): bool
    {
        [$name, $subject, $message] = array_map('mb_strtolower', $texts);

        return $name !== '' && $name === $subject
            && preg_match('/(?<![\\p{L}\\p{N}])' . preg_quote($name, '/') . '(?![\\p{L}\\p{N}])/u', $message) === 1;
    }

    /**
     * Whether a text is one long run of letters with capitals scattered through it, as robots fill forms with.
     */
    protected function isGibberish(string $text): bool
    {
        if (strlen($text) < self::GIBBERISH_MIN_LENGTH || !preg_match('/^[a-zA-Z]+$/', $text)) {
            return false;
        }
        $upperShare = preg_match_all('/[A-Z]/', substr($text, 1)) / (strlen($text) - 1);

        return $upperShare >= 0.25 && $upperShare <= 0.75;
    }

    /**
     * Whether every field is a different short run of letters with lower and upper case mixed past the first one.
     */
    protected function isShortRandom(array $texts): bool
    {
        foreach ($texts as $text) {
            if (!preg_match('/^[a-zA-Z]{3,8}$/', $text) || !preg_match('/[a-z]/', $text) || !preg_match('/[A-Z]/', substr($text, 1))) {
                return false;
            }
        }

        return count(array_unique($texts)) === count($texts);
    }

    /**
     * Whether an address is a Gmail one whose name is cut up by more dots than people use.
     */
    protected function isDottedGmail(string $email): bool
    {
        $parts = explode('@', strtolower(trim($email)));

        return count($parts) === 2
            && in_array($parts[1], ['gmail.com', 'googlemail.com'], true)
            && substr_count($parts[0], '.') > self::GMAIL_MAX_DOTS;
    }

    /**
     * The posted data without the guard's own fields, so they are never saved.
     */
    public function withoutFields(array $post): array
    {
        $time = $this->time();
        $names = [];
        foreach ([$this->day($time), $this->day($time - 86400)] as $day) {
            $dayNames = $this->namesFor($day);
            array_push($names, $dayNames['token'], $dayNames['honeypot'], $dayNames['altcha']);
        }

        return array_diff_key($post, array_flip($names));
    }

    protected function checkOnce(Request $request): ?string
    {
        $secret = $this->secret();
        if ($secret === null) {
            return self::REFUSED_NO_SECRET;
        }
        $reason = $this->refusal($request->request->all(), $secret);
        if ($reason !== null) {
            $this->count($reason);
        }

        return $reason;
    }

    /**
     * The message to show a visitor whose submission was refused.
     */
    public function message(string $reason): string
    {
        return $reason === self::REFUSED_NO_SECRET ? _t('BOT_GUARD_NO_SECRET') : _t('BOT_GUARD_REFUSED');
    }

    /**
     * Refusals per reason over the last days, today included.
     */
    public function refusedLastDays(int $days): array
    {
        $totals = [];
        foreach ($this->refusedPerDay($days) as $reasons) {
            foreach ($reasons as $reason => $count) {
                $totals[$reason] = ($totals[$reason] ?? 0) + $count;
            }
        }

        return $totals;
    }

    /**
     * Refusals per reason for each of the last days, newest first, capped at the days counters are kept.
     */
    public function refusedPerDay(int $days): array
    {
        $days = max(1, min($days, self::COUNTERS_KEPT_DAYS));
        $perDay = [];
        for ($i = 0; $i < $days; $i++) {
            $perDay[$this->day($this->time() - $i * 86400)] = [];
        }
        $rows = $this->dbService->loadAll(
            'SELECT resource, property, value FROM' . $this->dbService->prefixTable('triples')
            . "WHERE resource >= '" . $this->dbService->escape(self::COUNTER_RESOURCE . array_key_last($perDay)) . "'"
            . " AND resource <= '" . $this->dbService->escape(self::COUNTER_RESOURCE . array_key_first($perDay)) . "'"
            . " AND property LIKE '" . $this->dbService->escape(self::REFUSED_PROPERTY) . "%'"
        );
        foreach ($rows as $row) {
            $day = substr($row['resource'], strlen(self::COUNTER_RESOURCE));
            if (isset($perDay[$day])) {
                $reason = substr($row['property'], strlen(self::REFUSED_PROPERTY));
                $perDay[$day][$reason] = ($perDay[$day][$reason] ?? 0) + (int)$row['value'];
            }
        }
        foreach ($perDay as &$reasons) {
            ksort($reasons);
        }

        return $perDay;
    }

    /**
     * Deletes the used tokens that expired and the counters older than COUNTERS_KEPT_DAYS.
     */
    public function purge(): void
    {
        $triples = $this->dbService->prefixTable('triples');
        $this->dbService->query(
            'DELETE FROM' . $triples
            . "WHERE property = '" . self::USED_TOKEN_PROPERTY . "'"
            . " AND value < '" . date('Y-m-d H:i:s', $this->time()) . "'"
        );
        $this->dbService->query(
            'DELETE FROM' . $triples
            . "WHERE property LIKE '" . $this->dbService->escape(self::REFUSED_PROPERTY) . "%'"
            . " AND resource LIKE '" . self::COUNTER_RESOURCE . "%'"
            . " AND resource < '" . self::COUNTER_RESOURCE . $this->day($this->time() - self::COUNTERS_KEPT_DAYS * 86400) . "'"
        );
    }

    protected function refusal(array $post, string $secret): ?string
    {
        $time = $this->time();
        $names = null;
        foreach ([$this->day($time), $this->day($time - 86400)] as $day) {
            $candidate = $this->namesFor($day);
            if (isset($post[$candidate['token']])) {
                $names = $candidate;
                break;
            }
        }
        if ($names === null) {
            return self::REFUSED_TOKEN_MISSING;
        }
        if (!empty($post[$names['honeypot']])) {
            return self::REFUSED_HONEYPOT;
        }
        $token = $post[$names['token']];
        if (!is_string($token) || !preg_match('/^(\d{1,12})\.([0-9a-f]{32})\.([0-9a-f]{64})$/', $token, $parts)) {
            return self::REFUSED_TOKEN_INVALID;
        }
        [, $issuedAt, $id, $signature] = $parts;
        if (!hash_equals($this->sign($secret, $issuedAt, $id), $signature)) {
            return self::REFUSED_TOKEN_INVALID;
        }
        $age = $time - (int)$issuedAt;
        if ($age < self::MIN_AGE) {
            return self::REFUSED_TOO_FAST;
        }
        if ($age > self::MAX_AGE) {
            return self::REFUSED_TOKEN_EXPIRED;
        }
        if ($this->altchaEnabled() && $this->solvedChallenge($post[$names['altcha']] ?? null, $secret, ['token' => $id]) === null) {
            return self::REFUSED_ALTCHA;
        }
        if (!$this->claim('botGuard:token:' . $id, (int)$issuedAt + self::MAX_AGE)) {
            return self::REFUSED_TOKEN_REUSED;
        }

        return null;
    }

    protected function challenge(string $secret, array $data, int $expiresAt): string
    {
        $this->assetsManager->AddJavascriptFile(self::ALTCHA_SCRIPT, false, true);

        return json_encode($this->altcha($secret)->createChallenge(new CreateChallengeOptions(
            algorithm: new Pbkdf2(),
            cost: self::ALTCHA_COST,
            counter: random_int(self::ALTCHA_MIN_COUNTER, self::ALTCHA_MAX_COUNTER),
            data: $data,
            expiresAt: $expiresAt,
        ))->toArray());
    }

    protected function claim(string $resource, int $expiresAt): bool
    {
        $triples = $this->dbService->prefixTable('triples');
        $resource = $this->dbService->escape($resource);
        $this->dbService->query(
            'INSERT INTO' . $triples . '(resource, property, value) VALUES ('
            . "'" . $resource . "', '" . self::USED_TOKEN_PROPERTY . "', '" . date('Y-m-d H:i:s', $expiresAt) . "')"
        );
        $ownId = mysqli_insert_id($this->dbService->getLink());
        $first = $this->dbService->loadSingle(
            'SELECT MIN(id) AS id FROM' . $triples
            . "WHERE resource = '" . $resource . "' AND property = '" . self::USED_TOKEN_PROPERTY . "'"
        );

        return $ownId > 0 && (int)($first['id'] ?? 0) === (int)$ownId;
    }

    protected function count(string $reason): void
    {
        $triples = $this->dbService->prefixTable('triples');
        $resource = $this->dbService->escape(self::COUNTER_RESOURCE . $this->day($this->time()));
        $property = $this->dbService->escape(self::REFUSED_PROPERTY . $reason);
        $where = "WHERE resource = '" . $resource . "' AND property = '" . $property . "'";
        $row = $this->dbService->loadSingle('SELECT id, value FROM' . $triples . $where);
        if (empty($row)) {
            $this->dbService->query('INSERT INTO' . $triples . "(resource, property, value) VALUES ('" . $resource . "', '" . $property . "', '1')");
        } else {
            $this->dbService->query('UPDATE' . $triples . "SET value = '" . ((int)$row['value'] + 1) . "' WHERE id = " . (int)$row['id']);
        }
    }

    /**
     * Whether the ALTCHA proof of work is asked for: the `altcha` setting, on a wiki browsers can compute it on.
     */
    public function altchaEnabled(): bool
    {
        if ($this->altchaEnabled === null) {
            $this->altchaEnabled = (!$this->params->has('altcha') || !in_array($this->params->get('altcha'), [false, 'false', 0, '0'], true))
                && $this->isSecureContext((string)$this->params->get('base_url'));
        }

        return $this->altchaEnabled;
    }

    /**
     * Whether browsers treat pages at this address as a secure context, which ALTCHA's Web Crypto needs.
     */
    public function isSecureContext(string $baseUrl): bool
    {
        $scheme = strtolower((string)parse_url($baseUrl, PHP_URL_SCHEME));
        $host = strtolower(trim((string)parse_url($baseUrl, PHP_URL_HOST), '[]'));

        return $scheme === 'https' || in_array($host, ['localhost', '127.0.0.1', '::1'], true) || str_ends_with($host, '.localhost');
    }

    /**
     * Turns ALTCHA on or off for this request, for tests.
     */
    public function useAltcha(bool $enabled): void
    {
        $this->altchaEnabled = $enabled;
    }

    protected function solvedChallenge($payload, string $secret, array $expectedData): ?Payload
    {
        if (!is_string($payload) || $payload === '') {
            return null;
        }
        try {
            $payload = Payload::fromBase64($payload);
            $result = $this->altcha($secret)->verifySolution(new VerifySolutionOptions(algorithm: new Pbkdf2(), payload: $payload));
        } catch (\Throwable $e) {
            return null;
        }
        $data = $payload->challenge->parameters->data ?? [];
        foreach ($expectedData as $key => $value) {
            if (($data[$key] ?? null) !== $value) {
                return null;
            }
        }

        return $result->verified ? $payload : null;
    }

    protected function altcha(string $secret): Altcha
    {
        return new Altcha(
            hmacSignatureSecret: hash_hmac('sha256', 'altcha-signature', $secret),
            hmacKeySignatureSecret: hash_hmac('sha256', 'altcha-key', $secret),
        );
    }

    protected function sign(string $secret, string $issuedAt, string $id): string
    {
        return hash_hmac('sha256', 'token|' . $issuedAt . '|' . $id, $secret);
    }

    /**
     * The field names of a given day, derived from the secret.
     */
    public function namesFor(string $day): array
    {
        $secret = $this->secret() ?? '';
        $hash = hash_hmac('sha256', 'names|' . $day, $secret);
        $honeypotKeys = array_keys(self::HONEYPOTS);
        $honeypotKey = $honeypotKeys[hexdec(substr($hash, 0, 4)) % count($honeypotKeys)];

        return [
            'token' => 'yw' . substr($hash, 4, 10),
            'honeypot' => $honeypotKey . '_' . substr($hash, 14, 4),
            'honeypotKey' => $honeypotKey,
            'altcha' => 'yw' . substr($hash, 18, 10),
        ];
    }

    protected function day(int $time): string
    {
        return date('Y-m-d', $time);
    }

    protected function time(): int
    {
        return $this->now ? (int)($this->now)() : time();
    }

    /**
     * The wiki's secret, generated and written to the configuration file on first use; null when it cannot be written.
     */
    protected function secret(): ?string
    {
        if (!empty($this->secret)) {
            return $this->secret;
        }
        $usesWikiConfig = $this->configFile === ConfigurationFileProvider::getConfigFileFromEnv();
        if ($usesWikiConfig && $this->params->has(self::SECRET_KEY) && !empty($this->params->get(self::SECRET_KEY))) {
            return $this->secret = $this->params->get(self::SECRET_KEY);
        }
        $lock = @fopen($this->configFile, 'r+');
        if ($lock === false) {
            return null;
        }
        try {
            if (!flock($lock, LOCK_EX)) {
                return null;
            }
            $config = $this->configurationService->getConfiguration($this->configFile);
            $config->load();
            if (empty($config[self::SECRET_KEY])) {
                $config[self::SECRET_KEY] = bin2hex(random_bytes(32));
                if (!$this->configurationService->write($config)) {
                    return null;
                }
            }

            return $this->secret = $config[self::SECRET_KEY];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
