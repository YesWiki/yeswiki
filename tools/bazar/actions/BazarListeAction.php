<?php

use YesWiki\Bazar\Controller\EntryController;
use YesWiki\Bazar\Exception\ParsingMultipleException;
use YesWiki\Bazar\Service\BazarListService;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\Exception\TemplateNotFound;
use YesWiki\Core\YesWikiAction;

class BazarListeAction extends YesWikiAction
{
    protected const BAZARCARTO_TEMPLATES = ['map', 'gogomap', 'gogocarto', 'map-and-table']; // liste des templates sans .twig ni .tpl.html
    protected const BAZARTABLE_TEMPLATES = ['table', 'map-and-table']; // liste des templates sans .twig ni .tpl.html
    protected const CALENDRIER_TEMPLATES = ['calendar']; // liste des templates sans .twig ni .tpl.html

    protected $debug;

    public function formatArguments($arg)
    {
        $entryManager = $this->getService(EntryManager::class);

        // ICONS FIELD
        $iconField = $_GET['iconfield'] ?? $arg['iconfield'] ?? null;

        // ICONS
        $icon = $_GET['icon'] ?? $arg['icon'] ?? null;
        $iconAlreadyDefined = ($icon == $this->params->get('baz_marker_icon') || is_array($icon));
        if (!$iconAlreadyDefined) {
            if (!empty($icon)) {
                try {
                    $tabparam = $entryManager->getMultipleParameters($icon, ',', '=');
                    if (count($tabparam) > 0 && !empty($iconField)) {
                        // on inverse cle et valeur, pour pouvoir les reprendre facilement dans la carto
                        foreach ($tabparam as $key => $data) {
                            $tabparam[$data] = $key;
                        }
                        $icon = $tabparam;
                    } else {
                        $icon = trim(array_values($tabparam)[0]);
                    }
                } catch (ParsingMultipleException $th) {
                    throw new Exception('action bazarliste : le paramètre icon est mal rempli.<br />Il doit être de la forme icon="nomIcone1=valeur1, nomIcone2=valeur2"<br/>(' . $th->getMessage() . ')');
                }
            } else {
                $icon = $this->params->get('baz_marker_icon');
            }
        }

        // COLORS FIELD
        $colorField = $_GET['colorfield'] ?? $arg['colorfield'] ?? null;

        // COLORS
        $color = $_GET['color'] ?? $arg['color'] ?? null;
        $colorAlreadyDefined = ($color == $this->params->get('baz_marker_color') || is_array($color));
        if (!$colorAlreadyDefined) {
            if (!empty($color)) {
                try {
                    $tabparam = $entryManager->getMultipleParameters($color, ',', '=');
                    if (count($tabparam) > 0 && !empty($colorField)) {
                        // on inverse cle et valeur, pour pouvoir les reprendre facilement dans la carto
                        foreach ($tabparam as $key => $data) {
                            $tabparam[$data] = $key;
                        }
                        $color = $tabparam;
                    } else {
                        $color = trim(array_values($tabparam)[0]);
                    }
                } catch (ParsingMultipleException $th) {
                    throw new Exception('action bazarliste : le paramètre color est mal rempli.<br />Il doit être de la forme color="couleur1=valeur1, couleur2=valeur2"<br/>(' . $th->getMessage() . ')');
                }
            } else {
                $color = $this->params->get('baz_marker_color');
            }
        }

        $template = $_GET['template'] ?? $arg['template'] ?? null;

        // Dynamic templates
        $dynamic = $this->formatBoolean($arg, false, 'dynamic');

        if (isset($arg['displayfields']) && is_array($arg['displayfields'])) { // with bazarcarto this method is run twice
            $displayFields = $arg['displayfields'];
        } else {
            $displayFields = [];
            foreach (explode(',', $arg['displayfields'] ?? '') as $field) {
                $values = explode('=', $field);
                if (count($values) == 2) {
                    $displayFields[$values[0]] = $values[1];
                }
            }
        }

        if (in_array($template, ['list', 'card', 'map-and-table', 'table'])) {
            $dynamic = true;
        }
        if ($dynamic && $template == 'liste_accordeon') {
            $template = 'list';
        }
        if ($dynamic && in_array($template, ['tableau.tpl.html', 'tableau'])) {
            $template = 'table';
        }
        $searchfields = $this->formatArray($arg['searchfields'] ?? null);
        $searchfields = empty($searchfields) ? ['bf_titre'] : $searchfields;
        // End dynamic

        $agendaMode = (!empty($arg['agenda']) || !empty($arg['datefilter']) || (is_string($template) && substr($template, 0, strlen('agenda')) == 'agenda'));

        // get form ids for ExternalBazarService
        // format id="4,https://example.com|6,7,https://example.com|6->8"
        $ids = $arg['id'] ?? $arg['idtypeannonce'] ?? $_GET['id'] ?? null;
        $externalIds = $this->getExternalUrlsFromIds(is_array($ids) ? implode(',', $ids) : $ids);
        $externalModeActivated = !empty(array_filter($externalIds, function ($externalId) {
            return !empty($externalId['url']);
        }));
        // format ids as standard
        $ids = array_values(array_map(function ($externalId) {
            return $externalId['id'];
        }, $externalIds));
        $ids = array_map('strip_tags', $ids); // filter xss

        // Only keep "true" and "dynamic" value, so we can still do if params.search in twig
        $search = !isset($arg['search'])
            ? null
            : (
                $arg['search'] === 'dynamic'
                ? $arg['search']
                : (
                    in_array($arg['search'], ['true', true, '1', 1], true)
                    ? 'true'
                    : null
                )
            );

        return [
            // SELECTION DES FICHES
            // identifiant du formulaire (plusieures valeurs possibles, séparées par des virgules)
            'idtypeannonce' => $ids,
            // external mode
            'externalModeActivated' => $externalModeActivated,
            'externalIds' => $externalIds,
            // to be able to refresh cache for external json
            'refresh' => $this->formatBoolean($_GET, false, 'refresh'),
            // Paramètres pour une requete specifique
            'query' => $this->getService(EntryController::class)->formatQuery($arg, $_GET),
            // filtrer les resultats sur une periode données si une date est indiquée
            'dateMin' => $this->formatDateMin($_GET['period'] ?? $arg['period'] ?? null),
            // sélectionner seulement les fiches d'un utilisateur
            'user' => $arg['user'] ?? ((isset($arg['filteruserasowner']) && $arg['filteruserasowner'] == 'true') ?
                $this->getService(AuthController::class)->getLoggedUserName() : null),
            // Ordre du tri (asc ou desc)
            'ordre' => $arg['ordre'] ?? ((empty($arg['champ']) && $agendaMode) ? 'desc' : 'asc'),
            // Champ du formulaire utilisé pour le tri
            'champ' => $arg['champ'] ?? (($agendaMode) ? 'bf_date_debut_evenement' : 'bf_titre'),
            // Nombre maximal de résultats à afficher
            'nb' => $arg['nb'] ?? null,
            // Nombre de résultats affichés pour la pagination (permet d'activer la pagination)
            'pagination' => $arg['pagination'] ?? null,
            // Afficher les fiches dans un ordre aléatoire
            'random' => $this->formatBoolean($arg, false, 'random'),
            // Transfere les valeurs d'un champs vers un autre, afin de correspondre dans un template
            'correspondance' => $arg['correspondance'] ?? null,
            // paramètre de tri des fiches sur une date (en gardant la retrocompatibilité avec le paramètre agenda)
            'agenda' => $arg['datefilter'] ?? $arg['agenda'] ?? null,
            'datefilter' => $arg['datefilter'] ?? $arg['agenda'] ?? null,

            // Dynamic mean the template will be rendered from the front end in order to improve UX and perf
            // Only few bazar templates have been converted to javascript
            'dynamic' => $dynamic,
            'displayfields' => $displayFields,
            // Number of columns for card template
            'nbcol' => $arg['nbcol'] ?? null,

            // AFFICHAGE
            // Template pour l'affichage de la liste de fiches
            'template' => (!empty($template)) ? $template : $this->params->get('default_bazar_template'),
            // classe css a ajouter en rendu des templates liste
            'class' => $arg['class'] ?? '',
            // ajout du footer pour gérer la fiche (modifier, droits, etc,.. )
            'barregestion' => $this->formatBoolean($arg, true, 'barregestion'),
            // ajout des options pour exporter les fiches
            'showexportbuttons' => $this->formatBoolean($arg, false, 'showexportbuttons'),
            // Affiche le formulaire de recherche en haut
            'search' => $search,
            'searchfields' => $searchfields,
            // Affiche le nombre de fiche en haut
            'shownumentries' => $this->formatBoolean($arg, false, 'shownumentries'),
            // Iframe ?
            'isInIframe' => testUrlInIframe(),

            // FACETTES
            // Identifiants des champs utilisés pour les facettes
            // Plusieures valeurs possibles, séparées par des virgules, "all" pour toutes les facettes possibles
            // Exemple : {{bazarliste groups="bf_ce_titre,bf_ce_pays,etc."..}}
            'groups' => $this->formatArray($_GET['groups'] ?? $arg['groups'] ?? null),
            // Titres des boite de facettes. Plusieures valeurs possibles, séparées par des virgules
            // Exemple : {{bazarliste titles="Titre,Pays,etc."..}}
            'titles' => $this->formatArray($_GET['titles'] ?? $arg['titles'] ?? null),
            'groupicons' => $this->formatArray($arg['groupicons'] ?? null),
            // ajout d'un filtre pour chercher du texte dans les resultats pour les facettes
            'filtertext' => $this->formatBoolean($arg, false, 'filtertext'),
            // facette à gauche ou à droite
            'filterposition' => $_GET['filterposition'] ?? $arg['filterposition'] ?? 'right',
            // largeur colonne facettes
            'filtercolsize' => $_GET['filtercolsize'] ?? $arg['filtercolsize'] ?? '3',
            // déplier toutes les facettes
            'groupsexpanded' => $this->formatBoolean($_GET['groupsexpanded'] ?? $arg, true, 'groupsexpanded'),
            // Prefixe des classes CSS utilisees pour la carto et calendrier
            'iconprefix' => isset($_GET['iconprefix']) ? trim($_GET['iconprefix']) : (isset($arg['iconprefix']) ? trim($arg['iconprefix']) : ($this->params->get('baz_marker_icon_prefix') ?? '')),
            // Champ utilise pour les icones des marqueurs
            'iconfield' => $iconField,
            // icone des marqueurs
            'icon' => $icon,
            // Champ utilise pour la couleur des marqueurs
            'colorfield' => $colorField,
            // couleur des marqueurs
            'color' => $color,
            // affichage du nombre de fiches trouvées par les filtres
            'filtersresultnb' => $this->formatBoolean($arg, true, 'filtersresultnb'),
            // bouton de réinitialisation des filtres
            'resetfiltersbutton' => $this->formatBoolean($arg, false, 'resetfiltersbutton'),
        ];
    }

