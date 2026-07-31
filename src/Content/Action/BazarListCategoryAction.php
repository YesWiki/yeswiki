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
class BazarListCategoryAction extends YesWikiAction implements RegisteredAction
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
        if (!function_exists('compareFieldsByPosition')) {
            // tri par ordre desire
            function compareFieldsByPosition($a, $b)
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

            $entriesTab = $entryManager->search(['queries' => $query, 'formsIds' => [$form_id]]);

            $entries['info_res'] = '';
            $entries['pager_links'] = '';
            $entries['fiches'] = [];
            foreach ($entriesTab as $entry) {
                // pour les checkbox, on crée une fiche par case cochée pour apparaitre é différents endroits
                $tabcheckbox = explode(',', $entry[$id]);
                foreach ($tabcheckbox as $value) {
                    // on sauve les multiples valeurs pour les retablir é l'affichage
                    $multiplecheckbox[$entry['tag']] = $entry[$id];
                    $entry[$id] = $value;

                    // permet de voir la fiche
                    $entry['html'] = renderEntryView(0, $entry);
                    // lien de suppression visible pour le super admin
                    if (userIsAllowedTo('supp_fiche', $entry['owner'])) {
                        $entry['lien_suppression'] = '<a class="modalbox" href="'
                            . $this->getService(UrlFormatter::class)->href('deletepage', $entry['tag'], 'incoming=' . urlencode($this->getService(UrlFormatter::class)->href())) . '"></a>' . "\n";
                    }
                    if (userIsAllowedTo('modif_fiche', $entry['owner'])) {
                        $entry['lien_edition'] = '<a class="BAZ_lien_modifier" href="' . $this->getService(UrlFormatter::class)->href('edit', $entry['tag']) . '"></a>' . "\n";
                    }
                    $entry['lien_voir_titre'] = '<a class="BAZ_lien_modifier" href="' . $this->getService(UrlFormatter::class)->href('', $entry['tag']) . '">' . ($entry['title'] ?? $entry['bf_titre'] ?? $entry['tag']) . '</a>' . "\n";
                    $entry['lien_voir'] = '<a class="BAZ_lien_modifier" href="' . $this->getService(UrlFormatter::class)->href('', $entry['tag']) . '"></a>' . "\n";
                    $entries['fiches'][] = $entry;
                }
            }
            // trie par liste choisie
            usort($entries['fiches'], 'compareFieldsByPosition');

            $listvalues = listValues($list);
            $currentlabel = 'this is an impossible label';
            $categoryEntries = [];
            $output = '';
            $first = true;
            foreach ($entries['fiches'] as $entry) {
                $entry['multipleid'] = htmlspecialchars(trim(str_replace('/', '', $entry[$id])) . $entry['tag']);
                if ($currentlabel !== $entry[$id]) {
                    if (!$first) {
                        if (is_array($categoryEntries) && count($categoryEntries) > 0) {
                            $output .= $this->getService(TemplateEngine::class)->renderSafely("@core/$template", $categoryEntries);
                        }
                        // it's not the first time in the loop so we must close previously opened div
                        $output .= '</div>' . "\n";
                        $categoryEntries = [];
                    } else {
                        $first = false;
                    }
                    $output .= '<h3 class="collapsed yeswiki-list-category" '
                        . 'data-target="#collapse_' . htmlspecialchars(trim(str_replace('/', '', $entry[$id])))
                        . '" data-toggle="collapse"><svg class="yw-icon" aria-hidden="true"><use href="src/assets/icons.svg#chevron-right"/></svg> '
                        . (empty($listvalues['label'][$entry[$id]]) ? _t('BAZ_NOT_CATEGORIZED') : $listvalues['label'][$entry[$id]]) . '</h3>
                        <div id="collapse_' . htmlspecialchars(trim(str_replace('/', '', $entry[$id]))) . '" class="collapse">';
                }
                $currentlabel = $entry[$id];
                // on rétablit les valeurs multiples
                if (isset($multiplecheckbox[$entry['tag']])) {
                    $entry[$id] = $multiplecheckbox[$entry['tag']];
                }
                $categoryEntries['fiches'][] = $entry;
            }
            // last results
            if (is_array($categoryEntries) && count($categoryEntries) > 0) {
                $output .= $this->getService(TemplateEngine::class)->renderSafely("@core/$template", $categoryEntries);
            }
            // it's not the first time in the loop so we must close previously opened div
            $output .= '</div>' . "\n";
            echo $output;

            $_GET['query'] = $query;
        }
    }
}
