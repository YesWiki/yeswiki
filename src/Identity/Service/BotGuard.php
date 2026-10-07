<?php

namespace YesWiki\Identity\Service;

use AltchaOrg\Altcha\Algorithm\Pbkdf2;
use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\CreateChallengeOptions;
use AltchaOrg\Altcha\Payload;
use AltchaOrg\Altcha\VerifySolutionOptions;
use Symfony\Component\HttpFoundation\Request;
use YesWiki\Files\Service\Storage;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\LanguageService;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Render\Service\TemplateEngine;

/** Keeps robots out of forms with a token, a honeypot and ALTCHA. */
class BotGuard
{
    public const KEY_FILE = 'private/keys/bot-guard.key';
    public const USED_TOKEN_PROPERTY = 'http://yeswiki.net/_vocabulary/botGuard/usedToken';
    public const REFUSED_PROPERTY = 'http://yeswiki.net/_vocabulary/botGuard/refused/';
    public const RESOURCE_PREFIX = 'botGuard:';
    public const SETTING = 'altcha';
    public const MIN_AGE = 3;
    public const MAX_AGE = 86400;
    public const COUNTERS_KEPT_DAYS = 30;
    public const ALTCHA_COST = 1000;
    public const ALTCHA_MIN_COUNTER = 500;
    public const ALTCHA_MAX_COUNTER = 1000;
    public const ALTCHA_SCRIPT = 'javascripts/vendor/altcha/altcha.i18n.min.js';
    public const SCRIPT = 'javascripts/bot-guard.js';

    public const REFUSED_NO_SECRET = 'no-secret';
    public const REFUSED_HONEYPOT = 'honeypot';
    public const REFUSED_TOKEN_MISSING = 'token-missing';
    public const REFUSED_TOKEN_INVALID = 'token-invalid';
    public const REFUSED_TOO_FAST = 'too-fast';
    public const REFUSED_TOKEN_EXPIRED = 'token-expired';
    public const REFUSED_TOKEN_REUSED = 'token-reused';
    public const REFUSED_ALTCHA = 'altcha';

    public const HONEYPOTS = [
        'referral_code' => 'BOT_GUARD_HONEYPOT_REFERRAL_CODE',
        'member_number' => 'BOT_GUARD_HONEYPOT_MEMBER_NUMBER',
        'case_reference' => 'BOT_GUARD_HONEYPOT_CASE_REFERENCE',
        'how_heard' => 'BOT_GUARD_HONEYPOT_HOW_HEARD',
        'voucher' => 'BOT_GUARD_HONEYPOT_VOUCHER',
        'invitation' => 'BOT_GUARD_HONEYPOT_INVITATION',
    ];

    private const VERDICT_ATTRIBUTE = '_yw_bot_guard_verdict';

    private ?string $secret = null;

    /** @var (callable(): int)|null */
    private $clock;

    private ?bool $altchaOverride = null;

    public function __construct(
        private readonly DbService $dbService,
        private readonly AclService $aclService,
        private readonly RuntimeConfig $config,
        private readonly TemplateEngine $templateEngine,
        private readonly LanguageService $languageService,
        private Storage $storage,
    ) {
    }

    /** Swaps storage, clock and ALTCHA switch, for tests. */
    public function useForTests(?Storage $storage = null, ?callable $clock = null, ?bool $altcha = null): void
    {
        if ($storage !== null) {
            $this->storage = $storage;
            $this->secret = null;
        }
        $this->clock = $clock;
        $this->altchaOverride = $altcha;
    }

    /** Whether the current visitor is guarded: everyone but an admin. */
    public function applies(): bool
    {
        return !$this->aclService->isAdmin();
    }

    /** The guard's fields for the current visitor, '' for an admin. */
    public function fields(): string
    {
        if (!$this->applies()) {
            return '';
        }
        $secret = $this->secret();
        if ($secret === null) {
            return '';
        }
        $issuedAt = $this->time();
        $names = $this->namesFor($this->day($issuedAt));
        $id = bin2hex(random_bytes(16));

        return $this->templateEngine->render('@core/bot-guard-fields.twig', [
            'tokenName' => $names['token'],
            'token' => $issuedAt . '.' . $id . '.' . $this->sign($secret, (string)$issuedAt, $id),
            'honeypotName' => $names['honeypot'],
            'honeypotLabel' => _t(self::HONEYPOTS[$names['honeypotKey']]),
            'altchaName' => $names['altcha'],
            'altchaChallenge' => $this->altchaEnabled() ? $this->challenge($secret, ['token' => $id], $issuedAt + self::MAX_AGE) : null,
            'language' => $this->languageService->preferredLanguage(),
        ]);
    }

    /** Inserts the guard's fields into every form, or the one with $formId. */
    public function insertInto(string $html, ?string $formId = null): string
    {
        return $this->placeFields($html, $this->fields(), $formId);
    }

