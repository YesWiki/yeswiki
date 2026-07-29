<?php

namespace YesWiki\Content\Action;

use YesWiki\Content\Exception\ParsingMultipleException;
use YesWiki\Content\Field\BazarField;
use YesWiki\Content\Field\CheckboxField;
use YesWiki\Content\Field\EmailField;
use YesWiki\Content\Field\EnumField;
use YesWiki\Content\Field\ImageField;
use YesWiki\Content\Field\MapField;
use YesWiki\Content\Service\BazarListService;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Kernel\Exception\TemplateNotFound;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\Paginator;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Search\Service\SearchManager;

class BazarListeAction extends YesWikiAction implements RegisteredAction
{
    /** `{{bazarliste}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'bazarliste';
    }

    protected const BAZARCARTO_TEMPLATES = ['map', 'gogomap', 'gogocarto', 'map-and-table']; // liste des templates sans .twig ni .tpl.html
    protected const BAZARTABLE_TEMPLATES = ['table', 'map-and-table']; // liste des templates sans .twig ni .tpl.html
    protected const CALENDRIER_TEMPLATES = ['calendar']; // liste des templates sans .twig ni .tpl.html

    protected $debug;

    public function formatArguments($arg)
    {
        $entryManager = $this->getService(EntryManager::class);

        $get = $this->getRequest()->query;
        // ICONS FIELD
        $iconField = $get->get('iconfield') ?? $arg['iconfield'] ?? null;

        // ICONS
        $icon = $get->get('icon') ?? $arg['icon'] ?? $get->get('icons') ?? $arg['icons'] ?? null;
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
                    throw new \Exception('action bazarliste : le paramètre icon est mal rempli.<br />Il doit être de la forme icon="nomIcone1=valeur1, nomIcone2=valeur2"<br/>(' . $th->getMessage() . ')');
                }
            } else {
                $icon = $this->params->get('baz_marker_icon');
            }
        }

        // COLORS FIELD
        $colorField = $get->get('colorfield') ?? $arg['colorfield'] ?? null;

        // COLORS
        $color = $get->get('color') ?? $get->get('colors') ?? $arg['colors'] ?? $arg['color'] ?? null;
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
                    throw new \Exception('action bazarliste : le paramètre color est mal rempli.<br />Il doit être de la forme color="couleur1=valeur1, couleur2=valeur2"<br/>(' . $th->getMessage() . ')');
                }
            } else {
                $color = $this->params->get('baz_marker_color');
            }
        }

        $template = $get->get('template') ?? $arg['template'] ?? null;
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
        if ($dynamic && in_array($template, ['tableau.tpl.html', 'tableau.twig', 'tableau'])) {
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
        $ordre = $get->get('ordre') ?? $arg['ordre'] ?? ((empty($arg['champ']) && $agendaMode) ? 'desc' : 'asc');
        // Champ du formulaire utilisé pour le tri
        $champ = $get->get('champ') ?? $arg['champ'] ?? (($agendaMode) ? 'bf_date_debut_evenement' : 'bf_titre');

        $vSearchManager = $this->getService(SearchManager::class);

        $vKeywords = $vSearchManager->aggregateKeywords($arg['keywords'] ?? null, $this->getRequest()->get('q'), $this->getRequest()->get('keywords'));

        return [
            // //////////////////
            // USER PARAMETERS
            // ////////////////

            // SELECTION DES FICHES

            // sélectionner seulement les fiches d'un utilisateur
            'user' => $arg['user'] ?? ((isset($arg['filteruserasowner']) && $arg['filteruserasowner'] == 'true') ?
                $this->getService(AuthenticationService::class)->getLoggedUserName() : null),

            // identifiant du formulaire (plusieures valeurs possibles, séparées par des virgules)
            'idtypeannonce' => $arg['id'] ?? $arg['idtypeannonce'] ?? $get->get('id') ?? null,

            // to be able to refresh cache for external json
            'refresh' => $this->formatBoolean($get->all(), false, 'refresh'),

            // Paramètres pour une requete specifique
            'queries' => $vSearchManager->parseQuery($vSearchManager->aggregateQueries($arg, $get->all())),
            // filtrer sur des mots clefs
            'keywords' => $vKeywords,
            // filtrer les resultats sur une periode données si une date est indiquée
            'dateMin' => $this->formatDateMin($get->get('period') ?? $arg['period'] ?? null),

            // Afficher les fiches dans un ordre aléatoire
            'random' => $this->formatBoolean($arg, false, 'random'),
            // Ordre du tri (asc ou desc)
            'ordre' => $ordre,
            // Champ du formulaire utilisé pour le tri
            'champ' => $champ,
            // les tris disponibles par le bouton "Trier par"
            'sortfields' => $this->formatArray($get->get('sortfields') ?? $arg['sortfields'] ?? []),
            'sortfieldstitles' => $this->formatArray($get->get('sortfieldstitles') ?? $arg['sortfieldstitles'] ?? []),

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
            'necessary_fields' => $this->formatArray($get->get('necessaryfields') ?? $arg['necessaryfields'] ?? $get->get('necessary_fields') ?? $arg['necessary_fields'] ?? []),
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
            'groups' => $this->formatArray($get->get('groups') ?? $arg['groups'] ?? null),
            // Titres des boite de facettes. Plusieures valeurs possibles, séparées par des virgules
            // Exemple : {{bazarliste titles="Titre,Pays,etc."..}}
            'titles' => $this->formatArray($get->get('groupstitles') ?? $arg['groupstitles'] ?? $get->get('titles') ?? $arg['titles'] ?? null),

            // déplier toutes les facettes
            'groupsexpanded' => $this->formatBoolean($get->get('groupsexpanded') ?? $arg, true, 'groupsexpanded'),

            'groupicons' => $this->formatArray($arg['groupicons'] ?? null),

            // ajout d'un filtre pour chercher du texte dans les resultats pour les facettes
            'filtertext' => $this->formatBoolean($arg, false, 'filtertext'),

            // facette à gauche ou à droite
            'filterposition' => $get->get('filterposition') ?? $arg['filterposition'] ?? 'right',
            // largeur colonne facettes
            'filtercolsize' => $get->get('filtercolsize') ?? $arg['filtercolsize'] ?? '3',

            // ICONS

            // Prefixe des classes CSS utilisees pour la carto et calendrier
            'iconprefix' => $get->has('iconprefix') ? trim($get->get('iconprefix')) : (isset($arg['iconprefix']) ? trim($arg['iconprefix']) : ($this->params->get('baz_marker_icon_prefix') ?? '')),
            // Champ utilise pour les icones des marqueurs
            'iconfield' => $iconField,
            // icone des marqueurs
            'icon' => $icon,

            // COLORS

            // Champ utilise pour la couleur des marqueurs
            'colorfield' => $colorField,
            // couleur des marqueurs
            'color' => $color,

            // ////////////////////
            // SYSTEM PARAMETERS
            // //////////////////

            // Iframe ?
            'isInIframe' => testUrlInIframe(),

            'selectedID' => $get->get('selectedID'),
        ];
    }

    public function run()
    {
        $this->debug = (bool)$this->wiki->GetConfigValue('debug');

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
            $currentUser = $this->getService(AuthenticationService::class)->getLoggedUser();

            return $this->render("@core/entries/index-dynamic-templates/{$this->arguments['template']}.twig", [
                'param' => $this->arguments, // DEPRECATED but still there for retro-compatibility: use params (plural)
                'params' => $this->arguments,
                'keywords' => $this->arguments['keywords'],
                'forms' => $vForms,
                'currentUserName' => empty($currentUser['name']) ? '' : $currentUser['name'],
            ]);
        }
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

        $this->wiki->AddJavascriptFile('javascripts/bazar.js', true, true);

        return $this->render('@core/entries/index.twig', [
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
            // 'formId' => $this->arguments['idtypeannonce'][0] ?? null,
            'selectedID' => $this->arguments['selectedID'] ?? null,
            'facette' => $this->getRequest()->query->get('facette'),
        ]);
    }

    private function renderEntries($entries, $filters = [], $pForms = ''): string
    {
        $showNumEntries = count($entries) === 0 || $this->arguments['shownumentries'];
        $templateName = $this->arguments['template'];
        if (strpos($templateName, '.html') === false && strpos($templateName, '.twig') === false) {
            $templateName = $templateName . '.twig';
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
            $query = $this->getRequest()->query->all();
            // Preserved from the PEAR-Pager call this replaced (ticket 02), including the
            // `wiki` strip -- which looks like it drops the page tag outside rewrite mode,
            // but changing URL semantics is out of scope for a dead-code purge.
            unset($query['wiki']);
            $paginationUrl = $this->getService(UrlFormatter::class)->getBaseUrl() . '/' . basename((string)($_SERVER['PHP_SELF'] ?? 'index.php'));

            $paginator = new Paginator(
                $data['fiches'],
                (int)$this->arguments['pagination'],
                Paginator::pageFromQuery($query),
                (int)$this->params->get('BAZ_DELTA')
            );

            $data['fiches'] = $paginator->getPageData();
            $links = $paginator->renderLinks($paginationUrl, $query, [
                'prev' => _t('BAZ_PRECEDENT'),
                'next' => _t('BAZ_SUIVANT'),
            ]);
            $data['pager_links'] = $links === ''
                ? ''
                : '<div class="bazar_numero yw-text-center"><ul class="yw-pagination">' . $links . '</ul></div>';
        }

        try {
            $templateBaseName = preg_replace('/\.(twig|tpl\.html)$/', '', $templateName);
            if ($templateBaseName === 'tableau') {
                return $this->renderTableau($data);
            }
            if ($templateBaseName === 'map') {
                return $this->renderMap($data);
            }

            return $this->render("@core/{$templateName}", $data);
        } catch (TemplateNotFound $e) {
            return '<div class="alert alert-danger">' . $e->getMessage() . '</div>';
        }
    }

    /**
     * Data preparation for tableau.twig -- historically done in PHP inside
     * templates/tableau.tpl.html; moved here when tpl.html templates died.
     *
     * @param array<string,mixed> $data the uniform bazar template data (fiches, params, info_res, ...)
     */
    private function renderTableau(array $data): string
    {
        /** @var FormManager $formManager */
        $formManager = $this->getService(FormManager::class);
        $fiches = $data['fiches'];
        $params = $data['params'];

        $prefix = '';
        $colors = [];
        $icons = [];
        $columnsInfo = [];
        $sanitizedParams = [];
        /** @var array<mixed> $optionsIfDisplayvaluesinsteadofkeys */
        $optionsIfDisplayvaluesinsteadofkeys = [];
        $sumFieldsIds = [];

        if (count($fiches) > 0) {
            $formId = $fiches[array_key_first($fiches)]['form_id'] ?? null;
            $form = $formManager->getOne($formId);
            if (!empty($form)) {
                $fields = $form['prepared'];

                $listParams = [];
                foreach (['columntitles', 'columnfieldsids', 'sumfieldsids'] as $paramKey) {
                    $tmp = (isset($params[$paramKey]) && is_string($params[$paramKey])) ? $params[$paramKey] : '';
                    $listParams[$paramKey] = array_filter(
                        array_map('trim', explode(',', trim($tmp))),
                        function ($name) {
                            return !empty($name);
                        }
                    );
                }
                $columnTitlesNames = $listParams['columntitles'];
                $columnFieldsIdsRaw = $listParams['columnfieldsids'];
                $sumFieldsIds = $listParams['sumfieldsids'];

                $checkboxFieldsInColumns = $params['checkboxfieldsincolumns'] ?? null;
                $checkboxFieldsInColumns = !in_array($checkboxFieldsInColumns, ['0', 0, false, 'false', 'non'], true);

                foreach (['displayadmincol', 'displaycreationdate', 'displaylastchangedate', 'displayowner'] as $paramName) {
                    $paramValue = (!empty($params[$paramName]) && in_array($params[$paramName], ['yes', 'onlyadmins'], true)) ? $params[$paramName] : false;
                    switch ($paramValue) {
                        case 'onlyadmins':
                            $paramValue = $this->wiki->UserIsAdmin();
                            break;
                        case 'yes':
                            $paramValue = true;
                            break;
                        default:
                            $paramValue = false;
                            break;
                    }
                    $sanitizedParams[$paramName] = $paramValue;
                }
                foreach (['displayvaluesinsteadofkeys', 'exportallcolumns', 'displayimagesasthumbnails'] as $paramName) {
                    $sanitizedParams[$paramName] = isset($params[$paramName]) && filter_var($params[$paramName], FILTER_VALIDATE_BOOLEAN);
                }

                $sanitizedParams['columnswidth'] = [];
                if (isset($params['columnswidth']) && is_string($params['columnswidth'])) {
                    foreach (explode(',', $params['columnswidth']) as $columnInfo) {
                        $columnInfoExtracted = explode('=', $columnInfo);
                        if (!empty($columnInfoExtracted[0]) && !empty($columnInfoExtracted[1])) {
                            $sanitizedParams['columnswidth'][$columnInfoExtracted[0]] = $columnInfoExtracted[1];
                        }
                    }
                }

                if (empty($columnFieldsIdsRaw)) {
                    // if no explicit column list, display all fields
                    foreach ($fields as $field) {
                        foreach ($this->tableauFieldCols($field, $checkboxFieldsInColumns, $sanitizedParams['displayimagesasthumbnails']) as $col) {
                            $columnsInfo[] = $col;
                        }
                        $this->tableauCollectOptions($field, $optionsIfDisplayvaluesinsteadofkeys, $sanitizedParams);
                    }
                } else {
                    foreach ($columnFieldsIdsRaw as $fieldId) {
                        $field = $formManager->findFieldFromNameOrPropertyName($fieldId, $formId);
                        if (!empty($field)) {
                            foreach ($this->tableauFieldCols($field, $checkboxFieldsInColumns, $sanitizedParams['displayimagesasthumbnails']) as $col) {
                                $columnsInfo[] = $col;
                            }
                            $this->tableauCollectOptions($field, $optionsIfDisplayvaluesinsteadofkeys, $sanitizedParams);
                        }
                    }
                    if ($sanitizedParams['exportallcolumns']) {
                        $alreadyDefinedPropertyNames = array_map(function ($col) {
                            return $col['propertyName'];
                        }, $columnsInfo);
                        foreach ($fields as $field) {
                            $fieldPropertyName = $field->getPropertyName();
                            if (!empty($fieldPropertyName) && !in_array($fieldPropertyName, $alreadyDefinedPropertyNames)) {
                                foreach ($this->tableauFieldCols($field, $checkboxFieldsInColumns, $sanitizedParams['displayimagesasthumbnails']) as $col) {
                                    $columnsInfo[] = array_merge($col, ['not-visible' => true]);
                                }
                                $this->tableauCollectOptions($field, $optionsIfDisplayvaluesinsteadofkeys, $sanitizedParams);
                            }
                        }
                    }
                }

                foreach ([
                    'displaycreationdate' => ['fieldName' => 'created_at', 'translationKey' => 'BAZ_DATE_CREATION'],
                    'displaylastchangedate' => ['fieldName' => 'updated_at', 'translationKey' => 'BAZ_DATE_MAJ'],
                    'displayowner' => ['fieldName' => 'owner', 'translationKey' => 'TEMPLATE_OWNER'],
                ] as $paramName => $extraCol) {
                    if (!empty($sanitizedParams[$paramName])) {
                        $columnsInfo[] = [
                            'propertyName' => $extraCol['fieldName'],
                            'title' => _t($extraCol['translationKey']),
                            'key' => null,
                            'mapFieldId' => null,
                        ];
                    }
                }

                foreach ($columnTitlesNames as $key => $value) {
                    if (isset($columnsInfo[$key])) {
                        $columnsInfo[$key]['title'] = $value;
                    }
                }

                // mask emails and unreadable values for non-admins
                if (!$this->wiki->UserIsAdmin()) {
                    foreach ($fiches as $index => $fiche) {
                        $entryFormId = $fiche['form_id'];
                        if (strval($entryFormId) != strval(intval($entryFormId))) {
                            unset($fiches[$index]);
                        } else {
                            $entryForm = $formManager->getOne($entryFormId);
                            if (empty($entryForm['prepared'])) {
                                unset($fiches[$index]);
                            } else {
                                foreach ($entryForm['prepared'] as $field) {
                                    if (empty($field->getPropertyName())) {
                                        continue;
                                    }
                                    if ($field instanceof EmailField && !$field->canRead($fiches[$index], null)) {
                                        $fiches[$index][$field->getPropertyName()] = '***@***.***';
                                    } elseif (empty(trim($field->renderStaticIfPermitted($fiche) ?? ''))) {
                                        $fiches[$index][$field->getPropertyName()] = '';
                                    }
                                }
                            }
                        }
                    }
                }

                foreach ($fiches as $fiche) {
                    $colors[$fiche['tag']] = getCustomValueForEntry($params['color'] ?? null, $params['colorfield'] ?? null, $fiche, '');
                    $icons[$fiche['tag']] = getCustomValueForEntry($params['icon'] ?? null, $params['iconfield'] ?? null, $fiche, '');
                }
            } else {
                $prefix = $this->render('@core/alert-message.twig', [
                    'type' => 'danger',
                    'message' => _t('BAZ_NO_FORMS_FOUND'),
                ]);
            }
        }

        return $prefix . $this->render('@core/tableau.twig', [
            'infoRes' => $data['info_res'],
            'params' => $params,
            'columnsInfo' => $columnsInfo,
            'entries' => $fiches,
            'sumFieldsIds' => $sumFieldsIds,
            'displayadmincol' => $sanitizedParams['displayadmincol'] ?? null,
            'displayvaluesinsteadofkeys' => $sanitizedParams['displayvaluesinsteadofkeys'] ?? null,
            'optionsIfDisplayvaluesinsteadofkeys' => $optionsIfDisplayvaluesinsteadofkeys,
            'colors' => $colors,
            'icons' => $icons,
            'columnswidth' => $sanitizedParams['columnswidth'] ?? [],
        ]);
    }

