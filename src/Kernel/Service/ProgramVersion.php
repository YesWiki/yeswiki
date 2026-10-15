<?php

/* Which YesWiki this Program is, read from its own composer.json (ADR-0029): the build injects "version" from the tag, */
/* and a git checkout states it in .yeswiki-version, which `make install` writes the way yeswiki-build-repo names a build. */

namespace YesWiki\Kernel\Service;

class ProgramVersion
{
    public const DEV = 'dev';

    public const CHECKOUT_VERSION_FILE = '.yeswiki-version';

    private static ?string $programManifest = null;

    private readonly string $releaseLine;
    private readonly string $version;

    /** Reads the Program's composer.json once per process, unless given another manifest's text. */
    public function __construct(?string $manifest = null)
    {
        $decoded = json_decode($manifest ?? self::programManifest(), true);
        $decoded = is_array($decoded) ? $decoded : [];

        $line = $decoded['extra']['yeswiki']['release-line'] ?? null;
        $this->releaseLine = is_string($line) ? trim($line) : '';

        $version = $decoded['version'] ?? ($manifest === null ? self::checkoutVersion() : null);
        $this->version = is_string($version) && trim($version) !== '' ? trim($version) : self::DEV;
    }

    /** The Release line this Program belongs to, e.g. `ectoplasme`. */
    public function releaseLine(): string
    {
        return $this->releaseLine;
    }

    /** The version the build injected from the tag, e.g. `5.2.0`, or `dev` for a checkout. */
    public function version(): string
    {
        return $this->version;
    }

    /** The release a wiki on this Program runs: the Program's own version, or $configured for a checkout, which has none. */
    public function release(string $configured): string
    {
        return $this->version === self::DEV ? $configured : $this->version;
    }

    private static function programManifest(): string
    {
        return self::$programManifest ??= (string)@file_get_contents((\defined('YESWIKI_PROGRAM_DIR') ? YESWIKI_PROGRAM_DIR : \dirname(__DIR__, 3)) . '/composer.json');
    }

    /** What `make install` wrote for a checkout (binary/checkout-version.sh), or null. */
    private static function checkoutVersion(): ?string
    {
        $described = trim((string)@file_get_contents((\defined('YESWIKI_PROGRAM_DIR') ? YESWIKI_PROGRAM_DIR : \dirname(__DIR__, 3)) . '/' . self::CHECKOUT_VERSION_FILE));

        return $described === '' ? null : $described;
    }
}
