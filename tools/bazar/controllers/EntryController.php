<?php

namespace YesWiki\Bazar\Controller;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Bazar\Field\BazarField;
use YesWiki\Bazar\Field\ImageField;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Bazar\Service\SemanticTransformer;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\TemplateEngine;
use YesWiki\Core\YesWikiController;
use YesWiki\Security\Controller\SecurityController;

class EntryController extends YesWikiController
{
    protected $entryManager;
    protected $formManager;
    protected $aclService;
    protected $semanticTransformer;
    protected $pageManager;
    protected $templateEngine;
    protected $config;
    protected $securityController;

    public function __construct(
        EntryManager $entryManager,
        FormManager $formManager,
        AclService $aclService,
        SemanticTransformer $semanticTransformer,
        PageManager $pageManager,
        ParameterBagInterface $config,
        SecurityController $securityController
    ) {
        $this->entryManager = $entryManager;
        $this->formManager = $formManager;
        $this->aclService = $aclService;
        $this->semanticTransformer = $semanticTransformer;
        $this->pageManager = $pageManager;
        $this->config = $config->all();
        $this->securityController = $securityController;
    }

    public function selectForm()
    {
        $forms = $this->formManager->getAll();

        return $this->render("@bazar/entries/select_form.twig", ['forms' => $forms]);
    }

    public function view($entryId, $time = '', $showFooter = true)
    {
        if (is_array($entryId)) {
            // If entry ID is the full entry with all the values
            $entry = $entryId;
            $entryId = $entry['id_fiche'];
        } elseif ($entryId) {
            $entry = $this->entryManager->getOne($entryId, false, $time);
            if (!$entry) {
                return '<div class="alert alert-danger">' . _t('BAZ_PAS_DE_FICHE_AVEC_CET_ID') . ' : ' . $entryId . '</div>';
            }
        } else {
            return '<div class="alert alert-danger">' . _t('BAZ_PAS_D_ID_DE_FICHE_INDIQUEE') . '</div>';
        }

        $form = $this->formManager->getOne($entry['id_typeannonce']);

        // fake ->tag for the attached images
        $oldPageTag = $this->wiki->GetPageTag();
        $this->wiki->tag = $entryId;

        $renderedEntry = null;

        // use a custom template if exists (fiche-FORM_ID.tpl.html or fiche-FORM_ID.twig)
        $customTemplatePath = $this->getCustomTemplatePath($entry);
        if ($customTemplatePath) {
            $customTemplateValues = $this->getValuesForCustomTemplate($entry, $form);
            $renderedEntry = $this->render($customTemplatePath, $customTemplateValues);
        }

        // use a custom semantic template if exists
        if (is_null($renderedEntry) && !empty($customTemplateValues['html']['semantic'])) {
            $customTemplatePath = $this->getCustomSemanticTemplatePath($customTemplateValues['html']['semantic']);
            if ($customTemplatePath) {
                $renderedEntry = $this->render("@bazar/$customTemplatePath", $customTemplateValues);
            }
        }
        // if not found, use default template
        if (is_null($renderedEntry)) {
            if (!empty($form)) {
                foreach ($form['prepared'] as $field) {
                    if ($field instanceof BazarField) {
                        // TODO handle html_outside_app mode for images
                        if (!in_array($field->getPropertyName(), $this->fieldsToExclude()))
                            $renderedEntry .= $field->renderStaticIfPermitted($entry);
                    }
                }
            } else {
                $renderedEntry = $this->render(
                    "@templates/alert-message.twig",
                    [
                        'type' => 'info',
                        'message' => str_replace('{{nb}}', $entry['id_typeannonce'], _t('BAZ_PAS_DE_FORM_AVEC_ID_DE_CETTE_FICHE')),
                    ]
                );
            }
        }

        // fake ->tag for the attached images
        $this->wiki->tag = $oldPageTag;

        // Format owner
        $owner = $this->wiki->GetPageOwner($entryId);
        $isOwnerIpAddress = preg_replace('/([0-9]|\.)/', '', $owner) == '';
        if ($isOwnerIpAddress || !$owner) $owner = "Utilisateur Inconu";
        if (!empty($this->config['sso_config']) && isset($this->config['sso_config']['bazar_user_entry_id']) && $this->pageManager->getOne($owner)) {
            $owner = $this->wiki->Format('[[' . $this->wiki->GetPageOwner($entryId) . ' ' . $this->wiki->GetPageOwner($entryId) . ']]');
        }

        return $this->render('@bazar/entries/view.twig', [
            "form" => $form,
            "entry" => $entry,
            "entryId" => $entryId,
            "owner" => $owner,
            "message" => $_GET['message'] ?? '',
            "showFooter" => $showFooter,
            "canShow" => $this->wiki->GetPageTag() != $entry['id_fiche'], // hide if we are already in the show page
            "canEdit" =>  !$this->securityController->isWikiHibernated() && $this->aclService->hasAccess('write', $entryId),
            "canDelete" => !$this->securityController->isWikiHibernated() && ($this->wiki->UserIsAdmin() or $this->wiki->UserIsOwner()),
            "isAdmin" => $this->wiki->UserIsAdmin(),
            "renderedEntry" => $renderedEntry,
            "incomingUrl" => $_GET['incomingurl'] ?? getAbsoluteUrl()
        ]);
    }

