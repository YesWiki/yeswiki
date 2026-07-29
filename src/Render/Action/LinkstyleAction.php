<?php

namespace YesWiki\Render\Action;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\AssetsManager;
use YesWiki\Kernel\Service\PerformableArguments;
use YesWiki\Render\Service\ThemeManager;

/**
 * `{{linkstyle}}` -- converted from the procedural actions/linkstyle.php by ticket 06.
 *
 * The body still prints rather than returning, so it runs inside an output buffer in its
 * own method: that is what the old runFileInBuffer() did, and it keeps any early `return;`
 * in the body from discarding output.
 */
class LinkstyleAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'linkstyle';
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

        return $this->emitAfter((string)ob_get_clean());
    }

    /**
     * Ran as an after-callback until ticket 06 merged it in. Receives the rendered output
     * as $plugin_output_new -- the name the hooks already used -- because several rewrite
     * it rather than appending.
     */
    private function emitAfter(string $plugin_output_new): string
    {
        ob_start();

        // merged from actions/linkstyle__.php (ticket 06: core does not hook itself)
        // relocated from tools/tags/actions/linkstyle__.php (ticket 10): tags.css covers the
        // tag cloud and the rss-icon, loaded on every page like the rest of this file's styles.
        // (The epub export selection UI this also used to mention was already gone; wave-two
        // ticket 02 deleted the last of it -- the handler and the vendored PHPePub library.)
        $this->getService(AssetsManager::class)->AddCSSFile('styles/actions/tags-nuage.css');

        // relocated from tools/attach/actions/linkstyle__.php (ticket 17): attached-file display
        // (figures/captions/zoom), pointimage markers, background-image, embedded pdf/video sizing.
        // Trimmed of qq-uploader (dead, superseded by the /api/files upload route) and Bootstrap
        // .popover/jPlayer rules (converted to yw-popover / native <audio>/<video>).
        $this->getService(AssetsManager::class)->AddCSSFile('styles/actions/attach.css');

        // relocated from tools/bazar/actions/linkstyle__.php (ticket 24): styles for bazar entries,
        // the calendar view, and the google/leaflet map display.
        $this->getService(AssetsManager::class)->AddCSSFile('styles/bazar/bazar.css');

        // if exists and not empty, add the 'PageCss' yeswiki page's content to the styles
        // (the PageCss content must respect the CSS syntax). Inlined via AddCSS() rather than
        // linked from the /raw handler: raw.php serves text/plain, and browsers in standards
        // mode reject a <link rel="stylesheet"> whose response isn't text/css.
        $pageCss = $this->getService(PageManager::class)->getOne('PageCss');
        if ($pageCss && !empty($pageCss['body'])) {
            $this->getService(AssetsManager::class)->AddCSS(PageBody::content($pageCss['body']));
        }

        // This GLOBALS is populated from AddCSS and AddCSSFile, we add it at the end
        // Be careful to render Header AFTER rendering actions
        // do not use YesWiki:AddCSSFile(), YesWiki:LinkCSSFile() or YesWiki:AddCSS() in custom/linkstyle__.php (it will not work)
        if (isset($GLOBALS['css']) && !empty($GLOBALS['css'])) {
            echo $GLOBALS['css'];
            // empty $GLOBALS['css'] to fill it with other calls to AddCSS flushed in linkjavascript.php
            $GLOBALS['css'] = '';
        }

        return $plugin_output_new . (string)ob_get_clean();
    }

    private function emit(): void
    {
        $themeManager = $this->getService(ThemeManager::class);
        $favoriteStyle = $themeManager->getFavoriteStyle();
        // ticket 16: Bootstrap CSS is not loaded anymore — yw-core.css (base styles
        // + the yw-* design system, ADR-0004) is the only core-provided styling
        echo $this->getService(AssetsManager::class)->LinkCSSFile('styles/yw-core.css');

        // presets activated and path ?
        $favoritePreset = $themeManager->getFavoritePreset();
        $presetsActivated = !empty($themeManager->getTemplates()[$themeManager->getFavoriteTheme()]['presets']) && !empty($favoritePreset);
        if ($presetsActivated) {
            $custom_prefix = ThemeManager::CUSTOM_CSS_PRESETS_PREFIX;
            $presetIsCustom = (substr($favoritePreset, 0, strlen($custom_prefix)) == $custom_prefix);
            if (!$presetIsCustom) {
                $presetFile = 'themes/' . $themeManager->getFavoriteTheme() . '/presets/' . $favoritePreset;
            } else {
                $presetFile = ThemeManager::CUSTOM_CSS_PRESETS_PATH . '/' . substr($favoritePreset, strlen($custom_prefix));
            }
        }

        // on regarde dans quel dossier se trouve le theme
        $styleFile = 'themes/' . $themeManager->getFavoriteTheme() . '/styles/' . $favoriteStyle;
        if (file_exists('custom/' . $styleFile)) {
            $styleFile = 'custom/' . $styleFile;
        }
        if ($presetsActivated && !$presetIsCustom && file_exists('custom/' . $presetFile)) {
            $presetFile = 'custom/' . $presetFile;
        }

        // on ajoute le style css selectionne du theme
        if ($favoriteStyle != 'none') {
            if (substr($favoriteStyle, -4, 4) == '.css') {
                echo $this->getService(AssetsManager::class)->LinkCSSFile($styleFile, '', '', 'id="mainstyle"');
            }
        }

        // on ajoute le preset css selectionne du theme
        if (($favoriteStyle != 'none')
            && $presetsActivated
            && substr($favoritePreset, -4, 4) == '.css'
        ) {
            echo $this->getService(AssetsManager::class)->LinkCSSFile($presetFile);
        }

        // si l'action propose d'autres css a ajouter, on les ajoute
        $othercss = $this->getService(PerformableArguments::class)->get('othercss');
        if (!empty($othercss)) {
            $tabcss = explode(',', $othercss);
            foreach ($tabcss as $cssfile) {
                $style = 'themes/' . $themeManager->getFavoriteTheme() . '/styles/' . $cssfile;
                if (file_exists('custom/' . $style)) {
                    $style = 'custom/' . $style;
                }
                $this->getService(AssetsManager::class)->AddCSSFile($style);
            }
        }

        // add css files which are included in the custom styles directory
        $customCssPath = 'custom/styles';
        $customCssDir = is_dir($customCssPath) ? opendir($customCssPath) : false;
        while ($customCssDir && ($file = readdir($customCssDir)) !== false) {
            if (substr($file, -4, 4) == '.css') {
                $this->getService(AssetsManager::class)->AddCSSFile($customCssPath . '/' . $file);
            }
        }

        $favoriteBackgroundImage = $themeManager->getFavoriteBackgroundImage();
        // on ajoute aux css le background personnalise
        if (!empty($favoriteBackgroundImage)) {
            $imgextension = strtolower(substr($favoriteBackgroundImage, -4, 4));
            if ($imgextension == '.jpg') {
                $this->getService(AssetsManager::class)->AddCSS(<<<CSS
                    body {
                        background-image: url("files/backgrounds/$favoriteBackgroundImage");
                        background-repeat:no-repeat;
                        height:100%;
                        -webkit-background-size:cover;
                        -moz-background-size:cover;
                        -o-background-size:cover;
                        background-size:cover;
                        background-attachment:fixed;
                        background-clip:border-box;
                        background-origin:padding-box;
                        background-position:center center;
                    }
                CSS);
            } elseif ($imgextension == '.png') {
                $this->getService(AssetsManager::class)->AddCSS(<<<CSS
                    body {
                        background-image: url("files/backgrounds/$favoriteBackgroundImage");
                    }
                CSS);
            }
        }
    }
}
