<?php

namespace YesWiki\Identity\SignIn\Contract;

/** A type whose provider signs in the wiki's own accounts, the ones no provider is linked to, against what the wiki stores: local. */
interface ChecksTheWikisOwnAccounts extends ProviderType
{
}