    private function fieldsToExclude() {
        return isset($_GET['excludeFields']) ? explode(',', $_GET['excludeFields']) : [];
    }

    public function publish($entryId, $accepted)
    {
        $this->entryManager->publish($entryId, $accepted);

        if ($accepted) {
            echo '<div class="alert alert-success"><a data-dismiss="alert" class="close" type="button">&times;</a>' . _t('BAZ_FICHE_VALIDEE') . '</div>';
        } else {
            echo '<div class="alert alert-success"><a data-dismiss="alert" class="close" type="button">&times;</a>' . _t('BAZ_FICHE_PAS_VALIDEE') . '</div>';
        }

        return $this->view($entryId);
    }

    public function create($formId, $redirectUrl = null)
    {
        if (empty($formId)) {
            return '<div class="alert alert-danger">' . _t('BAZ_PAS_D_ID_DE_FORM_INDIQUE') . '</div>';
        }
        $form = $this->formManager->getOne($formId);
        if (!$form) {
            return '<div class="alert alert-danger">' . _t('BAZ_PAS_DE_FORM_AVEC_CET_ID') . ' : \'' . $formId . '\'</div>';
        }

        if (isset($_POST['bf_titre'])) {
            $entry = $this->entryManager->create($formId, $_POST);
            if (empty($redirectUrl)) {
                $redirectUrl = $this->wiki->Href(
                    testUrlInIframe(),
                    '',
                    [  'vue' => 'consulter',
                       'action' => 'voir_fiche',
                       'id_fiche' => $entry['id_fiche'],
                       'message' => 'ajout_ok'],
                    false
                );
            }
            header('Location: ' . $redirectUrl);
            exit;
        }

        return $this->render("@bazar/entries/form.twig", [
            'form' => $form,
            'renderedInputs' => $this->getRenderedInputs($form),
            'showConditions' => $form['bn_condition'] !== '' && !isset($_POST['accept_condition']),
            'passwordForEditing' => isset($this->config['password_for_editing']) && !empty($this->config['password_for_editing']) && isset($_POST['password_for_editing']) ? $_POST['password_for_editing'] : ''
        ]);
    }

    public function update($entryId)
    {
        $entry = $this->entryManager->getOne($entryId);
        $form = $this->formManager->getOne($entry['id_typeannonce']);

        if (isset($_POST['bf_titre'])) {
            $entry = $this->entryManager->update($entryId, $_POST);
            if (empty($redirectUrl)) {
                $redirectUrl = $this->wiki->Href(testUrlInIframe(), '', [
                    'vue' => 'consulter',
                    'action' => 'voir_fiche',
                    'id_fiche' => $entry['id_fiche'],
                    'message' => 'modif_ok'
                ], false);
            }
            header('Location: ' . $redirectUrl);
            exit;
        }

        return $this->render("@bazar/entries/form.twig", [
            'form' => $form,
            'entryId' => $entryId,
            'renderedInputs' => $this->getRenderedInputs($form, $entry),
            'showConditions' => false,
            'passwordForEditing' => isset($this->config['password_for_editing']) && !empty($this->config['password_for_editing']) && isset($_POST['password_for_editing']) ? $_POST['password_for_editing'] : ''
        ]);
    }

