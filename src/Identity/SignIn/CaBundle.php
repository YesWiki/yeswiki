<?php

namespace YesWiki\Identity\SignIn;

use YesWiki\Files\Service\LocalFiles;
use YesWiki\Identity\SignIn\Contract\FailureReason;
use YesWiki\Identity\SignIn\Contract\ProviderFailure;

/**
 * The certificate authorities a server's certificate is checked against: the file an administrator configured, else the system's.
 * The system's is looked for at run time, since a static binary carries the path of the machine it was built on, where none may be.
 */
final class CaBundle
{
    private const LAST_RESORT = '/etc/ssl/certs/ca-certificates.crt';

    /** The configured path as written, which the caller checks; without one, the first system bundle that can be read. */
    public static function path(?string $configured): string
    {
        $configured = trim((string)$configured);
        if ($configured !== '') {
            return $configured;
        }
        foreach (self::systemCandidates() as $candidate) {
            if (self::isReadable($candidate)) {
                return $candidate;
            }
        }

        throw new ProviderFailure(FailureReason::ConfigurationInvalid, 'no system certificate authorities could be found (tried ' . implode(', ', self::systemCandidates()) . '): set ca_cert to the CA file that signed the server\'s certificate');
    }

    /** Whether curl will be able to open that bundle: a path of this machine, which curl opens itself. */
    public static function isReadable(string $path): bool
    {
        return (new LocalFiles())->isReadableFile($path);
    }

    /** @return list<string> */
    private static function systemCandidates(): array
    {
        $candidates = [];
        if (function_exists('openssl_get_cert_locations')) {
            $candidates[] = (string)(openssl_get_cert_locations()['default_cert_file'] ?? '');
        }
        $candidates[] = (string)ini_get('curl.cainfo');
        $candidates[] = self::LAST_RESORT;

        return array_values(array_unique(array_filter($candidates, fn (string $path): bool => $path !== '')));
    }
}
