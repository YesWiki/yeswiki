<?php

namespace YesWiki\Kernel\Service;

use Symfony\Component\DependencyInjection\Attribute\AutowireServiceClosure;

/**
 * The messages a request leaves for the page that shows next (replicas 14): kept in this request while a page renders, and otherwise
 * in a signed `yw-flash` cookie for a minute, so the replica serving the redirect shows what the one that saved wrote. No session.
 */
class FlashMessages implements RequestScopedState
{
    public const COOKIE = 'yw-flash';

    public const LIFETIME = 60;

    public const MAX_BYTES = 3000;

    public const ALERTS = ['error' => 'danger', 'success' => 'success', 'info' => 'info', 'warning' => 'warning'];

    public const TOAST = 'toast';

    /** @var list<array{0: string, 1: string}> */
    private array $pending = [];

    /** @var list<array{0: string, 1: string}>|null what the cookie brought, read once */
    private ?array $arrived = null;

    public function __construct(
        private readonly SignedTokens $tokens,
        private readonly WikiCookies $cookies,
        private readonly CurrentRequest $request,
        #[AutowireServiceClosure(Journal::class)]
        private readonly ?\Closure $journal = null,
        private readonly ?PersonalResponse $personal = null,
    ) {
    }

    public function startNewRequest(): void
    {
        $this->pending = [];
        $this->arrived = null;
    }

    /** A message for the next page shown: `error`, `success`, `info` or `warning`, HTML as the caller gives it. */
    public function add(string $type, string $message): void
    {
        $this->pending[] = [$type, $message];
        $this->persist();
    }

    /** The one notice a toast shows, replacing any other (the historic SetMessage()). */
    public function toast(string $message): void
    {
        $this->pending = array_values(array_filter($this->pending, static fn (array $item): bool => $item[0] !== self::TOAST));
        $this->arrived = array_values(array_filter($this->arrived(), static fn (array $item): bool => $item[0] !== self::TOAST));
        $this->add(self::TOAST, $message);
    }

    /** The notice a toast shows, '' when there is none; it is shown once. */
    public function takeToast(): string
    {
        return implode('<br>', array_column($this->take(static fn (string $type): bool => $type === self::TOAST), 1));
    }

    /** The alerts waiting, as Bootstrap alerts, one per kind in the order they came; they are shown once. */
    public function display(): string
    {
        $byType = [];
        foreach ($this->take(static fn (string $type): bool => isset(self::ALERTS[$type])) as [$type, $message]) {
            $byType[$type][] = $message;
        }
        $html = '';
        foreach ($byType as $type => $messages) {
            $html .= '<div class="alert alert-' . self::ALERTS[$type] . '" role="alert">' . implode('', array_map(static fn (string $message): string => $message . '<br />', $messages)) . '</div>';
        }

        return $html;
    }

    /**
     * @param \Closure(string): bool $wanted
     *
     * @return list<array{0: string, 1: string}>
     */
    private function take(\Closure $wanted): array
    {
        $taken = [];
        $kept = static function (array $items) use ($wanted, &$taken): array {
            $left = [];
            foreach ($items as $item) {
                if ($wanted($item[0])) {
                    $taken[] = $item;
                } else {
                    $left[] = $item;
                }
            }

            return $left;
        };
        $this->arrived = $kept($this->arrived());
        $this->pending = $kept($this->pending);
        if ($taken !== []) {
            $this->personal?->mark();
            $this->persist();
        }

        return $taken;
    }

    /** Writes what is still to show into the cookie, or expires the cookie once nothing is. */
    private function persist(): void
    {
        $left = [...$this->arrived(), ...$this->pending];
        if ($left === []) {
            if ($this->request->get()->cookies->has(self::COOKIE) || isset($_COOKIE[self::COOKIE])) {
                $this->cookies->set(self::COOKIE, '', time() - 3600);
                unset($_COOKIE[self::COOKIE]);
            }

            return;
        }
        $payload = self::encode((string)json_encode($left, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        while (strlen($payload) > self::MAX_BYTES && count($left) > 1) {
            $dropped = array_shift($left);
            if ($this->journal !== null) {
                ($this->journal)()->warning('A flash message was dropped: the cookie that carries them cannot hold more', ['action' => 'flash.dropped', 'target' => $dropped[0]]);
            }
            $payload = self::encode((string)json_encode($left, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
        $value = $payload . '.' . $this->tokens->sign(self::COOKIE . ':' . $payload);
        $this->cookies->set(self::COOKIE, $value, time() + self::LIFETIME);
        $_COOKIE[self::COOKIE] = $value;
    }

    /** @return list<array{0: string, 1: string}> */
    private function arrived(): array
    {
        if ($this->arrived !== null) {
            return $this->arrived;
        }
        $this->arrived = [];
        $cookie = $this->request->get()->cookies->get(self::COOKIE);
        if (!is_string($cookie) || substr_count($cookie, '.') !== 1) {
            return $this->arrived;
        }
        [$payload, $signature] = explode('.', $cookie);
        if (!$this->tokens->verify(self::COOKIE . ':' . $payload, $signature)) {
            return $this->arrived;
        }
        $items = json_decode((string)base64_decode(strtr($payload, '-_', '+/'), true), true);
        foreach (is_array($items) ? $items : [] as $item) {
            if (is_array($item) && is_string($item[0] ?? null) && is_string($item[1] ?? null)) {
                $this->arrived[] = [$item[0], $item[1]];
            }
        }

        return $this->arrived;
    }

    private static function encode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
