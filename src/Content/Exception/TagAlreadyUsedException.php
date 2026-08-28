<?php

namespace YesWiki\Content\Exception;

/** Thrown when creating Content would write over a tag some other Content already holds. */
class TagAlreadyUsedException extends \Exception
{
}
