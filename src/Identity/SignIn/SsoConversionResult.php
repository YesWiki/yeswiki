<?php

namespace YesWiki\Identity\SignIn;

/** What Doryphore's login extensions become: the `sso_providers` section, the keys to remove, and what to tell the operator. */
final class SsoConversionResult
{
    /**
     * @param array<string, array<string, mixed>> $providers   the whole section to write, existing entries included; empty when nothing converts
     * @param list<string>                        $removedKeys the Doryphore keys to remove from the configuration
     * @param list<string>                        $messages    what the conversion did, never a secret
     * @param ?string                             $inCharge    the extension that signed people in: loginsso, loginldap or logincas
     * @param list<string>                        $loginssoIds the identifiers of the providers that came from loginsso, in order
     */
    public function __construct(
        public readonly array $providers,
        public readonly array $removedKeys,
        public readonly array $messages,
        public readonly ?string $inCharge,
        public readonly array $loginssoIds,
    ) {
    }

    public function changesTheConfiguration(): bool
    {
        return $this->removedKeys !== [];
    }
}
