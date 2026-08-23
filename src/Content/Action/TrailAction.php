<?php

namespace YesWiki\Content\Action;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Component\Setting;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\PerformableArguments;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\LinkRenderer;
use YesWiki\Render\Service\MarkdownFormatterService;

/** `{{trail}}` -- converted from the procedural actions/trail.php by ticket 06. */
class TrailAction extends YesWikiAction implements RegisteredAction, ProvidesComponents
{
    public static function performableName(): string
    {
        return 'trail';
    }

    public function components(): array
    {
        return [
            Component::for('trail')
                ->category(Category::Navigation)
                ->label(_t('AB_advanced_action_trail_label'))
                ->icon('sitemap')
                ->previewHeight('200px')
                ->settings(
                    Setting::page('toc')
                        ->label(_t('AB_advanced_action_trail_toc_label'))
                        ->required(),
                ),
        ];
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
        /*
        * Cette action permet de lier des pages entre elle via une page contenant la liste
        * ordonnées de ces pages. L'action affiche des liens de navigation permettant de
        * passer é la page suivante ou précédente ou de revenir au sommaire.
        *
        * @param toc string nom de la page contenant la liste ordonnée des pages é liées entre elles
        */

        $sommaire = $this->getService(PerformableArguments::class)->get('toc');
        if (!$sommaire) {
            echo '<div class="alert alert-danger"><strong>' . _t('ERROR_ACTION_TRAIL') . '</strong> : ' . _t('INDICATE_THE_PARAMETER_TOC') . '.</div>' . "\n";
        } else {
            $tocPage = $this->getService(PageManager::class)->getOne($sommaire);
            if (!$tocPage) {
                echo '<div class="alert alert-danger"><strong>' . _t('ERROR_ACTION_TRAIL') . '</strong> : ' . _t('THE_PAGE') . ' ', $this->getService(LinkRenderer::class)->link($sommaire), ' ' . _t('DOESNT_EXIST') . ' !</div>' . "\n";

                return;
            }
            $pages = [];
            $currentPageIndex = null;
            if (preg_match_all("/\n[\t ]+(.*)/", PageBody::content($tocPage['body']), $tocListe)) {
                foreach ($tocListe[1] as $line) {
                    $line = trim((string)preg_replace("/^([[:alnum:]]+\)|-)/", '', $line));
                    $line = (string)preg_replace("/^(\[\[.*\]\]|" . WN_CHAR . "+)\s*(.*)$/", '$1', $line);
                    if (preg_match("/\[\[.*\]\]/", $line, $match) | $this->getService(UrlFormatter::class)->isWikiName($line)) {
                        $pages[] = $line;
                        if (strcasecmp($this->getService(PageContext::class)->getTag(), $line) == 0) {
                            $currentPageIndex = count($pages) - 1;
                        } else {
                            if (preg_match("/\[\[(.*:)?" . $this->getService(PageContext::class)->getTag() . "(\s.*)?\]\]$/", $line)) {
                                $currentPageIndex = count($pages) - 1;
                            }
                        }
                    }
                }
            }
            if ($currentPageIndex === null) {
                return;
            }

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
