<?php

namespace YesWiki\Content\Action;

use YesWiki\Content\Service\EntryManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\AssetRegistry;
use YesWiki\Kernel\Service\PerformableArguments;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\TemplateEngine;

/**
 * `{{entrylistcategory}}` -- converted from the procedural actions/bazarlistecategorie.php by ticket 06.
 */
class EntryListCategoryAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'entrylistcategory';
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
        $entryManager = $this->getService(EntryManager::class);

        $this->getService(AssetRegistry::class)->addJsFile('javascripts/bazar.js', true, true);

        if (!function_exists('compareFieldsByPosition')) {
            function compareFieldsByPosition($a, $b)
            {
                if ($GLOBALS['order'] == 'desc') {
                    return strnatcasecmp($b[$GLOBALS['field']], $a[$GLOBALS['field']]);
                }

                return strnatcasecmp($a[$GLOBALS['field']], $b[$GLOBALS['field']]);
            }
        }

        $form_id = $this->getService(PerformableArguments::class)->get('id');
        if (empty($form_id)) {
            $form_id = 'toutes';
        }
        $GLOBALS['order'] = $this->getService(PerformableArguments::class)->get('order');
        if (empty($GLOBALS['order'])) {
            $GLOBAL['order'] = 'asc';
        }

        $template = $this->getService(PerformableArguments::class)->get('template');
        $template = $this->getService(TemplateEngine::class)->hasTemplate("@core/$template") ? $template : '';
        if (empty($template)) {
            $template = $GLOBALS['yeswikiServices']->get(\YesWiki\Kernel\Service\RuntimeConfig::class)['default_bazar_template'];
        }

        $id = $this->getService(PerformableArguments::class)->get('id');
        if (empty($id)) {
            throw new \Exception('Error action entrylistcategory: parameter "id" missing.');
        }
        $GLOBALS['field'] = $id;

        $list = $this->getService(PerformableArguments::class)->get('list');
        if (empty($list)) {
            echo '<div class="alert alert-danger">Error action entrylistcategory: parameter "list" missing.</div>';
        } else {
            if (isset($_GET['query'])) {
                $query = $_GET['query'];
            } else {
                $query = '';
            }
            unset($_GET['query']);

            $entriesTab = $entryManager->search(['queries' => $query, 'formsIds' => [$form_id]]);

            $entries['resultsInfo'] = '';
            $entries['pager_links'] = '';
            $entries['entries'] = [];
            foreach ($entriesTab as $entry) {
                $tabcheckbox = explode(',', $entry[$id]);
                foreach ($tabcheckbox as $value) {
                    $multiplecheckbox[$entry['tag']] = $entry[$id];
                    $entry[$id] = $value;

                    $entry['html'] = renderEntryView(0, $entry);

                    if (userIsAllowedTo('supp_fiche', $entry['owner'])) {
                        $entry['lien_suppression'] = '<a class="modalbox" href="'
                            . $this->getService(UrlFormatter::class)->href('deletepage', $entry['tag'], 'incoming=' . urlencode($this->getService(UrlFormatter::class)->href())) . '"></a>' . "\n";
                    }
                    if (userIsAllowedTo('modif_fiche', $entry['owner'])) {
                        $entry['lien_edition'] = '<a class="BAZ_lien_modifier" href="' . $this->getService(UrlFormatter::class)->href('edit', $entry['tag']) . '"></a>' . "\n";
                    }
                    $entry['lien_voir_titre'] = '<a class="BAZ_lien_modifier" href="' . $this->getService(UrlFormatter::class)->href('', $entry['tag']) . '">' . ($entry['title'] ?? $entry['bf_titre'] ?? $entry['tag']) . '</a>' . "\n";
                    $entry['lien_voir'] = '<a class="BAZ_lien_modifier" href="' . $this->getService(UrlFormatter::class)->href('', $entry['tag']) . '"></a>' . "\n";
                    $entries['entries'][] = $entry;
                }
            }

            usort($entries['entries'], 'compareFieldsByPosition');

            $listvalues = listValues($list);
            $currentlabel = 'this is an impossible label';
            $categoryEntries = [];
            $output = '';
            $first = true;
            foreach ($entries['entries'] as $entry) {
                $entry['multipleid'] = htmlspecialchars(trim(str_replace('/', '', $entry[$id])) . $entry['tag']);
                if ($currentlabel !== $entry[$id]) {
                    if (!$first) {
                        if (is_array($categoryEntries) && count($categoryEntries) > 0) {
                            $output .= $this->getService(TemplateEngine::class)->renderSafely("@core/$template", $categoryEntries);
                        }

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

                if (isset($multiplecheckbox[$entry['tag']])) {
                    $entry[$id] = $multiplecheckbox[$entry['tag']];
                }
                $categoryEntries['entries'][] = $entry;
            }

            if (is_array($categoryEntries) && count($categoryEntries) > 0) {
                $output .= $this->getService(TemplateEngine::class)->renderSafely("@core/$template", $categoryEntries);
            }

            $output .= '</div>' . "\n";
            echo $output;

            $_GET['query'] = $query;
        }
    }
}
