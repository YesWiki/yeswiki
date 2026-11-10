<?php

namespace YesWiki\Identity\Service;

use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\PersonalResponse;

/** Whom a CSRF token is bound to: the signed-in account and its generation, or an anonymous visitor's random `yw-visitor` cookie, set the first time a form needs a token. */
class CsrfVisitor
{
    public const COOKIE = 'yw-visitor';

    public const LIFETIME = 90 * 24 * 3600;

    public function __construct(
        private readonly AuthenticationService $authentication,
        private readonly CurrentRequest $request,
        private readonly PersonalResponse $personal,
    ) {
    }

    public function current(): string
    {
        $this->personal->mark();
        $name = $this->authentication->signedInName();
        if ($name !== '') {
            return 'user:' . $name . ':' . $this->authentication->signedInGeneration();
        }
        $visitor = $_COOKIE[self::COOKIE] ?? $this->request->get()->cookies->get(self::COOKIE);
        if (!is_string($visitor) || preg_match('/^[0-9a-f]{32}$/', $visitor) !== 1) {
            $visitor = bin2hex(random_bytes(16));
            $_COOKIE[self::COOKIE] = $visitor;
            $this->authentication->setPersistentCookie(self::COOKIE, $visitor, time() + self::LIFETIME);
        }

        return 'visitor:' . $visitor;
    }
}