    public function run()
    {
        $this->debug = ($this->wiki->GetConfigValue('debug') == 'yes');

        // If the template is a map or a calendar, call the dedicated action so that
        // arguments can be properly formatted. The second first condition prevents infinite loops
        if (self::specialActionFromTemplate($this->arguments['template'], 'BAZARCARTO_TEMPLATES')
                && (!isset($this->arguments['calledBy']) || !in_array($this->arguments['calledBy'], ['BazarCartoAction', 'BazarTableAction']))) {
            return $this->callAction('bazarcarto', $this->arguments);
        } elseif (self::specialActionFromTemplate($this->arguments['template'], 'CALENDRIER_TEMPLATES')
                && (!isset($this->arguments['calledBy']) || $this->arguments['calledBy'] !== 'CalendrierAction')) {
            return $this->callAction('calendrier', $this->arguments);
        } elseif (self::specialActionFromTemplate($this->arguments['template'], 'BAZARTABLE_TEMPLATES')
                && (!isset($this->arguments['calledBy']) || $this->arguments['calledBy'] !== 'BazarTableAction')) {
            return $this->callAction('bazartable', $this->arguments);
        }

        $bazarListService = $this->getService(BazarListService::class);
        $forms = $bazarListService->getForms($this->arguments);

        if ($this->arguments['dynamic']) {
            if (isset($this->arguments['zoom'])) {
                $this->arguments['zoom'] = intval($this->arguments['zoom']);
            }
            $currentUser = $this->getService(AuthController::class)->getLoggedUser();

            return $this->render("@bazar/entries/index-dynamic-templates/{$this->arguments['template']}.twig", [
                'params' => $this->arguments,
                'forms' => count($this->arguments['idtypeannonce']) === 0 ? $forms : '',
                'currentUserName' => empty($currentUser['name']) ? '' : $currentUser['name'],
            ]);
        } else {
            $entries = $bazarListService->getEntries($this->arguments, $forms);
            $filters = $bazarListService->getFilters($this->arguments, $entries, $forms);

            // backwardcompatibility, the structure of filters have changed in 06/2024
            $filters = array_reduce($filters, function ($carry, $filter) {
                $carry[$filter['propName']] = $filter;

                return $carry;
            }, []);

            // To handle multiple bazarlist in a same page, we need a specific ID per bazarlist
            // We use a global variable to count the number of bazarliste action run on this page
            if (!isset($GLOBALS['_BAZAR_']['nbbazarliste'])) {
                $GLOBALS['_BAZAR_']['nbbazarliste'] = 0;
            }
            $GLOBALS['_BAZAR_']['nbbazarliste']++;
            $this->arguments['nbbazarliste'] = $GLOBALS['_BAZAR_']['nbbazarliste'];

            // TODO put in all bazar templates
            $this->wiki->AddJavascriptFile('tools/bazar/presentation/javascripts/bazar.js');

            return $this->render('@bazar/entries/index.twig', [
                'listId' => $GLOBALS['_BAZAR_']['nbbazarliste'],
                'filters' => $filters,
                'renderedEntries' => $this->renderEntries($entries, $filters),
                'numEntries' => count($entries),
                'params' => $this->arguments,
                // Search form parameters
                'keywords' => $_GET['q'] ?? '',
                'pageTag' => $this->wiki->getPageTag(),
                'forms' => count($this->arguments['idtypeannonce']) === 0 ? $forms : '',
                'formId' => $this->arguments['idtypeannonce'][0] ?? null,
                'facette' => $_GET['facette'] ?? null,
            ]);
        }
    }

