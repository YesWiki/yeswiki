<?php

namespace YesWiki\Search\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\PerformableArguments;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\TemplateEngine;

/**
 * `{{moteurrecherche}}` -- converted from the procedural actions/moteurrecherche.php by ticket 06.
 *
 * The body still prints rather than returning, so it runs inside an output buffer in its
 * own method: that is what the old runFileInBuffer() did, and it keeps any early `return;`
 * in the body from discarding output.
 */
class SearchEngineAction extends YesWikiAction implements RegisteredAction
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
        $template = $this->getService(PerformableArguments::class)->get('template');
        if (empty($template)) {
            $template = 'moteurrecherche_basic.twig';
        }

        // on peut ajouter des classes à la classe par défaut .searchform
        $searchelements['class'] = ($this->getService(PerformableArguments::class)->get('class') ? 'form-search ' . $this->getService(PerformableArguments::class)->get('class') : 'form-search');
        $searchelements['btnclass'] = ($this->getService(PerformableArguments::class)->get('btnclass') ? ' ' . $this->getService(PerformableArguments::class)->get('btnclass') : '');
        $searchelements['iconclass'] = ($this->getService(PerformableArguments::class)->get('iconclass') ? ' ' . $this->getService(PerformableArguments::class)->get('iconclass') : '');

        // on peut changer l'url de recherche
        $searchelements['url'] = ($this->getService(PerformableArguments::class)->get('url') ? $this->getService(PerformableArguments::class)->get('url') : $this->getService(UrlFormatter::class)->href('show', 'RechercheTexte'));

        // si une recherche a été effectuée, on garde les mots clés
        $searchelements['phrase'] = htmlspecialchars(isset($_REQUEST['phrase']) ? $_REQUEST['phrase'] : '');

        echo $this->getService(TemplateEngine::class)->renderSafely("@core/$template", $searchelements);
    }
}