    /** Inserts $fields as insertInto() does unless the HTML has them. */
    public function placeFields(string $html, string $fields, ?string $formId = null): string
    {
        if ($fields === '' || str_contains($html, $fields)) {
            return $html;
        }
        if ($formId === null) {
            return (string)preg_replace('/<\/form>/i', addcslashes($fields, '\\$') . '</form>', $html);
        }
        if (!preg_match('/<form\b[^>]*\bid=["\']' . preg_quote($formId, '/') . '["\'][^>]*>/i', $html, $open, PREG_OFFSET_CAPTURE)) {
            return $html;
        }
        $close = stripos($html, '</form>', $open[0][1]);
        if ($close === false) {
            return $html;
        }

        return substr($html, 0, $close) . $fields . substr($html, $close);
    }

    /** Checks a submission and spends its token: null or the refusal reason. */
    public function check(Request $request): ?string
    {
        if (!$this->applies()) {
            return null;
        }
        $post = $request->request->all();
        $fingerprint = hash('sha256', serialize($post));
        $verdict = $request->attributes->get(self::VERDICT_ATTRIBUTE);
        if (is_array($verdict) && $verdict['post'] === $fingerprint) {
            return $verdict['reason'];
        }
        $reason = $this->refusal($post);
        if ($reason !== null) {
            $this->count($reason);
        }
        $request->attributes->set(self::VERDICT_ATTRIBUTE, ['post' => $fingerprint, 'reason' => $reason]);

        return $reason;
    }

    /**
     * The posted data without the guard's own fields.
     *
     * @param array<string, mixed> $post
     *
     * @return array<string, mixed>
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

    /** The message for a refused visitor. */
    public function message(string $reason): string
    {
        return $reason === self::REFUSED_NO_SECRET ? _t('BOT_GUARD_NO_SECRET') : _t('BOT_GUARD_REFUSED');
    }

    /**
     * Refusals per reason over the last days, today included.
     *
     * @return array<string, int>
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
     * Refusals per reason for each of the last days, newest first.
     *
     * @return array<string, array<string, int>>
     */
    public function refusedPerDay(int $days): array
    {
        $days = max(1, min($days, self::COUNTERS_KEPT_DAYS));
        $perDay = [];
        for ($i = 0; $i < $days; $i++) {
            $perDay[$this->day($this->time() - $i * 86400)] = [];
        }
        $rows = $this->dbService->loadAll(
            'SELECT resource, property, value FROM ' . $this->triples()
            . ' WHERE resource >= ? AND resource <= ? AND property LIKE ?',
            [self::RESOURCE_PREFIX . array_key_last($perDay), self::RESOURCE_PREFIX . array_key_first($perDay), self::REFUSED_PROPERTY . '%']
        );
        foreach ($rows as $row) {
            $day = substr((string)$row['resource'], strlen(self::RESOURCE_PREFIX));
            if (isset($perDay[$day])) {
                $reason = substr((string)$row['property'], strlen(self::REFUSED_PROPERTY));
                $perDay[$day][$reason] = ($perDay[$day][$reason] ?? 0) + (int)$row['value'];
            }
        }
        foreach ($perDay as &$reasons) {
            ksort($reasons);
        }

        return $perDay;
    }

    /** Deletes expired tokens and counters past COUNTERS_KEPT_DAYS. */
    public function purge(): void
    {
        $this->dbService->query(
            'DELETE FROM ' . $this->triples() . ' WHERE property = ? AND value < ?',
            [self::USED_TOKEN_PROPERTY, $this->expiry($this->time())]
        );
        $this->dbService->query(
            'DELETE FROM ' . $this->triples() . ' WHERE resource >= ? AND resource < ? AND property LIKE ?',
            [self::RESOURCE_PREFIX . '0', self::RESOURCE_PREFIX . $this->day($this->time() - self::COUNTERS_KEPT_DAYS * 86400), self::REFUSED_PROPERTY . '%']
        );
    }

    /** Whether ALTCHA is asked for. */
    public function altchaEnabled(): bool
    {
        if ($this->altchaOverride !== null) {
            return $this->altchaOverride;
        }

        return !in_array($this->config->getValue(self::SETTING, true), [false, 'false', 0, '0'], true)
            && $this->isSecureContext((string)$this->config->getValue('base_url', ''));
    }

    /** Whether browsers treat this address as a secure context. */
    public function isSecureContext(string $baseUrl): bool
    {
        $scheme = strtolower((string)parse_url($baseUrl, PHP_URL_SCHEME));
        $host = strtolower(trim((string)parse_url($baseUrl, PHP_URL_HOST), '[]'));

        return $scheme === 'https' || in_array($host, ['localhost', '127.0.0.1', '::1'], true) || str_ends_with($host, '.localhost');
    }

    /**
     * The field names of a given day.
     *
     * @return array{token: string, honeypot: string, honeypotKey: string, altcha: string}
     */
    public function namesFor(string $day): array
    {
        $hash = hash_hmac('sha256', 'names|' . $day, $this->secret() ?? '');
        $honeypotKeys = array_keys(self::HONEYPOTS);
        $honeypotKey = $honeypotKeys[hexdec(substr($hash, 0, 4)) % count($honeypotKeys)];

        return [
            'token' => 'yw' . substr($hash, 4, 10),
            'honeypot' => $honeypotKey . '_' . substr($hash, 14, 4),
            'honeypotKey' => $honeypotKey,
            'altcha' => 'yw' . substr($hash, 18, 10),
        ];
    }

