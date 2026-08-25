<?php

namespace YesWiki\Render\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\RuntimeConfig;

class FaviconAction extends YesWikiAction implements RegisteredAction
{
    /** `{{favicon}}` in page content. */
    public static function performableName(): string
    {
        return 'favicon';
    }

    public function run(): string
    {
        $favicon = $this->getService(RuntimeConfig::class)->getValue('favicon');

        if (!$favicon) {
            $favicon = "themes/{$this->getService(RuntimeConfig::class)->getValue('favorite_theme')}/images/favicon.png";
            if ($this->getService(\YesWiki\Files\Service\Storage::class)->exists("custom/$favicon")) {
                $favicon = "custom/$favicon";
            }
        }

        $isEmoji = strpos($favicon, '.') === false;
        if ($isEmoji) {
            return <<<HTML
            <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>$favicon</text></svg>">
          HTML;
        }

        return <<<HTML
            <link rel="icon" type="image/png" href="$favicon" />
          HTML;
    }
}
