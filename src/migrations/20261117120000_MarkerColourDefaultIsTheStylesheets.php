<?php

use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\WikiSettings;

/** A stored `baz_marker_color` still at the old `darkred` default is forgotten, so map markers take their colour from the Preset. */
class MarkerColourDefaultIsTheStylesheets extends YesWikiMigration
{
    public const OLD_DEFAULT = 'darkred';

    public function run()
    {
        $settings = $this->getService(WikiSettings::class);
        if (($settings->all()['baz_marker_color'] ?? null) !== self::OLD_DEFAULT) {
            return;
        }
        $settings->store([], ['baz_marker_color']);
        $this->say('The map marker colour was the old darkred default; markers now take the theme colours.');
    }
}
