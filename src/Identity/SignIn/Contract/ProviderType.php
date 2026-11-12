<?php

namespace YesWiki\Identity\SignIn\Contract;

/** A kind of provider the wiki knows how to build, from the core or from an extension. */
interface ProviderType
{
    /** The value of `type` in the configuration. */
    public function name(): string;

    public function label(): string;

    /**
     * The settings under `connection`, for the administration screen.
     *
     * @return list<\YesWiki\Kernel\Component\Setting>
     */
    public function connectionSettings(): array;

    /** @return array<string, string> the attribute names this type uses when the configuration says nothing */
    public function defaultAttributes(): array;

    public function create(ProviderConfig $config): IdentityProvider;
}
