<?php

namespace YesWiki\Content\Handler;

use YesWiki\Content\Service\QrCodeService;
use YesWiki\Core\YesWikiHandler;
use YesWiki\Kernel\Performable\RegisteredHandler;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\UrlFormatter;

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
        $html = '<a href="http://www.facebook.com/sharer.php?u=' . urlencode($this->getService(UrlFormatter::class)->href()) . '&amp;t=' . urlencode($this->getService(PageContext::class)->getTag()) . '" title="' . _t('TEMPLATE_SHARE_FACEBOOK') . '" class="bouton_share"><img loading="lazy" src="presentation/images/facebook.png" width="32" height="32" alt="' . _t('TEMPLATE_SHARE_FACEBOOK') . '" /></a>' . "\n";
        $html .= '<a href="http://twitter.com/home?status=' . urlencode(_t('TEMPLATE_SHARE_MUST_READ') . $this->getService(UrlFormatter::class)->href()) . '" title="' . _t('TEMPLATE_SHARE_TWITTER') . '" class="bouton_share"><img loading="lazy" src="presentation/images/twitter.png" width="32" height="32" alt="' . _t('TEMPLATE_SHARE_TWITTER') . '" /></a>' . "\n";
        $html .= '<a href="http://www.netvibes.com/share?title=' . urlencode($this->getService(PageContext::class)->getTag()) . '&amp;url=' . urlencode($this->getService(UrlFormatter::class)->href()) . '" title="' . _t('TEMPLATE_SHARE_NETVIBES') . '" class="bouton_share"><img loading="lazy" src="presentation/images/netvibes.png" width="32" height="32" alt="' . _t('TEMPLATE_SHARE_NETVIBES') . '" /></a>' . "\n";
        $html .= '<a href="http://del.icio.us/post?url=' . urlencode($this->getService(UrlFormatter::class)->href()) . '&amp;title=' . urlencode($this->getService(PageContext::class)->getTag()) . '" title="' . _t('TEMPLATE_SHARE_DELICIOUS') . '" class="bouton_share"><img loading="lazy" src="presentation/images/delicious.png" width="32" height="32" alt="' . _t('TEMPLATE_SHARE_DELICIOUS') . '" /></a>' . "\n";
        $html .= '<a href="http://www.google.com/reader/link?title=' . urlencode($this->getService(PageContext::class)->getTag()) . '&amp;url=' . urlencode($this->getService(UrlFormatter::class)->href()) . '" title="' . _t('TEMPLATE_SHARE_GOOGLEREADER') . '" class="bouton_share"><img loading="lazy" src="presentation/images/google.png" width="32" height="32" alt="' . _t('TEMPLATE_SHARE_GOOGLEREADER') . '" /></a>' . "\n";
        $html .= '<a href="' . $this->getService(UrlFormatter::class)->href('mail') . '" title="' . _t('TEMPLATE_SHARE_MAIL') . '" class="bouton_share"><img loading="lazy" src="presentation/images/email.png" width="32" height="32" alt="' . _t('TEMPLATE_SHARE_MAIL') . '" /></a>' . "\n";
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
            echo mb_convert_encoding('<div class="page">' . "\n" . $html . "\n" . '<div>', 'UTF-8', 'ISO-8859-1');
        } else {
            echo $this->wiki->Header();
            echo "<div class=\"page\">\n<h2>" . _t('TEMPLATE_SEE_SHARING_OPTIONS') . ' ' . $this->getService(PageContext::class)->getTag() . "</h2>\n$html\n<hr class=\"hr_clear\" />\n</div>\n";
            echo $this->wiki->Footer();
        }
    }
}
