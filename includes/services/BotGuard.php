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
 * Keeps robots out of forms: anonymous visitors get a signed single-use token, a honeypot and ALTCHA; logged-in users get ALTCHA alone; admins get nothing.
 */
class BotGuard
{
    public const SECRET_KEY = 'bot_guard_secret';
    public const MIN_AGE = 3;
    public const MAX_AGE = 86400;
    public const COUNTERS_KEPT_DAYS = 30;
    public const LOGGED_IN_MAX_AGE = 7 * 86400;
    public const LOGGED_IN_ALTCHA_FIELD = 'yw_altcha';
    public const MODE_NONE = 'none';
    public const MODE_ALTCHA = 'altcha';
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
    public const REFUSED_ALTCHA_REUSED = 'altcha-reused';

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
     * What the current visitor goes through: everything when anonymous or for a strict form, ALTCHA alone when logged in, nothing for an admin.
     */
    public function mode(bool $strict = false): string
    {
        $user = $this->authController->getLoggedUser();
        if (empty($user)) {
            return self::MODE_FULL;
        }
        if ($this->userManager->isInGroup(ADMIN_GROUP, $user['name'], false)) {
            return self::MODE_NONE;
        }
        if ($strict) {
            return self::MODE_FULL;
        }

        return $this->altchaEnabled() ? self::MODE_ALTCHA : self::MODE_NONE;
    }

    /**
     * The HTML of the guard's fields for the current visitor; strict forms, those that send mail, ask logged-in users for everything.
     */
    public function fields(bool $strict = false): string
    {
        $mode = $this->mode($strict);
        if ($mode === self::MODE_NONE) {
            return '';
        }
        $secret = $this->secret();
        if ($secret === null) {
            return '';
        }
        if ($mode === self::MODE_ALTCHA) {
            return $this->templateEngine->render('@core/bot-guard-fields.twig', [
                'altchaName' => self::LOGGED_IN_ALTCHA_FIELD,
                'altchaChallenge' => $this->challenge($secret, ['user' => $this->authController->getLoggedUser()['name']], $this->time() + self::LOGGED_IN_MAX_AGE),
                'language' => $GLOBALS['prefered_language'] ?? 'fr',
            ]);
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
    public function insertInto(string $html, ?string $formId = null, bool $strict = false): string
    {
        if ($formId !== null) {
            if (!preg_match('/<form\b[^>]*\bid=["\']' . preg_quote($formId, '/') . '["\'][^>]*>/i', $html, $open, PREG_OFFSET_CAPTURE)) {
                return $html;
            }
            $close = stripos($html, '</form>', $open[0][1]);
            if ($close === false) {
                return $html;
            }

            return substr($html, 0, $close) . $this->fields($strict) . substr($html, $close);
        }
        $fields = $this->fields($strict);
        if ($fields === '') {
            return $html;
        }

        return preg_replace('/<\/form>/i', $fields . '</form>', $html);
    }

    /**
     * Checks a submission and consumes its token: null when it passes, else the reason it was refused.
     */
    public function check(Request $request, bool $strict = false): ?string
    {
        if ($this->mode($strict) === self::MODE_NONE) {
            return null;
        }
        if (isset($this->checked[$request])) {
            return $this->checked[$request]['reason'];
        }
        $reason = $this->checkOnce($request, $strict);
        $this->checked[$request] = ['reason' => $reason];

        return $reason;
    }

    protected function checkOnce(Request $request, bool $strict): ?string
    {
        $secret = $this->secret();
        if ($secret === null) {
            return self::REFUSED_NO_SECRET;
        }
        $post = $request->request->all();
        $reason = $this->mode($strict) === self::MODE_ALTCHA
            ? $this->loggedInRefusal($post, $secret)
            : $this->refusal($post, $secret);
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
        $since = self::COUNTER_RESOURCE . $this->day($this->time() - ($days - 1) * 86400);
        $rows = $this->dbService->loadAll(
            'SELECT property, value FROM' . $this->dbService->prefixTable('triples')
            . "WHERE resource >= '" . $this->dbService->escape($since) . "'"
            . " AND resource LIKE '" . self::COUNTER_RESOURCE . "%'"
            . " AND property LIKE '" . $this->dbService->escape(self::REFUSED_PROPERTY) . "%'"
        );
        $totals = [];
        foreach ($rows as $row) {
            $reason = substr($row['property'], strlen(self::REFUSED_PROPERTY));
            $totals[$reason] = ($totals[$reason] ?? 0) + (int)$row['value'];
        }

        return $totals;
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

    protected function loggedInRefusal(array $post, string $secret): ?string
    {
        $payload = $this->solvedChallenge($post[self::LOGGED_IN_ALTCHA_FIELD] ?? null, $secret, ['user' => $this->authController->getLoggedUser()['name']]);
        if ($payload === null) {
            return self::REFUSED_ALTCHA;
        }
        $parameters = $payload->challenge->parameters;
        if (!$this->claim('botGuard:altcha:' . $parameters->nonce, (int)$parameters->expiresAt)) {
            return self::REFUSED_ALTCHA_REUSED;
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
