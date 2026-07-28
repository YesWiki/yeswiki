<?php

namespace YesWiki\Content\Action;
use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;

class MapAction extends YesWikiAction implements RegisteredAction
{
    /** `{{map}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'map';
    }

    public function run()
    {
        // Retrocompatibility
        return $this->callAction('bazarcarto', $this->arguments);
    }
}
