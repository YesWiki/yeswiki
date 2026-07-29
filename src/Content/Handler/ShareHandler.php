<?php

namespace YesWiki\Content\Handler;

use YesWiki\Content\Service\QrCodeService;
use YesWiki\Core\YesWikiHandler;
use YesWiki\Kernel\Performable\RegisteredHandler;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\TemplateEngine;

/**
 * `/PageName/share` -- converted from the procedural handlers/page/share.php by ticket 06.
 */
class ShareHandler extends YesWikiHandler implements RegisteredHandler
{
    public static function performableName(): string
    {
        return 'share';
    }

    public function run(): string
    {
        ob_start();
        try {
            $this->emit();
        } catch (\Throwable $t) {
            // handlers commonly end in exit()/redirect, which throw; keep what was already
            // printed and close the buffer either way (see ticket 06)
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

        // merged from handlers/page/ShareHandler__.php (ticket 06: core does not hook itself)
        // creation et affichage QRcode du lien de page
        $url = $this->getService(UrlFormatter::class)->href();
        $cacheImage = 'cache/qrcode-' . $this->getService(PageContext::class)->getTag() . '-url.svg';
        $this->getService(QrCodeService::class)->generateToFile($url, $cacheImage);
        $html = '<img class="right" src="' . $cacheImage . '" title="' . _t('QR_CODE_PAGE') . '" alt="' . $url . '" />' . "\n";

        // Agrégation du QRcode dans le buffer du handler share
        $plugin_output_new = preg_replace(
            '/<div class="page">/',
            '<div class="page">' . "\n" . $html . "\n",
            $plugin_output_new
        );

        return $plugin_output_new . (string)ob_get_clean();
    }

    private function emit(): void
    {
        $url = $this->getService(UrlFormatter::class)->href();
        $tag = $this->getService(PageContext::class)->getTag();
        $shareText = _t('TEMPLATE_SHARE_MUST_READ') . $url;
        // icon classes come from the bundled FontAwesome 5 Free brands set
        $targets = [
            ['href' => 'https://www.facebook.com/sharer/sharer.php?u=' . urlencode($url), 'icon' => 'fab fa-facebook-f', 'label' => _t('TEMPLATE_SHARE_FACEBOOK')],
            ['href' => 'https://twitter.com/intent/tweet?url=' . urlencode($url) . '&text=' . urlencode($tag), 'icon' => 'fab fa-twitter', 'label' => _t('TEMPLATE_SHARE_TWITTER')],
            ['href' => 'https://mastodonshare.com/?url=' . urlencode($url) . '&text=' . urlencode($tag), 'icon' => 'fab fa-mastodon', 'label' => _t('TEMPLATE_SHARE_MASTODON')],
            ['href' => 'https://www.linkedin.com/sharing/share-offsite/?url=' . urlencode($url), 'icon' => 'fab fa-linkedin-in', 'label' => _t('TEMPLATE_SHARE_LINKEDIN')],
            ['href' => 'https://wa.me/?text=' . urlencode($shareText), 'icon' => 'fab fa-whatsapp', 'label' => _t('TEMPLATE_SHARE_WHATSAPP')],
            ['href' => 'https://t.me/share/url?url=' . urlencode($url) . '&text=' . urlencode($tag), 'icon' => 'fab fa-telegram-plane', 'label' => _t('TEMPLATE_SHARE_TELEGRAM')],
            // the by-mail share is the wiki's own sendmail handler, not an external network
            ['href' => $this->getService(UrlFormatter::class)->href('sendmail'), 'icon' => 'fas fa-envelope', 'label' => _t('TEMPLATE_SHARE_MAIL')],
        ];
        $html = '<div class="yw-share-buttons">' . "\n";
        foreach ($targets as $target) {
            $external = str_starts_with($target['href'], 'https://');
            $html .= '<a href="' . htmlspecialchars($target['href'], ENT_QUOTES) . '"'
                . ' class="bouton_share" title="' . htmlspecialchars($target['label'], ENT_QUOTES) . '"'
                . ' aria-label="' . htmlspecialchars($target['label'], ENT_QUOTES) . '"'
                . ($external ? ' target="_blank" rel="noopener noreferrer"' : '')
                . '><i class="' . $target['icon'] . ' fa-2x" aria-hidden="true"></i></a>' . "\n";
        }
        $html .= '</div>' . "\n";
        $html .= '<br /><br />' . "\n";
        $html .= '<div class="yw-alert yw-alert--info">' . _t('TEMPLATE_SHARE_INCLUDE_CODE') . '</div>' . "\n";
        $html .= "<pre id=\"htmlsharecode\">\n";
        $html .= htmlentities('<iframe class="auto-resize" width="100%" scroll="no" frameborder="0" src="' . $this->getService(UrlFormatter::class)->href('iframe') . '"></iframe>') . "\n";
        $html .= "</pre>\n";
        $html .= '
        <div class="checkbox">
          <label>
            <input id="checkbox-share" type="checkbox" onclick="document.getElementById(\'htmlsharecode\').textContent = this.checked ? document.getElementById(\'htmlsharecode\').textContent.replace(\'&share=1\', \'\') : document.getElementById(\'htmlsharecode\').textContent.replace(\'' . $this->getService(UrlFormatter::class)->href('iframe') . '\', \'' . $this->getService(UrlFormatter::class)->href('iframe') . '&share=1\');"> ' . _t('TEMPLATE_ADD_SHARE_BUTTON') . '
          </label>
        </div>
        <div class="checkbox">
          <label>
            <input id="checkbox-edit" type="checkbox" onclick="document.getElementById(\'htmlsharecode\').textContent = this.checked ? document.getElementById(\'htmlsharecode\').textContent.replace(\'\&edit\=1\', \'\') : document.getElementById(\'htmlsharecode\').textContent.replace(\'' . $this->getService(UrlFormatter::class)->href('iframe') . '\', \'' . $this->getService(UrlFormatter::class)->href('iframe') . '&edit=1\');"> ' . _t('TEMPLATE_ADD_EDIT_BAR') . '
          </label>
        </div>
        ';

        // si l'on est dans une requete ajax, pas besoin de titre, et pas besoin de charger tout le html
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo '<div class="page">' . "\n" . $html . "\n" . '</div>';
        } else {
            echo $this->getService(TemplateEngine::class)->header();
            echo "<div class=\"page\">\n<h2>" . _t('TEMPLATE_SEE_SHARING_OPTIONS') . ' ' . $this->getService(PageContext::class)->getTag() . "</h2>\n$html\n<hr class=\"hr_clear\" />\n</div>\n";
            echo $this->getService(TemplateEngine::class)->footer();
        }
    }
}
