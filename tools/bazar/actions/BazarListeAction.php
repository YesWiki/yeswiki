<?php

use YesWiki\Bazar\Exception\ParsingMultipleException;
use YesWiki\Bazar\Service\BazarListService;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\SearchManager;
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
        $icon = $_GET['icon'] ?? $arg['icon'] ?? $_GET['icons'] ?? $arg['icons'] ?? null;
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
        $color = $_GET['color'] ?? $_GET['colors'] ?? $arg['colors'] ?? $arg['color'] ?? null;
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
        if ($template) {
            $template = htmlspecialchars($template);
        }
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

        if (in_array($template, ['list', 'card', 'map-and-table', 'table', 'timeline'])) {
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

        // Ordre du tri (asc ou desc)
        $ordre = $_GET['ordre'] ?? $arg['ordre'] ?? ((empty($arg['champ']) && $agendaMode) ? 'desc' : 'asc');
        // Champ du formulaire utilisé pour le tri
        $champ = $_GET['champ'] ?? $arg['champ'] ?? (($agendaMode) ? 'bf_date_debut_evenement' : 'bf_titre');

        $vSearchManager = $this->getService(SearchManager::class);

        $vKeywords = $vSearchManager->aggregateKeywords($arg['keywords'] ?? null, $_REQUEST['q'] ?? null, $_REQUEST['keywords'] ?? null);

        return [
        	////////////////////
	        // USER PARAMETERS
        	//////////////////	        
        	
            // SELECTION DES FICHES
            
            // sélectionner seulement les fiches d'un utilisateur
            'user' => $arg['user'] ?? ((isset($arg['filteruserasowner']) && $arg['filteruserasowner'] == 'true') ?
                $this->getService(AuthController::class)->getLoggedUserName() : null),
            
            // identifiant du formulaire (plusieures valeurs possibles, séparées par des virgules)
            'idtypeannonce' => $arg['id'] ?? $arg['idtypeannonce'] ?? $_GET['id'] ?? null,

            // to be able to refresh cache for external json
            'refresh' => $this->formatBoolean($_GET, false, 'refresh'),
            
            // Paramètres pour une requete specifique
            'queries' => $vSearchManager->parseQuery($vSearchManager->aggregateQueries($arg, $_GET)),
            // filtrer sur des mots clefs
            'keywords' => $vKeywords,
            // filtrer les resultats sur une periode données si une date est indiquée
            'dateMin' => $this->formatDateMin($_GET['period'] ?? $arg['period'] ?? null),
            
            // Afficher les fiches dans un ordre aléatoire
            'random' => $this->formatBoolean($arg, false, 'random'),
            // Ordre du tri (asc ou desc)
            'ordre' => $ordre,
            // Champ du formulaire utilisé pour le tri
            'champ' => $champ,
            // les tris disponibles par le bouton "Trier par"
            'sortfields' => $this->formatArray($_GET['sortfields'] ?? $arg['sortfields'] ?? []),
            'sortfieldstitles' => $this->formatArray($_GET['sortfieldstitles'] ?? $arg['sortfieldstitles'] ?? []),
            
            // Nombre maximal de résultats à afficher
            'nb' => $arg['nb'] ?? null,
                        
            // Nombre de résultats affichés pour la pagination (permet d'activer la pagination)
            'pagination' => $arg['pagination'] ?? null,
            
            // Transfere les valeurs d'un champs vers un autre, afin de correspondre dans un template
            'correspondance' => $arg['correspondance'] ?? null,
            
            // paramètre de tri des fiches sur une date (en gardant la retrocompatibilité avec le paramètre agenda)
            'agenda' => $arg['datefilter'] ?? $arg['agenda'] ?? null,
            'datefilter' => $arg['datefilter'] ?? $arg['agenda'] ?? null,

            // Dynamic mean the template will be rendered from the front end in order to improve UX and perf
            // Only few bazar templates have been converted to javascript
            'dynamic' => $dynamic,
            'displayfields' => $displayFields,
            
            // fields that will be used in dynamic views
            'necessary_fields' => $this->formatArray($_GET['necessaryfields'] ?? $arg['necessaryfields'] ?? $_GET['necessary_fields'] ?? $arg['necessary_fields'] ?? []),
            // get comments , reactions and metadatas with entry
            'extrafields' => $this->formatBoolean($arg, false, 'extrafields'),
            
            // AFFICHAGE
            
            // Template pour l'affichage de la liste de fiches
            'template' => (!empty($template)) ? $template : $this->params->get('default_bazar_template'),
            
            // ajout du footer pour gérer la fiche (modifier, droits, etc,.. )            
            'barregestion' => $this->formatBoolean($arg, true, 'barregestion'),
            
            // bouton de réinitialisation des filtres
            'resetfiltersbutton' => $this->formatBoolean($arg, false, 'resetfiltersbutton'),
            
            // ajout des options pour exporter les fiches
            'showexportbuttons' => $this->formatBoolean($arg, false, 'showexportbuttons'),

            // Affiche le formulaire de recherche en haut
            'search' => $search,
            'searchfields' => $searchfields,
            
            // Affiche le nombre de fiche en haut
            'shownumentries' => $this->formatBoolean($arg, false, 'shownumentries'),
            
            // affichage du nombre de fiches trouvées par les filtres
            'filtersresultnb' => $this->formatBoolean($arg, true, 'filtersresultnb'),
            
            // classe css a ajouter en rendu des templates liste
            'class' => $arg['class'] ?? '',
            
            // Number of columns for card template
            'nbcol' => $arg['nbcol'] ?? null,

            // FACETTES
            // Identifiants des champs utilisés pour les facettes
            // Plusieures valeurs possibles, séparées par des virgules, "all" pour toutes les facettes possibles
            // Exemple : {{bazarliste groups="bf_ce_titre,bf_ce_pays,etc."..}}
            'groups' => $this->formatArray($_GET['groups'] ?? $arg['groups'] ?? null),
            // Titres des boite de facettes. Plusieures valeurs possibles, séparées par des virgules
            // Exemple : {{bazarliste titles="Titre,Pays,etc."..}}
            'titles' => $this->formatArray($_GET['groupstitles'] ?? $arg['groupstitles']??$_GET['titles'] ?? $arg['titles'] ?? null),
            
            // déplier toutes les facettes
            'groupsexpanded' => $this->formatBoolean($_GET['groupsexpanded'] ?? $arg, true, 'groupsexpanded'),
            
            'groupicons' => $this->formatArray($arg['groupicons'] ?? null),
            
            // ajout d'un filtre pour chercher du texte dans les resultats pour les facettes
            'filtertext' => $this->formatBoolean($arg, false, 'filtertext'),
           
            // facette à gauche ou à droite
            'filterposition' => $_GET['filterposition'] ?? $arg['filterposition'] ?? 'right',
            // largeur colonne facettes
            'filtercolsize' => $_GET['filtercolsize'] ?? $arg['filtercolsize'] ?? '3',
            
            // ICONS
            
            // Prefixe des classes CSS utilisees pour la carto et calendrier
            'iconprefix' => isset($_GET['iconprefix']) ? trim($_GET['iconprefix']) : (isset($arg['iconprefix']) ? trim($arg['iconprefix']) : ($this->params->get('baz_marker_icon_prefix') ?? '')),
            // Champ utilise pour les icones des marqueurs
            'iconfield' => $iconField,
            // icone des marqueurs
            'icon' => $icon,
            
            // COLORS
            
            // Champ utilise pour la couleur des marqueurs
            'colorfield' => $colorField,
            // couleur des marqueurs
            'color' => $color,
            
            //////////////////////
	        // SYSTEM PARAMETERS
        	////////////////////
        	
        	// Iframe ?
            'isInIframe' => testUrlInIframe(),        	
        ];
    }

    public function run()
    {
        $this->debug = ($this->wiki->GetConfigValue('debug') == 'yes');

        // If the template is a map or a calendar, call the dedicated action so that
        // arguments can be properly formatted. The second first condition prevents infinite loops
        if (
            self::specialActionFromTemplate($this->arguments['template'], 'BAZARCARTO_TEMPLATES')
            && (!isset($this->arguments['calledBy']) || !in_array($this->arguments['calledBy'], ['BazarCartoAction', 'BazarTableAction']))
        ) {
            return $this->callAction('bazarcarto', $this->arguments);
        } elseif (
            self::specialActionFromTemplate($this->arguments['template'], 'CALENDRIER_TEMPLATES')
            && (!isset($this->arguments['calledBy']) || $this->arguments['calledBy'] !== 'CalendrierAction')
        ) {
            return $this->callAction('calendrier', $this->arguments);
        } elseif (
            self::specialActionFromTemplate($this->arguments['template'], 'BAZARTABLE_TEMPLATES')
            && (!isset($this->arguments['calledBy']) || $this->arguments['calledBy'] !== 'BazarTableAction')
        ) {
            // Ceci est bancal : bazarliste action appelle bazartable action qui rappelle une deuxieme bazarliste action.
            // L'objectif est de formater les arguments correctement pour les tables.
            // Ainsi on créé une action bazartable qui créée une deuxieme bazarliste action avec les paramètres correctement formatés pour les tables
            // Cela a des effets de bords :
            // ex : si la bazarliste action utilise des parametres de $_REQUEST pour définir ses arguments, alors ces arguments peuvent être dupliqués dans la deuxième bazarliste action créée
            // ie :
            //		- 1ere bazarliste action : bazartable ($arg + $_REQUEST)
            // 		- bazartable action : bazarliste ($arg + $_REQUEST)
            // 		- 2eme bazarliste action : bazarliste (($arg + $_REQUEST) + $_REQUEST)

            return $this->callAction('bazartable', $this->arguments);
        }

        $bazarListService = $this->getService(BazarListService::class);
        $vForms = $bazarListService->getForms($this->arguments);

        if ($this->arguments['dynamic']) {
            if (isset($this->arguments['zoom'])) {
                $this->arguments['zoom'] = intval($this->arguments['zoom']);
            }
            $currentUser = $this->getService(AuthController::class)->getLoggedUser();

            return $this->render("@bazar/entries/index-dynamic-templates/{$this->arguments['template']}.twig", [
                'param' => $this->arguments, // DEPRECATED but still there for retro-compatibility: use params (plural)
                'params' => $this->arguments,
                'keywords' => $this->arguments['keywords'],
                'forms' => $vForms,
                'currentUserName' => empty($currentUser['name']) ? '' : $currentUser['name']
            ]);
        } else {
            $entries = $bazarListService->getEntries($this->arguments, $vForms);
            $filters = $bazarListService->getFilters($this->arguments, $entries, $vForms, true);

            // To handle multiple bazarlist in a same page, we need a specific ID per bazarlist
            // We use a global variable to count the number of bazarliste action run on this page
            if (!isset($GLOBALS['_BAZAR_']['nbbazarliste'])) {
                $GLOBALS['_BAZAR_']['nbbazarliste'] = 0;
            }
            $GLOBALS['_BAZAR_']['nbbazarliste']++;
            $this->arguments['nbbazarliste'] = $GLOBALS['_BAZAR_']['nbbazarliste'];

            // TODO put in all bazar templates
           
            $this->wiki->AddJavascriptFile('tools/bazar/presentation/javascripts/bazar.js', true, true);

            return $this->render('@bazar/entries/index.twig', [
                'listId' => $GLOBALS['_BAZAR_']['nbbazarliste'],
                'filters' => $filters,
                'entries' => $entries,
                'renderedEntries' => $this->renderEntries($entries, $filters, $vForms),
                'numEntries' => count($entries),
                'param' => $this->arguments,
                'params' => $this->arguments,
                // Search form parameters
                'keywords' => $this->arguments['keywords'],
                'pageTag' => $this->wiki->getPageTag(),
                'forms' => $vForms,
                //'formId' => $this->arguments['idtypeannonce'][0] ?? null,
                'facette' => $_GET['facette'] ?? null,
            ]);
        }
    }

    private function renderEntries($entries, $filters = [], $pForms = ''): string
    {
        $showNumEntries = count($entries) === 0 || $this->arguments['shownumentries'];
        $templateName = $this->arguments['template'];
        if (strpos($templateName, '.html') === false && strpos($templateName, '.twig') === false) {
            $templateName = $templateName . '.tpl.html';
            $this->arguments['template'] = $templateName;
        }
        $data = [];
        $data['fiches'] = $entries;
        $data['info_res'] = $showNumEntries ? '<div class="alert alert-info">' . _t('BAZ_IL_Y_A') . ' ' . count($data['fiches']) . ' ' . (count($data['fiches']) <= 1 ? _t('BAZ_FICHE') : _t('BAZ_FICHES')) . '</div>' : '';
        $data['params'] = $this->arguments;
        $data['pager_links'] = '';
        $data['filters'] = $filters; // in case some template need it, like gogocarto
        $data['forms'] = $pForms;

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
}
