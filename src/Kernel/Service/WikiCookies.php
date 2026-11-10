<?php

namespace YesWiki\Kernel\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/** How the wiki sends its own cookies: under its path, secure on https, never to scripts, with the SameSite the configuration asks for. */
class WikiCookies
{
    public function __construct(
        private readonly ParameterBagInterface $params,
        private readonly CurrentRequest $request,
    ) {
    }

    /** Sends a cookie, or nothing once the headers have left. */
    public function set(string $name, string $value, int $expires): void
    {
        if (!headers_sent()) {
            setcookie($name, $value, $this->options($expires));
        }
    }

    /** @return array{expires: int, path: string, domain: string, secure: bool, httponly: bool, samesite: 'Lax'|'None'|'Strict'} */
    public function options(int $expires): array
    {
        $path = $this->params->has('cookie_path') ? $this->params->get('cookie_path') : '/';
        $baseUrl = $this->params->has('base_url') ? $this->params->get('base_url') : '';
        $secure = (is_string($baseUrl) && str_starts_with($baseUrl, 'https://')) || $this->request->get()->isSecure();
        $configured = $this->params->has('cookies_samesite') ? $this->params->get('cookies_samesite') : 'None';
        $sameSite = strtolower(is_string($configured) ? $configured : 'None');

        return [
            'expires' => $expires,
            'path' => is_string($path) && $path !== '' ? $path : '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => $sameSite === 'strict' ? 'Strict' : (($sameSite === 'none' && $secure) ? 'None' : 'Lax'),
        ];
    }
}
