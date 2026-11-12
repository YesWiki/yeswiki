<?php

namespace YesWiki\Identity\SignIn\Contract;

/** Why a sign-in was refused. The value is the code recorded in the Journal and shown to administrators. */
enum FailureReason: string
{
    case ProviderUnreachable = 'provider_unreachable';
    case ProviderRejected = 'provider_rejected';
    case InvalidResponse = 'invalid_response';
    case ConfigurationInvalid = 'configuration_invalid';
    case MissingAttribute = 'missing_attribute';
    case EmailConflict = 'email_conflict';
    case LinkedElsewhere = 'linked_elsewhere';
    case NoAccount = 'no_account';
    case ProviderDisabled = 'provider_disabled';
    case AccountCreationFailed = 'account_creation_failed';
    case WrongCredentials = 'wrong_credentials';
    case InternalError = 'internal_error';
}
