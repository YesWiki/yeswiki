<?php

namespace YesWiki\Kernel\Queue;

use Carbon\CarbonInterval;
use YesWiki\Kernel\Service\LanguageService;

/** How the queue's alerts and its jobs page name a message type and a length of time, so that both say it the same way. */
final class QueueWording
{
    /** What a message type is called: its short class name. */
    public static function type(string $class): string
    {
        return ($position = strrpos($class, '\\')) === false ? $class : substr($class, $position + 1);
    }

    /** "30 minutes", "1 jour 4 heures": a length of time in the reader's language, in its two largest units. */
    public static function duration(int $seconds): string
    {
        return CarbonInterval::seconds($seconds)->cascade()->locale(LanguageService::getInstance()->preferredLanguage())->forHumans(['parts' => 2]);
    }
}
