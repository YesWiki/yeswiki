<?php

namespace YesWiki\Content\Action;

use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\LinkRenderer;
use YesWiki\Render\Service\MarkdownFormatterService;

/**
 * `{{trail}}` -- converted from the procedural actions/trail.php by ticket 06.
 *
 * The body still prints rather than returning, so it runs inside an output buffer in its
 * own method: that is what the old runFileInBuffer() did, and it keeps any early `return;`
 * in the body from discarding output.
 */
class TrailAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'trail';
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
        /*
        trail.php : Permet d'afficher des liens "Page Suivante" "Sommaire" "Page Precedente" dans une page
        */

        /*
        * Cette action permet de lier des pages entre elle via une page contenant la liste
        * ordonnées de ces pages. L'action affiche des liens de navigation permettant de
        * passer é la page suivante ou précédente ou de revenir au sommaire.
        *
        * @param toc string nom de la page contenant la liste ordonnée des pages é liées entre elles
        */

        /* La page sommaire doit contenir une liste de pages. Le premier mot de chaque élément
           de la liste doit étre le nom d'une page du wiki, donc un mot wiki ou un lien force
           exemple de page sommaire:

        ===Sommaire===

         IntroductionAuProjet : présentation du projet.
         [[AnalyseProjet Analyse]] : analyse des besoins
           -BesoinDesUtilisateurs
           -ContraintesTechniques
         OutilsEtNormes

        Texte texte  texte texte texte texte texte texte texte texte
        texte texte texte texte texte texte texte texte texte texte texte
        texte texte texte texte texte texte texte texte texte texte texte texte

        */

        // echo $this->getService(MarkdownFormatterService::class)->format("===Action Trail===");
        $sommaire = $this->wiki->GetParameter('toc');
        if (!$sommaire) {
            echo '<div class="alert alert-danger"><strong>' . _t('ERROR_ACTION_TRAIL') . '</strong> : ' . _t('INDICATE_THE_PARAMETER_TOC') . '.</div>' . "\n";
        } else {
            // chargement de la page sommaire
            $tocPage = $this->getService(PageManager::class)->getOne($sommaire);
            if (!$tocPage) {
                echo '<div class="alert alert-danger"><strong>' . _t('ERROR_ACTION_TRAIL') . '</strong> : ' . _t('THE_PAGE') . ' ', $this->getService(LinkRenderer::class)->link($sommaire), ' ' . _t('DOESNT_EXIST') . ' !</div>' . "\n";

                return;
            }
            // analyse de la page sommaire pour récupérer la liste des pages
            // recuperation de la liste
            if (preg_match_all("/\n[\t ]+(.*)/", $tocPage['body'], $tocListe)) {
                // analyse de chaque ligne de la liste pour recupérer la page cible
                $currentPageIndex = null;
                foreach ($tocListe[1] as $line) {
                    // suppression d'un signe de liste eventuel
                    $line = trim(preg_replace("/^([[:alnum:]]+\)|-)/", '', $line));
                    // recuperation du 1er mot
                    $line = preg_replace("/^(\[\[.*\]\]|" . WN_CHAR . "+)\s*(.*)$/", '$1', $line);
                    // ajout a la liste des pages si le 1er mot est un lien force ou un mot wiki
                    if (preg_match("/\[\[.*\]\]/", $line, $match) | $this->getService(UrlFormatter::class)->isWikiName($line)) {
                        $pages[] = $line;
                        // regarde si la page ajoute a la liste est la page courante
                        if (strcasecmp($this->wiki->GetPageTag(), $line) == 0) {
                            $currentPageIndex = count($pages) - 1;
                        } else {  // traite le cas des lien force
                            if (preg_match("/\[\[(.*:)?" . $this->wiki->GetPageTag() . "(\s.*)?\]\]$/", $line)) {
                                $currentPageIndex = count($pages) - 1;
                            }
                        }
                    }
                }// foreach
            }
            // ecriture des liens Page Précedente/sommaire/page suivante
            if ($currentPageIndex > 0) {
                $PrevPage = $pages[$currentPageIndex - 1];
                $btnPrev = '<li class="previous"><span class="trail_button">' . $this->getService(MarkdownFormatterService::class)->format("&larr; $PrevPage") . "</span></li>\n";
            } else {
                $btnPrev = '';
            }
            $btnTOC = '<li><span class="trail_button">' . $this->getService(LinkRenderer::class)->linkToPage($sommaire) . "</span></li>\n";
            if ($currentPageIndex < (count($pages) - 1)) {
                $NextPage = $pages[$currentPageIndex + 1];
                $btnNext = '<li class="next"><span class="trail_button">' . $this->getService(MarkdownFormatterService::class)->format("$NextPage &rarr;") . "</span></li>\n";
            } else {
                $btnNext = '';
            }
            echo '<ul class="pager">' . "\n" . $btnPrev . $btnTOC . $btnNext . '</ul>' . "\n";
        }
    }
}
