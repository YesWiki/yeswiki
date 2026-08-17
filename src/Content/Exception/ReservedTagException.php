<?php

namespace YesWiki\Content\Exception;

/** Thrown when Content is written on a tag the router owns (ticket 20). */
class ReservedTagException extends \Exception
{
}