    /**
     * JS builder for the static (non-dynamic) leaflet map -- historically done in PHP
     * inside templates/map.tpl.html; moved here when tpl.html templates died.
     *
     * @param array<string,mixed> $data the uniform bazar template data (fiches, params, info_res, ...)
     */
    private function renderMap(array $data): string
    {
        $params = $data['params'];
        $fiches = $data['fiches'];
        $output = $data['info_res'];
        if (count($fiches) === 0) {
            return $output;
        }
        $js = '';

        $this->wiki->AddCSSFile('styles/vendor/leaflet/leaflet.css');
        $this->wiki->AddCSSFile('styles/bazar/bazarcarto.css');
        $this->wiki->AddCSSFile('javascripts/vendor/leaflet-draw/leaflet.draw.css');
        $this->wiki->AddJavascriptFile('javascripts/bazar.js', true, true);
        $this->wiki->AddJavascriptFile('javascripts/vendor/leaflet/leaflet.min.js');
        $this->wiki->AddJavascriptFile('javascripts/vendor/leaflet-providers/leaflet-providers.js');
        $this->wiki->AddJavascriptFile('javascripts/vendor/leaflet-draw/leaflet.draw.js', false, true);

        $output .= '<div id="osmmap' . $params['nbbazarliste'] . '" class="no-dblclick" style="width:' . $params['width'] . '; height:' . $params['height'] . '"></div>';

        if ($params['spider'] == 'true' or $params['spider'] == '1') {
            $this->wiki->AddJavascriptFile('javascripts/vendor/leaflet-spiderfier/oms.min.js');
            $markersjs = 'var popups = Array();' . "\n" . 'oms = new OverlappingMarkerSpiderfier(map' . $params['nbbazarliste'] . ');' . "\n" .
            'var popup = new L.Popup();
        oms.addListener("click", function(marker) {
            marker.openPopup();
        });
        oms.addListener(\'spiderfy\', function(markers) {
          map' . $params['nbbazarliste'] . '.closePopup();
        });' . "\n";
        } elseif ($params['cluster'] == 'true' or $params['cluster'] == '1') {
            $this->wiki->AddCSSFile('styles/vendor/leaflet-markercluster/leaflet-markercluster.css');
            $this->wiki->AddJavascriptFile('javascripts/vendor/leaflet-markercluster/leaflet-markercluster.min.js');
            $markersjs = 'var markerscluster = new L.MarkerClusterGroup();' . "\n";
        } else {
            $markersjs = '';
        }

