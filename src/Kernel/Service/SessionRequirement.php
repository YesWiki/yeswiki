<?php

namespace YesWiki\Kernel\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Kernel\Session\DatabaseSessions;

/**
 * The session core no longer starts (replicas 16), for an extension that still keeps something in `$_SESSION`: it asks for it here,
 * and gets one in the wiki's `sessions` table with the cookie flags the wiki's own cookies carry.
 */
class SessionRequirement
{
    public function __construct(
        private readonly ParameterBagInterface $params,
        private readonly WikiCookies $cookies,
        private readonly PersonalResponse $personal,
    ) {
    }

    /** Starts the session if none is open; false when it cannot, the headers having left. */
    public function start(): bool
    {
        $this->personal->mark();
        if (session_status() === PHP_SESSION_ACTIVE) {
            return true;
        }
        if (headers_sent()) {
            return false;
        }
        $options = $this->cookies->options(0);
        session_set_save_handler(new DatabaseSessions($this->params->all()), true);
        session_set_cookie_params(['lifetime' => 0, 'path' => $options['path'], 'domain' => '', 'secure' => $options['secure'], 'httponly' => true, 'samesite' => $options['samesite']]);
        session_name(self::nameFor($options['path']));
        session_cache_limiter('');
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '1000');

        return session_start();
    }

    /** The session cookie's name for a wiki served under that path, as core named it when it started sessions. */
    public static function nameFor(string $cookiePath): string
    {
        return $cookiePath === '/' ? 'YesWiki-main' : 'YesWiki-' . str_replace('/', '-', trim($cookiePath, '/'));
    }
}
