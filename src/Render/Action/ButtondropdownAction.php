<?php

namespace YesWiki\Render\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Render\Service\TemplateHelperService;

class ButtondropdownAction extends YesWikiAction implements RegisteredAction
{
    /** `{{buttondropdown}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'buttondropdown';
    }

    /**
     * @return string opening markup for the dropdown, or the unclosed-element error message
     */
    public function run()
    {
        $text = $this->arguments['text'] ?? '';

        $title = $this->arguments['title'] ?? '';

        $caret = $this->arguments['caret'];
        if ($caret != '0') {
            $caret = '1';
        }

        $icon = $this->getService(TemplateHelperService::class)->formatIconHtml($this->arguments['icon']);
        if (!empty($icon) && !empty($text)) {
            $icon = $icon . ' ';
        }

        $class = $this->arguments['class'] ?? '';

        $btnclass = $this->arguments['btnclass'] ?? '';
        $btnclass = 'yw-btn ' . $btnclass;

        $nobtn = $this->arguments['nobtn'] ?? '';
        if (!empty($nobtn) && $nobtn == '1') {
            $btnclass = str_replace('yw-btn ', '', $btnclass);
        }

        if (!$this->check_end_elem('buttondropdown')) {
            return $this->generate_error_msg('buttondropdown');
        }

        $encodedtitle = htmlentities($title, ENT_COMPAT, YW_CHARSET);

        return '<div class="yw-dropdown' . (!empty($class) ? ' ' . $class : '') . '"> <!-- start of buttondropdown -->
            <button class="' . $btnclass . ' yw-collapse-toggle" data-yw-dropdown-toggle aria-label="' . $encodedtitle . '" title="' . $encodedtitle . '">
            ' . $icon . $text . (($caret == '1') ? ' <span class="yw-dropdown__caret"></span>' : '') . '
            </button>' . "\n";
    }

    public function end(): string
    {
        return "\n</div> <!-- end of buttondropdown -->\n";
    }
}
