<?php

namespace YesWiki\Identity\Service\SignIn;

use YesWiki\Kernel\Health\HealthCheck;
use YesWiki\Kernel\Health\ProvidesHealthChecks;

/** Sign-in declares what makes a sign-in setup unable to work, and sends to the screen that fixes it (ADR-0026). */
class SignInHealthChecks implements ProvidesHealthChecks
{
    private const ROUTE = 'admin/sign-in';

    public function __construct(
        private readonly ProviderRegistry $registry,
        private readonly AccountLinks $links
    ) {
    }

    public function healthChecks(): array
    {
        return [
            // Nobody can sign in, whether the section disables every entry or leaves only broken or unavailable ones.
            HealthCheck::named('sign-in-no-provider')
                ->label(_t('HEALTH_SIGN_IN_NO_PROVIDER'))
                ->says(_t('HEALTH_SIGN_IN_NO_PROVIDER_SAYS'))
                ->linkedTo(self::ROUTE)
                ->runs(fn (): ?string => $this->registry->enabled() === [] ? _t('HEALTH_SIGN_IN_NO_PROVIDER_NONE') : null),

            HealthCheck::named('sign-in-config')
                ->label(_t('HEALTH_SIGN_IN_CONFIG'))
                ->says(_t('HEALTH_SIGN_IN_CONFIG_SAYS'))
                ->linkedTo(self::ROUTE)
                ->runs(function (): ?string {
                    $problems = $this->registry->problems();

                    return $problems === [] ? null : implode('; ', $problems);
                }),

            HealthCheck::named('sign-in-orphan-accounts')
                ->label(_t('HEALTH_SIGN_IN_ORPHANS'))
                ->says(_t('HEALTH_SIGN_IN_ORPHANS_SAYS'))
                ->linkedTo(self::ROUTE)
                ->runs(function (): ?string {
                    $known = $this->registry->configs();
                    $orphans = [];
                    foreach ($this->links->countByProvider() as $id => $count) {
                        if (!isset($known[(string)$id])) {
                            $orphans[] = sprintf('%s (%d)', $id, $count);
                        }
                    }

                    return $orphans === []
                        ? null
                        : _t('HEALTH_SIGN_IN_ORPHANS_FOUND', ['providers' => implode(', ', $orphans)]);
                }),

            HealthCheck::named('sign-in-ldap-module')
                ->label(_t('HEALTH_SIGN_IN_LDAP'))
                ->says(_t('HEALTH_SIGN_IN_LDAP_SAYS'))
                ->linkedTo(self::ROUTE)
                ->runs(function (): ?string {
                    if ($this->ldapLoaded()) {
                        return null;
                    }
                    $ids = [];
                    foreach ($this->registry->configs() as $config) {
                        if ($config->enabled && $config->type === 'ldap') {
                            $ids[] = $config->id;
                        }
                    }

                    return $ids === [] ? null : _t('HEALTH_SIGN_IN_LDAP_MISSING', ['providers' => implode(', ', $ids)]);
                }),
        ];
    }

    /** A seam: the tests cannot unload the extension. */
    protected function ldapLoaded(): bool
    {
        return extension_loaded('ldap');
    }
}
