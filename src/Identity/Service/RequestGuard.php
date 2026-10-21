<?php

namespace YesWiki\Identity\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManager;

/** Refuses requests that change something when another site sent them, and gives the wiki's own forms and links the token they must carry. */
class RequestGuard
{
    public const TOKEN_ID = 'main';
    public const FIELD = 'csrf-token';
    public const HEADER = 'X-CSRF-Token';
    public const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];
    public const OWN_SITE = ['same-origin', 'none'];
    public const LEGACY_FIELDS = ['csrfToken'];
    public const SCRIPT = 'javascripts/csrf-guard.js';

    public function __construct(
        private readonly ParameterBagInterface $params,
        private readonly CsrfTokenManager $csrfTokenManager,
        private readonly AuthenticationService $authenticationService,
    ) {
    }

    /** Why the request must be refused, or null when it may go on. */
    public function refusal(Request $request): ?string
    {
        if (in_array($request->getMethod(), self::SAFE_METHODS, true) || $this->carriesApiKey($request)) {
            return null;
        }
        if ($this->comesFromAnotherSite($request)) {
            return _t('CROSS_SITE_REQUEST_REFUSED');
        }
        if (!empty($this->authenticationService->getLoggedUser()) && !$this->carriesToken($request)) {
            return _t('CSRF_TOKEN_FAIL_ERROR');
        }

        return null;
    }

    /** Whether a browser says, or lets us see, that another site started the request. */
    public function comesFromAnotherSite(Request $request): bool
    {
        $site = $request->headers->get('Sec-Fetch-Site');
        if (!empty($site)) {
            return !in_array(strtolower($site), self::OWN_SITE, true);
        }
        $source = $request->headers->get('Origin') ?: $request->headers->get('Referer');
        if (empty($source)) {
            return false;
        }

        return !in_array($this->authority($source), $this->ownAuthorities($request), true);
    }

    /** Whether the request holds this session's token, in a header or a form field. */
    public function carriesToken(Request $request): bool
    {
        $fields = $request->request->all();
        $candidates = [$request->headers->get(self::HEADER), $fields[self::FIELD] ?? null];
        foreach (self::LEGACY_FIELDS as $legacy) {
            $candidates[] = $fields[$legacy] ?? null;
        }
        foreach ($candidates as $candidate) {
            if ($this->isValid($candidate)) {
                return true;
            }
        }

        return false;
    }

    /** Whether a link that changes something on GET came with this session's token, in its URL or a header. */
    public function linkIsSigned(Request $request): bool
    {
        return $this->isValid($request->query->all()[self::FIELD] ?? null)
            || $this->isValid($request->headers->get(self::HEADER));
    }

    /** The session's token, for a form field, a header or a link. */
    public function token(): string
    {
        return $this->csrfTokenManager->getToken(self::TOKEN_ID)->getValue();
    }

    /** The given wiki URL with the session's token added, for links that change something. */
    public function sign(string $url): string
    {
        $glue = str_contains($url, '?') ? '&' : '?';

        return $url . $glue . self::FIELD . '=' . rawurlencode($this->token());
    }

    /** Adds the token to every POST form of an HTML response that sends to this wiki. */
    public function arm(string $html, Request $request): string
    {
        if (stripos($html, '<form') === false) {
            return $html;
        }
        $field = '<input type="hidden" name="' . self::FIELD . '" value="' . htmlspecialchars($this->token(), ENT_QUOTES) . '">';
        $own = $this->ownAuthorities($request);

        return preg_replace_callback(
            '/<script\b[^>]*>.*?<\/script\s*>|<form\b[^>]*>/is',
            fn ($match) => $this->postsHere($match[0], $own) ? $match[0] . $field : $match[0],
            $html
        ) ?? $html;
    }

    /** kernel.response listener: arms the forms of a signed-in visitor's HTML pages. */
    public function onResponse(ResponseEvent $event): void
    {
        $response = $event->getResponse();
        if (!$event->isMainRequest() || !$this->isHtml($response) || empty($this->authenticationService->getLoggedUser())) {
            return;
        }
        $content = $response->getContent();
        if (is_string($content) && $content !== '') {
            $response->setContent($this->arm($content, $event->getRequest()));
        }
    }

    /** @param list<string> $own */
    protected function postsHere(string $tag, array $own): bool
    {
        if (!preg_match('/^<form\b/i', $tag) || !preg_match('/\bmethod\s*=\s*["\']?\s*post\b/i', $tag)) {
            return false;
        }
        if (!preg_match('/\baction\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $tag, $action)) {
            return true;
        }
        $target = trim(html_entity_decode($action[1] . ($action[2] ?? '') . ($action[3] ?? ''), ENT_QUOTES));
        if ($target === '' || preg_match('/^[?#]/', $target) || preg_match('#^/(?!/)#', $target)) {
            return true;
        }
        if (!preg_match('#^(https?:)?//#i', $target)) {
            return !preg_match('/^[a-z][a-z0-9+.-]*:/i', $target);
        }

        return in_array($this->authority($target), $own, true);
    }

    protected function isValid(mixed $candidate): bool
    {
        return is_string($candidate) && $candidate !== ''
            && $this->csrfTokenManager->isTokenValid(new CsrfToken(self::TOKEN_ID, $candidate));
    }

    protected function carriesApiKey(Request $request): bool
    {
        return preg_match('/^\s*Bearer\s+\S/i', (string)$request->headers->get('Authorization')) === 1;
    }

    /** @return list<string> */
    protected function ownAuthorities(Request $request): array
    {
        $baseUrl = $this->params->has('base_url') ? $this->params->get('base_url') : '';

        return array_values(array_unique(array_filter([
            strtolower($request->getHttpHost()),
            $this->authority(is_string($baseUrl) ? $baseUrl : ''),
        ])));
    }

    protected function authority(string $url): string
    {
        $parts = parse_url(str_starts_with($url, '//') ? 'https:' . $url : $url);
        if (!is_array($parts) || empty($parts['host'])) {
            return '';
        }
        $port = $parts['port'] ?? null;
        $default = ($parts['scheme'] ?? 'https') === 'http' ? 80 : 443;

        return strtolower($parts['host']) . ($port !== null && $port !== $default ? ':' . $port : '');
    }

    protected function isHtml(Response $response): bool
    {
        $type = $response->headers->get('Content-Type');
        if ($type === null) {
            foreach (headers_list() as $header) {
                if (stripos($header, 'content-type:') === 0) {
                    return stripos($header, 'html') !== false;
                }
            }

            return true;
        }

        return stripos($type, 'html') !== false;
    }
}