    private function renderEntries($entries, $filters = []): string
    {
        $showNumEntries = count($entries) === 0 || $this->arguments['shownumentries'];
        $templateName = $this->arguments['template'];
        if (strpos($templateName, '.html') === false && strpos($templateName, '.twig') === false) {
            $templateName = $templateName . '.tpl.html';
            $this->arguments['template'] = $templateName;
        }

        $data['fiches'] = $entries;
        $data['info_res'] = $showNumEntries ? '<div class="alert alert-info">' . _t('BAZ_IL_Y_A') . ' ' . count($data['fiches']) . ' ' . (count($data['fiches']) <= 1 ? _t('BAZ_FICHE') : _t('BAZ_FICHES')) . '</div>' : '';
        $data['param'] = $this->arguments;
        $data['pager_links'] = '';
        $data['filters'] = $filters; // in case some template need it, like gogocarto

        if (!empty($this->arguments['pagination']) && $this->arguments['pagination'] > 0) {
            require_once 'tools/bazar/libs/vendor/Pager/Pager.php';
            $tab = $_GET;
            unset($tab['wiki']);
            $pager = &Pager::factory([
                'mode' => $this->params->get('BAZ_MODE_DIVISION'),
                'perPage' => $this->arguments['pagination'],
                'delta' => $this->params->get('BAZ_DELTA'),
                'httpMethod' => 'GET',
                'path' => $this->wiki->getBaseUrl(),
                'extraVars' => $tab,
                'altNext' => _t('BAZ_SUIVANT'),
                'altPrev' => _t('BAZ_PRECEDENT'),
                'nextImg' => _t('BAZ_SUIVANT'),
                'prevImg' => _t('BAZ_PRECEDENT'),
                'itemData' => $data['fiches'],
                'curPageSpanPre' => '<li class="active"><a>',
                'curPageSpanPost' => '</a></li>',
                'useSessions' => false,
                'closeSession' => false,
            ]);
            $data['fiches'] = $pager->getPageData();
            $data['pager_links'] = '<div class="bazar_numero text-center"><ul class="pagination">' . $pager->links . '</ul></div>';
        }

        try {
            return $this->render("@bazar/{$templateName}", $data);
        } catch (TemplateNotFound $e) {
            return '<div class="alert alert-danger">' . $e->getMessage() . '</div>';
        }
    }