    public function delete($entryId)
    {
        $this->entryManager->delete($entryId);
        // WARNING : 'delete_ok' is not used
        header('Location: ' . $this->wiki->Href('', 'BazaR', ['vue' => 'consulter', 'message' => 'delete_ok']));
    }

    private function getRenderedInputs($form, $entry = null)
    {
        $renderedFields = [];
        foreach ($form['prepared'] as $field) {
            if ($field instanceof BazarField) {
                $renderedFields[] = $field->renderInputIfPermitted($entry);
            }
        }
        return $renderedFields;
    }


    private function getCustomTemplatePath($entry): ?string
    {
        $templatePaths = [
            "@bazar/fiche-{$entry['id_typeannonce']}.tpl.html",
            "@bazar/fiche-{$entry['id_typeannonce']}.twig"
        ];
        foreach ($templatePaths as $templatePath) {
            if ($this->getService(TemplateEngine::class)->hasTemplate($templatePath)) {
                return $templatePath;
            }
        }
        return null;
    }

    private function getCustomSemanticTemplatePath($semanticData): ?string
    {
        if (empty($semanticData)) {
            return null;
        }

        // Trouve le contexte principal
        if (is_array($semanticData['@context'])) {
            foreach ($semanticData['@context'] as $context) {
                if (is_string($context)) {
                    break;
                }
            }
        } else {
            $context = $semanticData['@context'];
        }

        // Si on a trouvé un contexte et qu'un mapping existe pour ce contexte
        if (isset($context) && $dir_name = $this->config['baz_semantic_types_mapping'][$context]) {
            // Trouve le type principal
            if (is_array($semanticData['@type'])) {
                foreach ($semanticData['@type'] as $type) {
                    if (is_string($type)) {
                        break;
                    }
                }
            } else {
                $type = $semanticData['@type'];
            }

            if (isset($type)) {
                $templatePath = $dir_name . "/" . strtolower($type) . ".tpl.html";
                return $this->getService(TemplateEngine::class)->hasTemplate($templatePath) ? $templatePath : null;
            }
        }

        return null;
    }

    private function getValuesForCustomTemplate($entry, $form)
    {
        $html = [];
        foreach ($form['prepared'] as $field) {
            if ($field instanceof BazarField) {
                $id = $field->getPropertyName();
                if (!empty($id) && !in_array($id, $this->fieldsToExclude())) {
                    $html[$id] = $field->renderStaticIfPermitted($entry);
                    if ($id == 'bf_titre') {
                        preg_match('/<h1 class="BAZ_fiche_titre">\s*(.*)\s*<\/h1>.*$/is', $html[$id], $matches);
                    } else {
                        preg_match('/<span class="BAZ_texte">\s*(.*)\s*<\/span>.*$/is', $html[$id], $matches);
                    }
                    if (isset($matches[1]) && $matches[1] != '') {
                        $html[$id] = $matches[1];
                    }
                }
            }
        }

        if ($form['bn_sem_type']) {
            $html['id_fiche'] = $entry['id_fiche'];
            $html['semantic'] = $GLOBALS['wiki']->services->get(SemanticTransformer::class)->convertToSemanticData($form, $html, true);
        }

        $values['html'] = $html;
        $values['fiche'] = $entry;
        $values['form'] = $form;

        return $values;
    }

    /**
     * format queries form GET and from $arg in order to give the right 'queries' to EntryManager->search
     * @param array|string|null $arg
     * @param array $get (copy of $_GET) but pass in parameters to be more visible in primary level controllers
     * @return array
     */
    public function formatQuery($arg, array $get) : array
    {
        $queryArray = [];

        // Aggregate argument and $get values
        if (isset($get['query'])) {
            if (!empty($arg['query'])) {
                if (is_array($arg['query'])) {
                    $queryArray = $arg['query'] ;
                    $query = $get['query'];
                } else {
                    $query = $arg['query'].'|'.$get['query'];
                }
            } else {
                $query = $get['query'];
            }
        } else {
            if (isset($arg['query']) && is_array($arg['query'])) {
                $queryArray = $arg['query'] ;
                $query = null;
            } else {
                $query = $arg['query'] ?? null;
            }
        }

        // Create an array from the queries
        if (!empty($query)) {
            $res1 = explode('|', $query);
            foreach ($res1 as $req) {
                $res2 = explode('=', $req, 2);
                if (isset($queryArray[$res2[0]]) && !empty($queryArray[$res2[0]])) {
                    $queryArray[$res2[0]] = $queryArray[$res2[0]].','.trim($res2[1] ?? '');
                } else {
                    $queryArray[$res2[0]] = trim($res2[1] ?? '');
                }
            }
        }

        return $queryArray;
    }