    /** @param array<string, mixed> $post */
    private function refusal(array $post): ?string
    {
        $secret = $this->secret();
        if ($secret === null) {
            return self::REFUSED_NO_SECRET;
        }
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
        if ($this->altchaEnabled() && !$this->solved($post[$names['altcha']] ?? null, $secret, ['token' => $id])) {
            return self::REFUSED_ALTCHA;
        }
        if (!$this->claim($id, (int)$issuedAt + self::MAX_AGE)) {
            return self::REFUSED_TOKEN_REUSED;
        }

        return null;
    }

    /** @param array<string, string> $data */
    private function challenge(string $secret, array $data, int $expiresAt): string
    {
        return (string)json_encode($this->altcha($secret)->createChallenge(new CreateChallengeOptions(
            algorithm: new Pbkdf2(),
            cost: self::ALTCHA_COST,
            counter: random_int(self::ALTCHA_MIN_COUNTER, self::ALTCHA_MAX_COUNTER),
            data: $data,
            expiresAt: $expiresAt,
        ))->toArray());
    }

    /** @param array<string, string> $expectedData */
    private function solved(mixed $payload, string $secret, array $expectedData): bool
    {
        if (!is_string($payload) || $payload === '') {
            return false;
        }
        try {
            $payload = Payload::fromBase64($payload);
            $result = $this->altcha($secret)->verifySolution(new VerifySolutionOptions(algorithm: new Pbkdf2(), payload: $payload));
        } catch (\Throwable) {
            return false;
        }
        $data = $payload->challenge->parameters->data ?? [];
        foreach ($expectedData as $key => $value) {
            if (($data[$key] ?? null) !== $value) {
                return false;
            }
        }

        return $result->verified;
    }

    private function altcha(string $secret): Altcha
    {
        return new Altcha(
            hmacSignatureSecret: hash_hmac('sha256', 'altcha-signature', $secret),
            hmacKeySignatureSecret: hash_hmac('sha256', 'altcha-key', $secret),
        );
    }

    /** Records the token as used; true when this request was the first. */
    private function claim(string $id, int $expiresAt): bool
    {
        $resource = self::RESOURCE_PREFIX . 'token:' . $id;
        if ($this->firstClaim($resource) !== null) {
            return false;
        }
        $value = $this->expiry($expiresAt) . '|' . bin2hex(random_bytes(8));
        $this->dbService->query(
            'INSERT INTO ' . $this->triples() . ' (resource, property, value) VALUES (?, ?, ?)',
            [$resource, self::USED_TOKEN_PROPERTY, $value]
        );

        return $this->firstClaim($resource) === $value;
    }

    /**
     * The value of the oldest claim on the resource.
     *
     * @phpstan-impure
     */
    private function firstClaim(string $resource): ?string
    {
        $row = $this->dbService->loadSingle(
            'SELECT value FROM ' . $this->triples() . ' WHERE resource = ? AND property = ? ORDER BY id ASC LIMIT 1',
            [$resource, self::USED_TOKEN_PROPERTY]
        );

        return $row === null ? null : (string)$row['value'];
    }

    /** Adds one to today's counter for $reason. */
    private function count(string $reason): void
    {
        $resource = self::RESOURCE_PREFIX . $this->day($this->time());
        $property = self::REFUSED_PROPERTY . $reason;
        $row = $this->dbService->loadSingle(
            'SELECT id, value FROM ' . $this->triples() . ' WHERE resource = ? AND property = ? ORDER BY id ASC LIMIT 1',
            [$resource, $property]
        );
        if (empty($row)) {
            $this->dbService->query(
                'INSERT INTO ' . $this->triples() . ' (resource, property, value) VALUES (?, ?, ?)',
                [$resource, $property, '1']
            );

            return;
        }
        $this->dbService->query(
            'UPDATE ' . $this->triples() . ' SET value = ? WHERE id = ?',
            [(string)((int)$row['value'] + 1), (int)$row['id']]
        );
    }

    private function triples(): string
    {
        return trim($this->dbService->prefixTable('triples'));
    }

    private function expiry(int $time): string
    {
        return date('Y-m-d H:i:s', $time);
    }

    private function sign(string $secret, string $issuedAt, string $id): string
    {
        return hash_hmac('sha256', 'token|' . $issuedAt . '|' . $id, $secret);
    }

    private function day(int $time): string
    {
        return date('Y-m-d', $time);
    }

    private function time(): int
    {
        return $this->clock !== null ? (int)($this->clock)() : time();
    }

    /** The wiki's key, created on first use; null when unavailable. */
    private function secret(): ?string
    {
        if ($this->secret !== null) {
            return $this->secret;
        }
        try {
            if (!$this->storage->exists(self::KEY_FILE)) {
                $this->storage->write(self::KEY_FILE, bin2hex(random_bytes(32)));
            }
            $key = trim($this->storage->read(self::KEY_FILE));
        } catch (\Throwable) {
            return null;
        }

        return $key === '' ? null : $this->secret = $key;
    }
}
