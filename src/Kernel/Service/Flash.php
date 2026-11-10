<?php

namespace YesWiki\Kernel\Service;

/** The static door to FlashMessages that the call sites of the old SimpleFlash kept: `Flash::success()`, `Flash::display()`. */
final class Flash
{
    private static ?FlashMessages $messages = null;

    /** @var list<array{0: string, 1: string}> what was said before the wiki booted, shown by the first display() */
    private static array $early = [];

    public static function bind(FlashMessages $messages): void
    {
        self::$messages = $messages;
        foreach (self::$early as [$type, $message]) {
            $messages->add($type, $message);
        }
        self::$early = [];
    }

    public static function success(string $message): void
    {
        self::add('success', $message);
    }

    public static function error(string $message): void
    {
        self::add('error', $message);
    }

    public static function info(string $message): void
    {
        self::add('info', $message);
    }

    public static function warning(string $message): void
    {
        self::add('warning', $message);
    }

    public static function display(): string
    {
        return self::$messages?->display() ?? '';
    }

    private static function add(string $type, string $message): void
    {
        if (self::$messages === null) {
            self::$early[] = [$type, $message];

            return;
        }
        self::$messages->add($type, $message);
    }
}