    private function formatDateMin($period)
    {
        switch ($period) {
            case 'day':
                $d = strtotime('-1 day');

                return date('Y-m-d H:i:s', $d);
            case 'week':
                $d = strtotime('-1 week');

                return date('Y-m-d H:i:s', $d);
            case 'month':
                $d = strtotime('-1 month');

                return date('Y-m-d H:i:s', $d);
        }
    }

    /* Method to test if the current template is associated to a specific bazar actions
     * @param $templateName string (ex. "map","map.tpl.html","map.twig")
     * @param $constName string name of the constant array containing the right template names
     *                          "BAZARCARTO_TEMPLATES" or "CALENDRIER_TEMPLATES"
     */
    public static function specialActionFromTemplate(string $templateName, string $constName): bool
    {
        switch ($constName) {
            case 'BAZARCARTO_TEMPLATES':
                $baseArray = self::BAZARCARTO_TEMPLATES;
                break;
            case 'CALENDRIER_TEMPLATES':
                $baseArray = self::CALENDRIER_TEMPLATES;
                break;
            case 'BAZARTABLE_TEMPLATES':
                $baseArray = self::BAZARTABLE_TEMPLATES;
                break;
            default:
                return false;
        }

        $templatesnames = [];
        foreach ($baseArray as $templateBaseName) {
            $templatesnames[] = $templateBaseName;
            $templatesnames[] = $templateBaseName . '.tpl.html';
            $templatesnames[] = $templateBaseName . '.twig';
        }

        return in_array($templateName, $templatesnames);
    }

