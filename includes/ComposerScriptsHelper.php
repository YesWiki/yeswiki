<?php

namespace YesWiki\Core;

use Composer\Script\Event;

class ComposerScriptsHelper
{
    public static function postInstall(Event $event)
    {
        // clean test files from svg-sanitize
        echo "clean test files from svg-sanitize\n";
        array_map('unlink', glob('vendor/enshrined/svg-sanitize/tests/data/*.svg'));

        // clean example files from zebra_image
        echo "clean example files from zebra_image\n";
        array_map('unlink', glob('vendor/stefangabos/zebra_image/examples/images/*'));
        if (is_dir('vendor/stefangabos/zebra_image/examples/images/')) {
            rmdir('vendor/stefangabos/zebra_image/examples/images/');
        }
        array_map('unlink', glob('vendor/stefangabos/zebra_image/examples/*'));
        if (is_dir('vendor/stefangabos/zebra_image/examples')) {
            rmdir('vendor/stefangabos/zebra_image/examples');
        }
    }
}