        if ($params['fullscreen'] == 'true' || $params['fullscreen'] == '1') {
            $params['fullscreen'] = 'true';
            $this->wiki->AddCSSFile('styles/vendor/leaflet-fullscreen/leaflet-fullscreen.css');
            $this->wiki->AddJavascriptFile('javascripts/vendor/leaflet-fullscreen/leaflet-fullscreen.js');
        } else {
            $params['fullscreen'] = 'false';
        }

        $vAllGeometries = $geometriesWithoutMarker = [];
        $fiche = ['html_data' => ''];

        $vGeolocationField = $params['geolocationfield'] ?? 'bf_geolocation';

        foreach ($fiches as $fiche) {
            $vGeolocation = $fiche[$vGeolocationField] ?? [];

            $vLatitude = $vGeolocation['latitude'] ?? '';
            $vLongitude = $vGeolocation['longitude'] ?? '';
            $vGeometries = $vGeolocation['geometries'] ?? '';
            $vGeometries = str_replace(
                '"type":"FeatureCollection"',
                '"id":"' . $fiche['tag'] . '","type":"FeatureCollection"',
                $vGeometries
            );

            // couleur de marqueur
            $color = getCustomValueForEntry($params['color'], $params['colorfield'], $fiche, $this->wiki->config['baz_marker_color']);

            // icone de marqueur
            $icon = $params['iconprefix']
                    . getCustomValueForEntry($params['icon'], $params['iconfield'], $fiche, $this->wiki->config['baz_marker_icon']);

            if (is_numeric($vLatitude) && is_numeric($vLongitude)) {
                // on genere le point marqueur sur la carte
                $markersjs .= '
				i++;
				var markerLocation = new L.LatLng(' . $vLatitude . ', ' . $vLongitude . ');
				marker[i] = new L.Marker(
						markerLocation,
						{
								icon: L.divIcon({
										iconSize: ' . $params['iconSize'] . ',
										iconAnchor: ' . $params['iconAnchor'] . ',
										popupAnchor: ' . $params['popupAnchor'] . ',
										className: \'bazar-marker' . $params['smallmarker'] . '\',
										html: \'<div class="bazar-entry" '
                                        . str_replace('\'', '', $fiche['html_data']) . ' style="color:' . $color . ';">'
                                        . (!empty($icon) ? '<i class="fa-fw ' . $icon . '"></i>' : '')
                                        . '</div>\'
								}),
								title: \'' . addslashes($fiche['bf_titre']) . '\'
						});
				marker[i].bindPopup(\'' . preg_replace("(\r\n|\n|\r|)", '', addslashes(baz_voir_fiche($params['barregestion'], $fiche))) . '\');
				';
                if ($params['spider'] == 'true' or $params['spider'] == '1') {
                    $markersjs .= 'map' . $params['nbbazarliste'] . '.addLayer(marker[i]);' . "\n" . 'oms.addMarker(marker[i]);' . "\n";
                } elseif ($params['cluster'] == 'true' or $params['cluster'] == '1') {
                    $markersjs .= 'markerscluster.addLayer(marker[i]);' . "\n";
                } else {
                    $markersjs .= 'map' . $params['nbbazarliste'] . '.addLayer(marker[i]);' . "\n";
                }
            } elseif (!empty($vGeometries)) {
                // fake marker for facetted search
                // TODO: this is way too hacky, need to find a way to search on marker and geometries in a better way
                $geometriesWithoutMarker[$fiche['tag']] = '<div class="bazar-entry" ' . str_replace('\'', '', $fiche['html_data']) . '></div>';
            }
            if (!empty($vGeometries)) {
                $vAllGeometries[$fiche['tag']] = $vGeometries;
            }
        }

        if ($params['cluster'] == 'true' or $params['cluster'] == '1') {
            $markersjs .= 'map' . $params['nbbazarliste'] . '.addLayer(markerscluster);' . "\n";
        }
        $js .=
                '// Init leaflet map
		var map' . $params['nbbazarliste'] . ' = new L.Map(\'osmmap' . $params['nbbazarliste'] . '\', {
				scrollWheelZoom:' . $params['zoom_molette'] . ',
				zoomControl:' . $params['navigation'] . ',
		fullscreenControl:' . $params['fullscreen'] . ',
				iconAnchor:   [6, 20]
		});';