    /* PART TO FILTER ON DATE */

    /**
     * filter entries on date
     * @param array $entries
     * @param string $datefilter
     * @return array $entries
     */
    public function filterEntriesOnDate($entries, $datefilter) : array
    {
        $TODAY_TEMPLATE = "/^(today|aujourdhui|=0(D)?)$/i" ;
        $FUTURE_TEMPLATE = "/^(futur|future|>0(D)?)$/i" ;
        $PAST_TEMPLATE = "/^(past|passe|<0(D)?)$/i" ;
        $DATE_TEMPLATE = "(\+|-)(([0-9]+)Y)?(([0-9]+)M)?(([0-9]+)D)?" ;
        $EQUAL_TEMPLATE = "/^=".$DATE_TEMPLATE."$/i" ;
        $MORE_TEMPLATE = "/^>".$DATE_TEMPLATE."$/i" ;
        $LOWER_TEMPLATE = "/^<".$DATE_TEMPLATE."$/i" ;
        $BETWEEN_TEMPLATE = "/^>".$DATE_TEMPLATE."&<".$DATE_TEMPLATE."$/i" ;

        if (preg_match_all($TODAY_TEMPLATE, $datefilter, $matches)) {
            $todayMidnigth = new \DateTime() ;
            $todayMidnigth->setTime(0, 0);
            $entries = array_filter($entries, function ($entry) use ($todayMidnigth) {
                return $this->filterEntriesOnDateTraversing($entry, "=", $todayMidnigth) ;
            });
        } elseif (preg_match_all($FUTURE_TEMPLATE, $datefilter, $matches)) {
            $now = new \DateTime() ;
            $entries = array_filter($entries, function ($entry) use ($now) {
                return $this->filterEntriesOnDateTraversing($entry, ">", $now) ;
            });
        } elseif (preg_match_all($PAST_TEMPLATE, $datefilter, $matches)) {
            $now = new \DateTime() ;
            $entries = array_filter($entries, function ($entry) use ($now) {
                return $this->filterEntriesOnDateTraversing($entry, "<", $now) ;
            });
        } elseif (preg_match_all($EQUAL_TEMPLATE, $datefilter, $matches)) {
            $sign = $matches[1][0];
            $nbYears = $matches[3][0];
            $nbMonth = $matches[5][0];
            $nbDays = $matches[7][0];

            $dateMidnigth = $this->extractDate($sign, $nbYears, $nbMonth, $nbDays);
            $dateMidnigth->setTime(0, 0);
            $entries = array_filter($entries, function ($entry) use ($dateMidnigth) {
                return $this->filterEntriesOnDateTraversing($entry, "=", $dateMidnigth) ;
            });
        } elseif (preg_match_all($MORE_TEMPLATE, $datefilter, $matches)) {
            $sign = $matches[1][0];
            $nbYears = $matches[3][0];
            $nbMonth = $matches[5][0];
            $nbDays = $matches[7][0];

            $date = $this->extractDate($sign, $nbYears, $nbMonth, $nbDays) ;
            $entries = array_filter($entries, function ($entry) use ($date) {
                return $this->filterEntriesOnDateTraversing($entry, ">", $date) ;
            });
        } elseif (preg_match_all($LOWER_TEMPLATE, $datefilter, $matches)) {
            $sign = $matches[1][0];
            $nbYears = $matches[3][0];
            $nbMonth = $matches[5][0];
            $nbDays = $matches[7][0];

            $date = $this->extractDate($sign, $nbYears, $nbMonth, $nbDays) ;
            $entries = array_filter($entries, function ($entry) use ($date) {
                return $this->filterEntriesOnDateTraversing($entry, "<", $date) ;
            });
        } elseif (preg_match_all($BETWEEN_TEMPLATE, $datefilter, $matches)) {
            $signMore = $matches[1][0];
            $nbYearsMore = $matches[3][0];
            $nbMonthMore = $matches[5][0];
            $nbDaysMore = $matches[7][0];
            $dateMin = $this->extractDate($signMore, $nbYearsMore, $nbMonthMore, $nbDaysMore);
            $signLower = $matches[8][0];
            $nbYearsLower = $matches[10][0];
            $nbMonthLower = $matches[12][0];
            $nbDaysLower = $matches[14][0];
            $dateMax = $this->extractDate($signLower, $nbYearsLower, $nbMonthLower, $nbDaysLower);
            if ($dateMin->diff($dateMax)->invert == 0) {
                // $dateMax higher than $dateMin
                $entries = array_filter($entries, function ($entry) use ($dateMin) {
                    return $this->filterEntriesOnDateTraversing($entry, ">", $dateMin) ;
                });
                $entries = array_filter($entries, function ($entry) use ($dateMax) {
                    return $this->filterEntriesOnDateTraversing($entry, "<", $dateMax) ;
                });
            }
        }

        return $entries ;
    }

