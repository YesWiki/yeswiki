<?php

namespace YesWiki\Content\Exception;

use YesWiki\Kernel\Exception\SpeaksToTheReader;

/** Thrown when Content is written on a tag that is in the trash, which has to be restored rather than overwritten (end-users 13). */
class TrashedTagException extends \Exception implements SpeaksToTheReader
{
}
