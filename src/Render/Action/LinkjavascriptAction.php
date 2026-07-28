<?php

namespace YesWiki\Render\Action;

use Symfony\Component\Security\Csrf\CsrfTokenManager;
use YesWiki\Render\Service\ThemeManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;

/**
 * `{{linkjavascript}}` -- converted from the procedural actions/linkjavascript.php by ticket 06.
 *
 * The body still prints rather than returning, so it runs inside an output buffer in its
 * own method: that is what the old runFileInBuffer() did, and it keeps any early `return;`
 * in the body from discarding output.
 */
class LinkjavascriptAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'linkjavascript';
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
        $yeswiki_javascripts = "\n" . '  <!-- javascripts -->' . "\n";

        // ticket 16: jQuery and Bootstrap JS are no longer loaded globally; ticket 26
        // removed the last jQuery island (the old form-builder admin page) — core ships
        // no jQuery at all.

        // on récupère le bon chemin pour le theme
        if ($themeManager->getUseFallbackTheme()) {
            $repertoire = 'themes/' . $themeManager->getFavoriteTheme() . '/javascripts';
        } else {
            $jsDir = 'themes/' . $themeManager->getFavoriteTheme() . '/javascripts';
            if (is_dir('custom/' . $jsDir)) {
                $repertoire = 'custom/' . $jsDir;
            } else {
                $repertoire = $jsDir;
            }
        }

        // on scanne les javascripts du theme
        $yeswikijs = false;
        $dir = (is_dir($repertoire) ? opendir($repertoire) : false);
        while ($dir && ($file = readdir($dir)) !== false) {
            if (substr($file, -3, 3) == '.js') {
                $scripts[] = $repertoire . '/' . $file;
                if (strstr($file, 'yeswiki.') || strstr($file, 'yw.')) {
                    // le theme contient deja le js de yeswiki
                    $yeswikijs = true;
                }
            }
        }
        if (is_dir($repertoire)) {
            closedir($dir);
        }

        // on trie les javascripts du theme par ordre alphabéthique et on les insere
        if (isset($scripts) && is_array($scripts)) {
            asort($scripts);
            foreach ($scripts as $val) {
                $this->wiki->addJavascriptFile($val);
            }
        }

        // s'il n'y a pas le javascript de yeswiki dans le theme, on le rajoute
        if (!$yeswikijs) {
            $this->wiki->addJavascriptFile('javascripts/yeswiki-base.js');
        }

        // htmx + yw-* core design system (ADR-0004/0005): loaded on every page so
        // core interactions can rely on them without each surface opting in itself
        $this->wiki->addJavascriptFile('javascripts/vendor/htmx/htmx.min.js');
        $this->wiki->addJavascriptFile('javascripts/yw-core.js');
        $this->wiki->addJavascriptFile('javascripts/yw-datatable.js');
        $this->wiki->addJavascriptFile('javascripts/yw-autocomplete.js');

        // ajoute la méthode pour les traductions js
        $this->wiki->addJavascriptFile('javascripts/yeswiki-base-no-defer.js', true);

        // add javascript files which are included in the custom javascript directory
        $customJsPath = 'custom/javascripts';
        $customJsDir = is_dir($customJsPath) ? opendir($customJsPath) : false;
        while ($customJsDir && ($file = readdir($customJsDir)) !== false) {
            if (substr($file, -3, 3) == '.js') {
                $this->wiki->addJavascriptFile($customJsPath . '/' . $file);
            }
        }

        // si quelque chose est passée dans la variable globale pour le javascript, on l'intègre
        $yeswiki_javascripts .= isset($GLOBALS['js']) ? $GLOBALS['js'] : '';

        // on vide la variable globale pour le javascript
        $GLOBALS['js'] = '';

        $wikiprops = [
            'locale' => $GLOBALS['prefered_language'],
            'timezone' => date_default_timezone_get(),
            'baseUrl' => $this->wiki->config['base_url'],
            'pageTag' => $this->wiki->getPageTag(),
            'isDebugEnabled' => ($this->wiki->GetConfigValue('debug') ? 'true' : 'false'),
            'antiCsrfToken' => $this->wiki->services->get(CsrfTokenManager::class)->getToken('main')->getValue(),
        ];

        // Globale wiki variable
        echo "<script>
            var wiki = {
                ...((typeof wiki !== 'undefined') ? wiki : null),
                ..." . json_encode($wikiprops) . ",
                ...{
                    lang: {
                        ...((typeof wiki !== 'undefined') ? (wiki.lang ?? null) : null),
                        ..." . json_encode($GLOBALS['translations_js'] ?? null) . '
                    }
                },
                ...{
                	minSearchKeywordLength : ' . (isset($this->wiki->config['min_search_keyword_length']) ? intval($this->wiki->config['min_search_keyword_length']) : MIN_SEARCH_KEYWORD_LENGTH) . '
                }
            };
        </script>';

        // on affiche
        echo $yeswiki_javascripts;

        // This GLOBALS is populated from AddCSS and AddCSSFile, but already flush in <HEAD> by actions/linkstyle__.php
        // we add it at the end to catch other calls to ADDCSSFile ou AddCSS
        if (isset($GLOBALS['css']) && !empty($GLOBALS['css'])) {
            echo $GLOBALS['css'];
        }
    }
}
