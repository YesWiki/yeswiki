<?php

namespace YesWiki\Render\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\PerformableArguments;
use YesWiki\Kernel\Service\UrlFormatter;

/** `{{doubleclick}}` -- converted from the procedural actions/doubleclic.php by ticket 06. */
class DoubleClickAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'doubleclick';
    }

    public function run(): string
    {
        ob_start();
        try {
            $this->emit();
        } catch (\Throwable $t) {
            $this->output .= (string)ob_get_clean();

            throw $t;
        }

        return (string)ob_get_clean();
    }

    private function emit(): void
    {
        $page = $this->getService(PerformableArguments::class)->get('page');
        $isIframe = $this->getService(PerformableArguments::class)->get('iframe') && (!isset($_GET['iframelinks']) or $_GET['iframelinks'] != '0');
        if ($this->getService(PageContext::class)->getMethod() == 'show' && $this->getService(AclService::class)->hasAccess('write', $page)) {
            $method = $isIframe ? 'editiframe' : 'edit';

            if (!empty($page)) {
                echo 'ondblclick="document.location=\'' . $this->getService(UrlFormatter::class)->href($method, $page) . '\';" ';
            } else {
                echo 'ondblclick="document.location=\'' . $this->getService(UrlFormatter::class)->href($method) . '\';" ';
            }
        }
    }
}
