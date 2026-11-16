<?php

namespace YesWiki\Identity\SignIn\Contract;

/**
 * A type whose provider signs in the wiki's own accounts, the ones no provider is linked to, against what the wiki stores: local.
 * Everything the wiki does differently for such a provider follows from that one fact:
 *
 * - its identity names the account directly, which no other provider's may;
 * - the person's choice to be remembered is kept, and the form offers the lost password and the sign-up;
 * - it is never tested: checking a password can rewrite its stored hash, and a test changes nothing;
 * - it has one entry, under the type's own name, always there: nobody adds or deletes it;
 * - while it is on, an administrator with an unlinked account keeps a way in.
 *
 * Reserved to core's types: ProviderRegistry ignores it on an extension's, which adds a protocol and cannot change the account rules.
 */
interface ChecksTheWikisOwnAccounts extends ProviderType
{
}
