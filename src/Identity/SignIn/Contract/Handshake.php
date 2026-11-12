<?php

namespace YesWiki\Identity\SignIn\Contract;

/** What a redirect sign-in remembers between leaving the wiki and coming back (state, nonce, verifier...). */
final class Handshake
{
    /** @var array<string, string> */
    private array $values = [];

    public function __construct(
        public readonly string $providerId,
        public readonly string $returnUrl,
        public readonly string $callbackUrl,
        public readonly HandshakeMode $mode,
        public readonly int $expiresAt,
    ) {
    }

    public function set(string $key, string $value): void
    {
        $this->values[$key] = $value;
    }

    public function get(string $key): ?string
    {
        return $this->values[$key] ?? null;
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'p' => $this->providerId,
            'r' => $this->returnUrl,
            'c' => $this->callbackUrl,
            'm' => $this->mode->value,
            'e' => $this->expiresAt,
            'v' => $this->values,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromPayload(array $payload): ?self
    {
        if (!isset($payload['p'], $payload['r'], $payload['c'], $payload['m'], $payload['e'])
            || !is_string($payload['p']) || !is_string($payload['r']) || !is_string($payload['c'])
            || !is_string($payload['m']) || !is_int($payload['e'])) {
            return null;
        }
        $mode = HandshakeMode::tryFrom($payload['m']);
        if ($mode === null) {
            return null;
        }
        $handshake = new self($payload['p'], $payload['r'], $payload['c'], $mode, $payload['e']);
        foreach (is_array($payload['v'] ?? null) ? $payload['v'] : [] as $key => $value) {
            if (is_string($value)) {
                $handshake->set((string)$key, $value);
            }
        }

        return $handshake;
    }
}
