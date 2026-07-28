<?php

namespace YesWiki\Search\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;

/**
 * `{{moteurrecherche}}` -- converted from the procedural actions/moteurrecherche.php by ticket 06.
 *
 * The body still prints rather than returning, so it runs inside an output buffer in its
 * own method: that is what the old runFileInBuffer() did, and it keeps any early `return;`
 * in the body from discarding output.
 */
class MoteurrechercheAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'moteurrecherche';
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
        // on choisit le template utilisé
        $template = $this->wiki->GetParameter('template');
        if (empty($template)) {
            $template = 'moteurrecherche_basic.tpl.html';
        }

        // on peut ajouter des classes à la classe par défaut .searchform
        $searchelements['class'] = ($this->wiki->GetParameter('class') ? 'form-search ' . $this->wiki->GetParameter('class') : 'form-search');
        $searchelements['btnclass'] = ($this->wiki->GetParameter('btnclass') ? ' ' . $this->wiki->GetParameter('btnclass') : '');
        $searchelements['iconclass'] = ($this->wiki->GetParameter('iconclass') ? ' ' . $this->wiki->GetParameter('iconclass') : '');

        // on peut changer l'url de recherche
        $searchelements['url'] = ($this->wiki->GetParameter('url') ? $this->wiki->GetParameter('url') : $this->wiki->href('show', 'RechercheTexte'));

        // si une recherche a été effectuée, on garde les mots clés
        $searchelements['phrase'] = htmlspecialchars(isset($_REQUEST['phrase']) ? $_REQUEST['phrase'] : '');

        echo $this->wiki->render("@core/$template", $searchelements);
    }
}
