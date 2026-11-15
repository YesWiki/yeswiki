<?php

namespace YesWiki\Content\Exception;

use YesWiki\Kernel\Exception\SpeaksToTheReader;

/** Thrown when Content is written on a tag the router owns (ticket 20). */
class ReservedTagException extends \Exception implements SpeaksToTheReader
{
}
