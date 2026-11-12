<?php

namespace YesWiki\Identity\SignIn\Contract;

use Symfony\Component\HttpFoundation\Response;

/** A provider that has something to hand to the other side (SAML service provider metadata). */
interface PublishesMetadata
{
    public function metadataResponse(): Response;

    /**
     * Values an administrator copies into the provider's console.
     *
     * @return array<string, string> label => value
     */
    public function copyableValues(): array;
}
