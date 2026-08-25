<?php

namespace YesWiki\Render\Action;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\PerformableArguments;
use YesWiki\Render\Service\GraphicalElementState;
use YesWiki\Render\Service\Performer;
use YesWiki\Render\Service\TemplateHelperService;

/** `{{end}}` -- converted from the procedural actions/end.php by ticket 06. */
class EndAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'end';
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
        require_once YESWIKI_PROGRAM_DIR . '/src/YesWikiPerformable.php';

        $elem = $this->getService(PerformableArguments::class)->get('elem');
        if (empty($elem)) {
            echo '<div class="yw-alert yw-alert--danger"><strong>' . _t('TEMPLATE_ACTION_END') . '</strong> : ' . _t('TEMPLATE_ELEM_PARAMETER_REQUIRED') . '.</div>' . "\n";

            return;
        }
        $pagetag = $this->getService(PageContext::class)->getTag();
        $body = PageBody::content($this->getService(PageContext::class)->getPage()['body'] ?? []);

        $closes = $this->getService(GraphicalElementState::class)->closesElement(
            $pagetag,
            $elem,
            fn (): bool => $this->getService(TemplateHelperService::class)->checkGraphicalElements($elem, $pagetag, $body)
        );

        if ($closes || in_array($elem, ['tab', 'tabs'], true)) {
            echo $this->getService(Performer::class)->run($elem, 'action', [], true);
        }
    }
}