        // Pas de L.control.layers
        if (empty($params['providers']) && empty($params['layers'])) {
            $js .=
                '
			var provider = L.tileLayer.provider("' . $params['provider'] . '"' . $params['provider_credentials'] . ');
			';
        } else {
            // Avec un L.control.layers
            // Si param['provider'] existe, ce sera le baseLayer activé par défaut, sinon ce sera le 1er de la liste $params['providers'].
            $js .= 'var provider; var baseLayers = {};';
            if (empty($params['providers'])) {
                $params['providers'] = [$params['provider']];
            }
            foreach ($params['providers'] as $provider) {
                $js .= 'baseLayers["' . $provider . '"] = L.tileLayer.provider("' . $provider . '");';
                if (empty($params['provider'])) {
                    $js .= 'if(provider==null) provider=baseLayers["' . $provider . '"];';
                } elseif ($provider == $params['provider']) {
                    $js .= 'provider=baseLayers["' . $provider . '"];';
                }
            }

            $js .= 'var layers = {};';
            if (is_array($params['layers'])) {
                $leafletajaxIncluded = false;
                foreach ($params['layers'] as $layer) {
                    @list($layerLabel, $layerType, $layerOptions, $layerUrl) = explode('|', $layer);
                    if ($layerUrl == null) {
                        $layerUrl = $layerOptions;
                        $layerOptions = null;
                    }
                    $layerType = strtolower($layerType);
                    if (!in_array($layerType, ['tiles', 'geojson'])) {
                        $js .= 'alert("Erreur paramètre \\"layers\\": le type \\"' . $layerType . '\\" est inconnu")';
                    }
                    switch ($layerType) {
                        case 'tiles':
                            $js .= 'layers["' . $layerLabel . '"] = L.tileLayer("' . $layerUrl . '");';
                            break;
                        case 'geojson':
                            // URL: Attention au Blocage d’une requête multi-origines (Cross-Origin Request).
                            // Le plus simple est de recopier les data GeoJson dans une page du Wiki puis de l'appeler avec le handler "/raw".
                            // STYLE:
                            //	http://leafletjs.com/reference.html#path-options
                            //	http://leafletjs.com/reference.html#marker-options
                            if (!$leafletajaxIncluded) {
                                $leafletajaxIncluded = true;
                                $this->wiki->AddJavascriptFile('javascripts/vendor/leaflet-ajax/leaflet.ajax.min.js');
                            }

                            $styleJs = '';
                            $isVisibleByDefault = false;
                            if ($layerOptions != null) {
                                // extract 'visiblebydefault'
                                if (preg_match_all('/visiblebydefault\\s*;?/i', $layerOptions, $matches)) {
                                    $isVisibleByDefault = true;
                                    foreach ($matches[0] as $key => $value) {
                                        $layerOptions = str_replace($value, '', $layerOptions);
                                    }
                                }
                                $layerOptions = str_replace(';', ',', $layerOptions);
                                $styleJs .= '
							style: function (feature, latlng) {
								// pour les lignes et polygones
								if( feature.geometry.type=="Point" )
									return ;
								return {' . $layerOptions . '};
							},
							pointToLayer: function (feature, latlng) {
								// pour les points
								// parsing de layerOptions pour distinguer les options de marker et celles pour construire un icon pour le marker.
								var layerOptions = "' . $layerOptions . '".split(",");
								var markerOptions = {} , iconClass=null, color=null ;
								for( opt in layerOptions )
								{
									opt = layerOptions[opt].split(":");
									switch(opt[0].trim()) {
										case "opacity":
										case "clickable":
											// les options pour le marker http://leafletjs.com/reference.html#marker
											markerOptions[opt[0].trim()] = opt[1].trim();
											break;
										case "icon":
											// pour le html du L.divIcon http://leafletjs.com/reference.html#divicon
											// supprimer les éventuels apostrhophes
											iconClass = opt[1].trim().replace(/\'/g, "");
											break;
										case "color":
											// pour le html du L.divIcon
											color = opt[1].trim();
											break;
									}
								}

								if( iconClass!=null || color!=null ) {
									// Construit un L.divIcon pour le marker, sinon ce sera par défaut la goutte bleu.
									markerOptions["icon"] = L.divIcon({
											iconSize: ' . $params['iconSize'] . ',
											iconAnchor: ' . $params['iconAnchor'] . ',
											popupAnchor: ' . $params['popupAnchor'] . ',
											className: "bazar-marker' . $params['smallmarker'] . '",
											html: "<div class=\"bazar-entry "+ (color==null?"":"icon-"+color)+"\" ' . addslashes($fiche['html_data']) . '>" + (iconClass==null?"":"<i class=\""+iconClass+"\"></i>") + "</div>"
									});
								}

								return L.marker(latlng, markerOptions);
							},
							';
                            }

                            $js .= 'layers["' . $layerLabel . '"] = L.geoJson.ajax("' . $layerUrl . '",
						{
              interactive: false,
							' . $styleJs . '
							onEachFeature: function (feature, layer) {
								//layer.bindPopup(feature.properties.NOM + feature.properties.NOM_QP);
								var str = "" ;
								for( var prop in feature.properties){
									if( prop.toLowerCase() == "url" ) {
										str+= prop +": <a href=\""+ feature.properties[prop] + "\" target=\"_blank\" >" + feature.properties[prop] +"<br/>";
									} else {
										str+= prop +": "+ feature.properties[prop] +"<br/>";
									}
								}
								//layer.bindPopup( str );
							}
						} );';
                            if ($isVisibleByDefault) {
                                // add layer to the map
                                $js .= 'layers["' . $layerLabel . '"].addTo(map' . $params['nbbazarliste'] . ');';
                            }
                            break;
                    }
                }
            }

            $js .= 'L.control.layers(baseLayers, layers).addTo(map' . $params['nbbazarliste'] . ');';
        }
        $geometriesModuleJs = '';
        if (!empty($vAllGeometries)) {
            $geometriesModuleJs .= '

    var drawnFeatures = new L.FeatureGroup()
    map' . $params['nbbazarliste'] . ".addLayer(drawnFeatures)\n";
            foreach ($vAllGeometries as $id => $g) {
                $geometriesModuleJs .= "const geo{$id} = " . $g . "\n";
                $geometriesModuleJs .= 'var popup = \'' . preg_replace("(\r\n|\n|\r|)", '', addslashes(baz_voir_fiche($params['barregestion'], $id))) . '\'' . "\n";
                $geometriesModuleJs .= "drawnFeatures = drawGeometries(drawnFeatures, geo{$id}.features, popup, '{$id}')\n";
            }
            if (!empty($geometriesWithoutMarker)) {
                $js .= 'L.Control.geometriesPanel = L.Control.extend({
    onAdd: function(map) {
        const div = L.DomUtil.create(\'div\', \'info-panel\');
        div.innerHTML = \'' . implode('', $geometriesWithoutMarker) . '\';
        div.style.background = \'transparent\';
        div.style.padding = \'0\';

        // Prevent clicks from reaching the map
        L.DomEvent.disableClickPropagation(div);

        return div;
    }
});

  new L.Control.geometriesPanel({ position: \'topright\' }).addTo(map' . $params['nbbazarliste'] . ');';
            }
        }
        $this->wiki->AddJavascript(
            '
import { drawGeometries } from "./javascripts/leaflet-draw.helper.js"

var map' . $params['nbbazarliste'] . ';	
document.addEventListener(\'DOMContentLoaded\', function() {
    if (document.getElementById(\'osmmap' . $params['nbbazarliste'] . '\')) {
		' . $js . '
		// N\'ajoute pas un doublon de layer, mais active la sélection du layer.
		map' . $params['nbbazarliste'] . '.addLayer(provider);
		map' . $params['nbbazarliste'] . '.setView(new L.LatLng(' . $params['latitude'] . ', ' . $params['longitude'] . '), ' . $params['zoom'] . ');
		var i = 0;
		var marker = Array();
    ' . $markersjs . '
    }
    ' . $geometriesModuleJs . '
	});',
            true
        );

        return $output;
    }

