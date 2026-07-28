<?php

namespace YesWiki\Render\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredPerformable;

class FaviconAction extends YesWikiAction implements RegisteredPerformable
{
    /**
     * `{{favicon}}` in page content. Stated rather than inferred from the filename, which
     * is what let this class finally have a namespace (ticket 06).
     */
    public static function performableName(): string
    {
        return 'favicon';
    }

    public function run(): string
    {
        $favicon = $this->wiki->getConfigValue('favicon');

        // backward compatibility, favicon used to be in the theme folder
        if (!$favicon) {
            $favicon = "themes/{$this->wiki->getConfigValue('favorite_theme')}/images/favicon.png";
            if (file_exists("custom/$favicon")) {
                $favicon = "custom/$favicon";
            } // handles custom theme
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