    private function extractDate(string $sign, string $nbYears, string $nbMonth, string $nbDays): \DateTime
    {
        $dateInterval = new \DateInterval(
            'P'
                .(!empty($nbYears) ? $nbYears . 'Y' : '')
                .(!empty($nbMonth) ? $nbMonth . 'M' : '')
                .(!empty($nbDays) ? $nbDays . 'D' : '')
        );
        $dateInterval->invert = ($sign == "-") ? 1 : 0;

        $date = new \DateTime() ;
        $date->add($dateInterval) ;

        return $date;
    }

    private function filterEntriesOnDateTraversing(?array $entry, string $mode = "=", \DateTime $date): bool
    {
        if (empty($entry) || !isset($entry['bf_date_debut_evenement'])) {
            return false;
        }

        $entryStartDate = new \DateTime($entry['bf_date_debut_evenement']);
        $entryEndDate = isset($entry['bf_date_fin_evenement']) ? new \DateTime($entry['bf_date_fin_evenement']) : null  ;
        if (isset($entry['bf_date_fin_evenement']) && strpos($entry['bf_date_fin_evenement'], 'T')=== false) {
            // all day (so = midnigth of next day)
            $entryEndDate->add(new \DateInterval("P1D"));
        }
        $nextDay = (clone $date)->add(new \DateInterval("P1D"));
        switch ($mode) {
            case "<":
                // start before date
                return (
                    $date->diff($entryStartDate)->invert == 1
                    && $entryEndDate && $date->diff($entryEndDate)->invert == 1
                    );
                break;
            case ">":
                // start after date or (before date but and end should be after date, end is needed)
                return (
                    $date->diff($entryStartDate)->invert == 0
                    || ($entryEndDate && $date->diff($entryEndDate)->invert == 0)
                    );
                break;
            case "=":
            default:
                // start before next day midnight and end should be after date midnigth
                return (
                        $nextDay->diff($entryStartDate)->invert == 1
                        && $entryEndDate && $date->diff($entryEndDate)->invert == 0
                    );
        }
    }

    /* END OF PART TO FILTER ON DATE */

    public function renderBazarList($entries, $param =[], $showNumEntries = true)
    {
        $ids = [];
        foreach ($entries as $entry) {
            if (!empty($entry['id_fiche'])) {
                $ids[] = $entry['id_fiche'];
            }
        }
        $params['query'] = 'id_fiche=' . implode(',', $ids);
        $params['shownumentries'] = $showNumEntries;

        if (empty($ids)) {
            return $this->render(
                '@templates/alert-message.twig',
                [
                    'type' => 'info',
                    'message' => _t('BAZ_IL_Y_A').' 0 '. _t('BAZ_FICHE')
                ]
            );
        }
        return $this->wiki->Action('bazarliste', 0, $params);
    }
}
