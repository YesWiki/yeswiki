<?php

namespace YesWiki\Render\Action;

use YesWiki\Content\Service\LinkTracker;
use YesWiki\Render\Service\TemplateHelperService;
use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;

/**
 * `{{button}}` -- converted from the procedural actions/button.php by ticket 06.
 *
 * The body still prints rather than returning, so it runs inside an output buffer in its
 * own method: that is what the old runFileInBuffer() did, and it keeps any early `return;`
 * in the body from discarding output.
 */
class ButtonAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'button';
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

        // adresse vers quoi le bouton pointe
        $link = $this->wiki->GetParameter('link');

        // extration du nom de 'root_page' si nécessaire
        if ($link == 'config/root_page') {
            $link = $this->wiki->config['root_page'];
            $this->wiki->setParameter('link', $link);
        }

        $linkParts = $this->wiki->extractLinkParts($link);
        if ($linkParts) {
            $link = $this->wiki->href($linkParts['method'], $linkParts['tag'], $linkParts['params']);
            $this->wiki->services->get(LinkTracker::class)->forceAddIfNotIncluded($linkParts['tag']);
        }
        // change short yeswiki urls in real links
        $link = $this->wiki->generateLink($link);

        // texte genere a l'interieur du bouton
        $text = $this->wiki->GetParameter('text');

        // titre au survol du bouton et dans la boite modale associée
        $title = $this->wiki->GetParameter('title');

        // icone du bouton
        $icon = $this->wiki->services->get(TemplateHelperService::class)->formatIconHtml($this->wiki->GetParameter('icon'));
        if (!empty($icon) && !empty($text)) {
            $icon = $icon . ' ';
        }

        // classe css supplémentaire pour changer le look
        $class = $this->wiki->GetParameter('class');
        $class .= (!empty($class) ? ' ' : '') . 'yw-btn';

        $datasize = '';
        if (preg_match('/\bmodalbox\b/i', $class)) {
            // if modalbox, set the big size
            $datasize .= 'modal-lg';
        }

        $nobtn = $this->wiki->GetParameter('nobtn');
        if (!empty($nobtn) && $nobtn == '1') {
            // remove all the yw-btn or yw-btn--* css class
            $class = preg_replace('/\byw-btn(?:--\w+)?\b/i', '', $class);
            // remove unneeded spaces
            $class = preg_replace('/(^\s*)|(\s*$)/', '', preg_replace('/\s{2,}/', ' ', $class));
        }

        $hideIfNoAccess = $this->wiki->GetParameter('hideifnoaccess');
        if ($hideIfNoAccess == 'true' && isset($linkParts['tag']) && !$GLOBALS['wiki']->HasAccess('read', $linkParts['tag'])) {
            echo '';
        } elseif (empty($link)) {
            echo '<div class="yw-alert yw-alert--danger"><strong>' . _t('TEMPLATE_ACTION_BUTTON') . '</strong> : ' . _t('TEMPLATE_LINK_PARAMETER_REQUIRED') . '.</div>' . "\n";
        } else {
            $btn = '<a'
                . (!empty($link) ? ' href="' . $link . '"' : '')
                . (!empty($class) ? ' class="' . $class . '"' : '')
                . (!empty($datasize) ? ' data-size="' . $datasize . '"' : '')
                . ((!empty($datasize) && empty($linkParts)) ? ' data-iframe="1"' : '') // use iframe for external links in modalbox
                . (!empty($title) ? ' title="' . htmlentities($title, ENT_COMPAT, YW_CHARSET) . '"' : '');
            $btn .= '>' . $icon . (!empty($text) ? htmlentities($text, ENT_COMPAT, YW_CHARSET) : '') . '</a>';
            echo $btn;
        }
    }
}
