<?php

namespace YesWiki\Render\Action;
use YesWiki\Render\Service\TemplateHelperService;
use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;

class ButtondropdownAction extends YesWikiAction implements RegisteredAction
{
    /** `{{buttondropdown}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'buttondropdown';
    }

    public function run()
    {
        ob_start();

        // texte genere a l'interieur du bouton
        $text = $this->arguments['text'] ?? '';

        // titre au survol du bouton et dans la boite modale associée
        $title = $this->arguments['title'] ?? '';

        // mettre un petit triangle pour indiquer que c'est déroulant
        $caret = $this->arguments['caret'];
        if ($caret != '0') {
            $caret = '1';
        }

        $icon = $this->getService(TemplateHelperService::class)->formatIconHtml($this->arguments['icon']);
        if (!empty($icon) && !empty($text)) {
            $icon = $icon . ' ';
        }

        // classe css supplémentaire l'ensemble du
        $class = $this->arguments['class'] ?? '';

        // classe css supplémentaire pour changer le look des boutons (e.g. "yw-btn--primary")
        $btnclass = $this->arguments['btnclass'] ?? '';
        $btnclass = 'yw-btn ' . $btnclass;

        $nobtn = $this->arguments['nobtn'] ?? '';
        if (!empty($nobtn) && $nobtn == '1') {
            $btnclass = str_replace('yw-btn ', '', $btnclass);
        }

        if ($this->check_end_elem('buttondropdown')) {
            $encodedtitle = htmlentities($title, ENT_COMPAT, YW_CHARSET);
            echo '<div class="yw-dropdown' . (!empty($class) ? ' ' . $class : '') . '"> <!-- start of buttondropdown -->
            <button class="' . $btnclass . ' yw-collapse-toggle" data-yw-dropdown-toggle aria-label="' . $encodedtitle . '" title="' . $encodedtitle . '">
            ' . $icon . $text . (($caret == '1') ? ' <span class="yw-dropdown__caret"></span>' : '') . '
            </button>' . "\n";
        } else {
            echo $this->generate_error_msg('buttondropdown');
        }
        $buttondropdown = ob_get_contents();
        ob_end_clean();

        return $buttondropdown;
    }

    public function end(): string
    {
        return "\n</div> <!-- end of buttondropdown -->\n";
    }
}
