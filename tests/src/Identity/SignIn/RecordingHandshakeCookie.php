<?php

namespace YesWiki\Test\Identity\SignIn;

use Psr\Container\ContainerInterface;
use YesWiki\Identity\Service\SignIn\HandshakeCookie;
use YesWiki\Kernel\Service\SignedTokens;
use YesWiki\Kernel\Service\WikiCookies;

/** A HandshakeCookie that remembers the value it last sent, since a CLI run cannot read its Set-Cookie headers. */
class RecordingHandshakeCookie extends HandshakeCookie
{
    public ?string $sent = null;

    public static function from(ContainerInterface $services): self
    {
        return new self($services->get(SignedTokens::class), $services->get(WikiCookies::class));
    }

    protected function send(string $value, int $expires): void
    {
        $this->sent = $value === '' ? null : $value;
        parent::send($value, $expires);
    }
}
