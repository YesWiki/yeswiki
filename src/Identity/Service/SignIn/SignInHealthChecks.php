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

            // Accepted, since development and test servers run on http, but never silently: what crosses it can be read and changed on the way.
            HealthCheck::named('sign-in-plain-http')
                ->label(_t('HEALTH_SIGN_IN_PLAIN_HTTP'))
                ->says(_t('HEALTH_SIGN_IN_PLAIN_HTTP_SAYS'))
                ->linkedTo(self::ROUTE)
                ->degraded()
                ->runs(function (): ?string {
                    $found = $this->plainHttp();

                    return $found === [] ? null : _t('HEALTH_SIGN_IN_PLAIN_HTTP_FOUND', ['providers' => implode(', ', $found)]);
                }),
        ];
    }

    /**
     * Every enabled provider with an address setting of its type written in http://, as "id (setting, setting)".
     * Read from each type's own settings, so an extension's type is covered as the core's are.
     *
     * @return list<string>
     */
    private function plainHttp(): array
    {
        $types = $this->registry->types();
        $found = [];
        foreach ($this->registry->configs() as $config) {
            $type = $types[$config->type] ?? null;
            if (!$config->enabled || $type === null) {
                continue;
            }
            $names = [];
            foreach ($type->connectionSettings() as $setting) {
                $value = $config->connection($setting->name());
                if ($setting->type() === 'url' && is_string($value) && str_starts_with(strtolower(trim($value)), 'http://')) {
                    $names[] = $setting->name();
                }
            }
            if ($names !== []) {
                $found[] = sprintf('%s (%s)', $config->id, implode(', ', $names));
            }
        }

        return $found;
    }

    /** A seam: the tests cannot unload the extension. */
    protected function ldapLoaded(): bool
    {
        return extension_loaded('ldap');
    }
}
