<?php

namespace YesWiki\Content\Exception;

use YesWiki\Kernel\Exception\SpeaksToTheReader;

/** Thrown when creating Content would write over a tag some other Content already holds. */
class TagAlreadyUsedException extends \Exception implements SpeaksToTheReader
{
}
