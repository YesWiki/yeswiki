<?php

namespace YesWiki\Content\Action;

use YesWiki\Content\Service\EntryManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\AssetsManager;
use YesWiki\Kernel\Service\PerformableArguments;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\TemplateEngine;

/**
 * `{{bazarlistecategorie}}` -- converted from the procedural actions/bazarlistecategorie.php by ticket 06.
 *
 * The body still prints rather than returning, so it runs inside an output buffer in its
 * own method: that is what the old runFileInBuffer() did, and it keeps any early `return;`
 * in the body from discarding output.
 */
class BazarlistecategorieAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'bazarlistecategorie';
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
        /**
         * bazarlistecategorie : programme affichant les fiches du bazar catégorisées par les champs liste
         * sous forme de liste accordeon (ou autre template).
         */
        $entryManager = $this->getService(EntryManager::class);

        $this->getService(AssetsManager::class)->AddJavascriptFile('javascripts/bazar.js', true, true);

        // initialisation de la fonction de tri , inspiré par http://php.net/manual/fr/function.usort.php
        if (!function_exists('champCompare')) {
            // tri par ordre desire
            function champCompare($a, $b)
            {
                if ($GLOBALS['ordre'] == 'desc') {
                    return strnatcasecmp($b[$GLOBALS['champ']], $a[$GLOBALS['champ']]);
                }

                return strnatcasecmp($a[$GLOBALS['champ']], $b[$GLOBALS['champ']]);
            }
        }

        $form_id = $this->getService(PerformableArguments::class)->get('idtypeannonce');
        if (empty($form_id)) {
            $form_id = 'toutes';
        }
        $GLOBALS['ordre'] = $this->getService(PerformableArguments::class)->get('ordre');
        if (empty($GLOBALS['ordre'])) {
            $GLOBAL['ordre'] = 'asc';
        }

        $template = $this->getService(PerformableArguments::class)->get('template');
        $template = $this->getService(TemplateEngine::class)->hasTemplate("@core/$template") ? $template : '';
        if (empty($template)) {
            $template = $GLOBALS['yeswikiServices']->get(\YesWiki\Kernel\Service\RuntimeConfig::class)['default_bazar_template'];
        }

        // identifiant de la base de donnée pour la liste
        $id = $this->getService(PerformableArguments::class)->get('id');
        if (empty($id)) {
            throw new \Exception('Error action bazarlistecategorie: parameter "id" missing.');
        }
        $GLOBALS['champ'] = $id;

        // NomWiki de la liste
        $list = $this->getService(PerformableArguments::class)->get('list');
        if (empty($list)) {
            echo '<div class="alert alert-danger">Error action bazarlistecategorie: parameter "list" missing.</div>';
        } else {
            // on recupere les parameres pour une requete specifique
            if (isset($_GET['query'])) {
                $query = $_GET['query'];
            } else {
                $query = '';
            }
            unset($_GET['query']);

            $tabfiches = $entryManager->search(['queries' => $query, 'formsIds' => [$form_id]]);

            $fiches['info_res'] = '';
            $fiches['pager_links'] = '';
            $fiches['fiches'] = [];
            foreach ($tabfiches as $fiche) {
                // pour les checkbox, on crée une fiche par case cochée pour apparaitre é différents endroits
                $tabcheckbox = explode(',', $fiche[$id]);
                foreach ($tabcheckbox as $value) {
                    // on sauve les multiples valeurs pour les retablir é l'affichage
                    $multiplecheckbox[$fiche['tag']] = $fiche[$id];
                    $fiche[$id] = $value;

                    // permet de voir la fiche
                    $fiche['html'] = baz_voir_fiche(0, $fiche);
                    // lien de suppression visible pour le super admin
                    if (baz_a_le_droit('supp_fiche', $fiche['owner'])) {
                        $fiche['lien_suppression'] = '<a class="modalbox" href="'
                            . $this->getService(UrlFormatter::class)->href('deletepage', $fiche['tag'], 'incoming=' . urlencode($this->getService(UrlFormatter::class)->href())) . '"></a>' . "\n";
                    }
                    if (baz_a_le_droit('modif_fiche', $fiche['owner'])) {
                        $fiche['lien_edition'] = '<a class="BAZ_lien_modifier" href="' . $this->getService(UrlFormatter::class)->href('edit', $fiche['tag']) . '"></a>' . "\n";
                    }
                    $fiche['lien_voir_titre'] = '<a class="BAZ_lien_modifier" href="' . $this->getService(UrlFormatter::class)->href('', $fiche['tag']) . '">' . ($fiche['title'] ?? $fiche['bf_titre'] ?? $fiche['tag']) . '</a>' . "\n";
                    $fiche['lien_voir'] = '<a class="BAZ_lien_modifier" href="' . $this->getService(UrlFormatter::class)->href('', $fiche['tag']) . '"></a>' . "\n";
                    $fiches['fiches'][] = $fiche;
                }
            }
            // trie par liste choisie
            usort($fiches['fiches'], 'champCompare');

            $listvalues = baz_valeurs_liste($list);
            $currentlabel = 'this is an impossible label';
            $fichescat = [];
            $output = '';
            $first = true;
            foreach ($fiches['fiches'] as $fiche) {
                $fiche['multipleid'] = htmlspecialchars(trim(str_replace('/', '', $fiche[$id])) . $fiche['tag']);
                if ($currentlabel !== $fiche[$id]) {
                    if (!$first) {
                        if (is_array($fichescat) && count($fichescat) > 0) {
                            $output .= $this->getService(TemplateEngine::class)->renderSafely("@core/$template", $fichescat);
                        }
                        // it's not the first time in the loop so we must close previously opened div
                        $output .= '</div>' . "\n";
                        $fichescat = [];
                    } else {
                        $first = false;
                    }
                    $output .= '<h3 class="collapsed yeswiki-list-category" '
                        . 'data-target="#collapse_' . htmlspecialchars(trim(str_replace('/', '', $fiche[$id])))
                        . '" data-toggle="collapse"><svg class="yw-icon" aria-hidden="true"><use href="src/assets/icons.svg#chevron-right"/></svg> '
                        . (empty($listvalues['label'][$fiche[$id]]) ? _t('BAZ_NOT_CATEGORIZED') : $listvalues['label'][$fiche[$id]]) . '</h3>
                        <div id="collapse_' . htmlspecialchars(trim(str_replace('/', '', $fiche[$id]))) . '" class="collapse">';
                }
                $currentlabel = $fiche[$id];
                // on rétablit les valeurs multiples
                if (isset($multiplecheckbox[$fiche['tag']])) {
                    $fiche[$id] = $multiplecheckbox[$fiche['tag']];
                }
                $fichescat['fiches'][] = $fiche;
            }
            // last results
            if (is_array($fichescat) && count($fichescat) > 0) {
                $output .= $this->getService(TemplateEngine::class)->renderSafely("@core/$template", $fichescat);
            }
            // it's not the first time in the loop so we must close previously opened div
            $output .= '</div>' . "\n";
            echo $output;

            $_GET['query'] = $query;
        }
    }
}
