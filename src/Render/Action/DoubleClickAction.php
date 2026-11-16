<?php

namespace YesWiki\Render\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\PerformableArguments;
use YesWiki\Render\Service\EditZone;

/** `{{doubleclick}}`: marks the page body as an edit zone, opened by a double-click or by the touch pencil. */
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
        $arguments = $this->getService(PerformableArguments::class);
        $isIframe = $arguments->get('iframe') && (!isset($_GET['iframelinks']) or $_GET['iframelinks'] != '0');
        echo ltrim($this->getService(EditZone::class)->attributes((string)$arguments->get('page'), (bool)$isIframe)) . ' ';
    }
}
