<?php

namespace YesWiki\Identity\Exception;

/** Thrown when someone tries to register under a name the router owns (ticket 20). */
class UserNameReservedException extends \Exception
{
}
