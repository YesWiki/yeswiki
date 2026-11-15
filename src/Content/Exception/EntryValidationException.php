<?php

namespace YesWiki\Content\Exception;

use YesWiki\Kernel\Exception\SpeaksToTheReader;

/**
 * A submitted entry the visitor can fix: a required field left empty, a form that cannot name its entries.
 */
class EntryValidationException extends \Exception implements SpeaksToTheReader
{
}
