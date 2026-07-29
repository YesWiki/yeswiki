<?php

namespace YesWiki\Admin\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\PerformableArguments;
use YesWiki\Render\Service\ThemeManager;

/**
 * `{{configuration}}` -- converted from the procedural actions/configuration.php by ticket 06.
 *
 * The body still prints rather than returning, so it runs inside an output buffer in its
 * own method: that is what the old runFileInBuffer() did, and it keeps any early `return;`
 * in the body from discarding output.
 */
class ConfigurationAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'configuration';
    }

    public function run(): string
    {
        ob_start();
        try {
            $this->emit();
        } catch (\Throwable $t) {
            // Several of these bodies end in $this->exit(), which throws. The old
            // runFileInBuffer() accumulated output into a by-reference variable, so a throw
            // did not discard what had already been printed; keep that by flushing into the
            // shared output before rethrowing -- and close the buffer either way.
            $this->output .= (string)ob_get_clean();

            throw $t;
        }

        return (string)ob_get_clean();
    }

    private function emit(): void
    {
        $themeManager = $this->wiki->services->get(ThemeManager::class);

        $param = $this->getService(PerformableArguments::class)->get('param');
        if (!empty($param)) {
            // pages of wikis installed before the rename still contain {{configuration param="wakka_name"}}
            if ($param === 'wakka_name') {
                $param = 'yeswiki_name';
            }
            switch ($param) {
                case 'yeswiki_version':
                case 'yeswiki_release':
                case 'root_page':
                case 'yeswiki_name':
                case 'base_url':
                case 'navigation_links':
                case 'meta_keywords':
                case 'meta_description':
                case 'favorite_theme':
                case 'favorite_style':
                case 'favorite_squelette':
                case 'default_language':
                case 'charset':
                    echo htmlentities($this->wiki->config[$param], ENT_QUOTES, YW_CHARSET);
                    break;
                case 'lang':
                    echo $GLOBALS['prefered_language'];
                    break;
                case 'theme_path':
                    $theme = $themeManager->getFavoriteTheme();
                    echo (is_dir('custom/themes/' . $theme)) ?
                        "custom/themes/$theme/" :
                        "themes/$theme/";
                    break;
                default:
                    break;
            }
        }
    }
}
