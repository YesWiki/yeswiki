<?php

namespace YesWiki\Identity\SignIn\Contract;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/** A provider the person is sent to, and who sends them back (OIDC, SAML, CAS). */
interface RedirectProvider extends IdentityProvider
{
    /** Send the person to the provider, noting in the handshake what is needed on the way back. */
    public function begin(Handshake $handshake): Response;

    /** @throws ProviderFailure */
    public function complete(Request $request, Handshake $handshake): ProviderIdentity;
}
