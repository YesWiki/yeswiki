<?php

namespace YesWiki\Identity\SignIn;

/** What Doryphore's login extensions become: the `sso_providers` section, the keys to remove, and what to tell the operator. */
final class SsoConversionResult
{
    /**
     * @param array<string, array<string, mixed>> $providers         the whole section to write, existing entries included; empty when nothing converts
     * @param list<string>                        $removedKeys       the Doryphore keys to remove from the configuration
     * @param list<string>                        $messages          what the conversion did, never a secret
     * @param ?string                             $inCharge          the extension whose provider is switched on: loginsso, loginldap or logincas, null when local stays on or a section already existed
     * @param list<string>                        $loginssoIds       the identifiers of the providers that came from loginsso, in order
     * @param ?string                             $doryphoreInCharge the extension that signed people in on Doryphore, whatever the section holds now: what decides whether md5 hashes go
     */
    public function __construct(
        public readonly array $providers,
        public readonly array $removedKeys,
        public readonly array $messages,
        public readonly ?string $inCharge,
        public readonly array $loginssoIds,
        public readonly ?string $doryphoreInCharge,
    ) {
    }

    public function changesTheConfiguration(): bool
    {
        return $this->removedKeys !== [];
    }
}
