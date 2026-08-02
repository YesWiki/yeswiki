<?php

namespace YesWiki\Identity\Exception;

/**
 * Thrown when someone tries to register under a name the router owns (ticket 20).
 *
 * Distinct from UserNameAlreadyUsedException on purpose. A taken name is somebody else's;
 * a reserved name is nobody's and never will be.
 *
 * Registration is the one creation path that must REFUSE rather than suggest, because an
 * account's name IS its page tag -- UserManager::buildBody() deliberately does not store a
 * second copy that could drift. Everywhere else a tag is generated from something that is
 * not the identity itself (a form's label, an entry's title, a filename), so suffixing the
 * tag leaves the visible name untouched. Here it would not: silently resolving `api` to
 * `api-2` would mean someone typed one username at signup and has a different one
 * afterwards.
 */
class UserNameReservedException extends \Exception
{
}