    /**
     * One tableau column descriptor per displayable facet of a field (checkbox options may fan out).
     *
     * @return array<int,array<string,mixed>>
     */
    private function tableauFieldCols(BazarField $field, bool $checkboxFieldsInColumns, bool $displayImagesAsThumbnails): array
    {
        $propertyName = $field->getPropertyName();
        if (empty($propertyName)) {
            return [];
        }

        $fieldLabel = $field->getLabel();
        if ($field instanceof MapField) {
            return [[
                'propertyName' => $propertyName,
                'title' => $fieldLabel,
                'key' => null,
                'mapFieldId' => $propertyName,
                'multivalues' => false,
            ]];
        }
        if ($field instanceof CheckboxField && $checkboxFieldsInColumns) {
            $options = $field->getOptions();
            if (empty($options)) {
                return [];
            }
            $cols = [];
            foreach ($options as $key => $optionName) {
                $cols[] = [
                    'propertyName' => $propertyName,
                    'title' => "$fieldLabel - $optionName",
                    'key' => $key,
                    'mapFieldId' => null,
                    'multivalues' => false,
                ];
            }

            return $cols;
        }

        return [[
            'propertyName' => $propertyName,
            'title' => $fieldLabel,
            'key' => null,
            'mapFieldId' => null,
            'multivalues' => $field instanceof CheckboxField,
        ] + ($displayImagesAsThumbnails && $field instanceof ImageField ? ['imageAsThumbnail' => true] : [])];
    }

    /**
     * @param array<mixed> $optionsIfDisplayvaluesinsteadofkeys
     * @param array<mixed> $sanitizedParams
     */
    private function tableauCollectOptions(BazarField $field, array &$optionsIfDisplayvaluesinsteadofkeys, array $sanitizedParams): void
    {
        $propertyName = $field->getPropertyName();
        if ($sanitizedParams['displayvaluesinsteadofkeys']
            && $field instanceof EnumField
            && !isset($optionsIfDisplayvaluesinsteadofkeys[$propertyName])) {
            $optionsIfDisplayvaluesinsteadofkeys[$propertyName] = $field->getOptions();
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