    /**
     * extract external url from ids
     * get form ids for ExternalBazarService
     * format id="4,https://example.com|6,7,https://example.com|6->8".
     *
     * @param string $ids
     *
     * @return array
     */
    private function getExternalUrlsFromIds(?string $ids)
    {
        // external ids
        $externalIds = [];
        if (!is_null($ids) && preg_match_all('/(?:'
            . '(' // begin url capturing
            . '(?:(?:https?):\/\/)' // http or https protocol
            . '(?:\S+(?::\S*)?@|\d{1,3}(?:\.\d{1,3}){3}|(?:(?:[a-z\d\x{00a1}-\x{ffff}]+-?)*[a-z\d\x{00a1}-\x{ffff}]+)' // long part to catch url
            . '(?:\.(?:[a-z\d\x{00a1}-\x{ffff}]+-?)*[a-z\d\x{00a1}-\x{ffff}]+)*(?:\.[a-z\x{00a1}-\x{ffff}]{2,6})'
            . '|(?:localhost))' // or localhost
            . '(?::\d+)?' // optionnal port
            . '(?:[^\s^,^|]*)?)'
            . '\|' // following by a '|'
            . ')?' // 0 or 1 time - capturing
            . '([0-9]+)' // and a number
            . '(?:->([0-9]+))?' // optionnaly following by '->' and a number
            . '/u', $ids, $matches)) {
            foreach ($matches[0] as $index => $match) {
                $externalIds[] = [
                    'url' => $matches[1][$index] ?? '',
                    'id' => $matches[2][$index] ?? '',
                    'localFormId' => $matches[3][$index] ?? '',
                ];
            }
        }

        return $externalIds;
    }
}
