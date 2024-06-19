<?php

namespace YesWiki\Bazar\Controller;

use DateInterval;
use DateTime;
use Exception;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Throwable;
use YesWiki\Bazar\Exception\UserFieldException;
use YesWiki\Bazar\Field\BazarField;
use YesWiki\Bazar\Field\UserField;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Bazar\Service\SemanticTransformer;
use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\EventDispatcher;
use YesWiki\Core\Service\FavoritesManager;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\TemplateEngine;
use YesWiki\Core\YesWikiController;
use YesWiki\Security\Controller\SecurityController;

class EntryController extends YesWikiController
{
    protected $aclService;
    protected $authController;
    protected $config;
    protected $entryManager;
    protected $eventDispatcher;
    protected $favoritesManager;
    protected $formManager;
    protected $pageManager;
    protected $securityController;
    protected $semanticTransformer;
    protected $templateEngine;

    private $parentsEntries;

    public function __construct(
        AclService $aclService,
        AuthController $authController,
        EntryManager $entryManager,
        EventDispatcher $eventDispatcher,
        FavoritesManager $favoritesManager,
        FormManager $formManager,
        PageManager $pageManager,
        ParameterBagInterface $config,
        SecurityController $securityController,
        SemanticTransformer $semanticTransformer
    ) {
        $this->aclService = $aclService;
        $this->authController = $authController;
        $this->config = $config->all();
        $this->entryManager = $entryManager;
        $this->eventDispatcher = $eventDispatcher;
        $this->favoritesManager = $favoritesManager;
        $this->formManager = $formManager;
        $this->pageManager = $pageManager;
        $this->parentsEntries = [];
        $this->securityController = $securityController;
        $this->semanticTransformer = $semanticTransformer;
    }

    /**
     * @param array $formsIds (empty = all)
     *
     * @return string
     */
    public function selectForm(array $formsIds = [])
    {
        $formsIds = array_filter($formsIds, function ($formId) {
            return strval($formId) === strval(intval($formId));
        });
        if (empty($formsIds)) {
            $forms = $this->formManager->getAll();
        } else {
            $forms = $this->formManager->getMany($formsIds);
        }

        return $this->render('@bazar/entries/select_form.twig', ['forms' => $forms]);
    }

    /**
     * @param string      $entryId
     * @param string|null $time                 choose only the entry's revision corresponding to time, null = latest revision
     * @param bool        $showFooter
     * @param string|null $userNameForRendering userName used to render the entry, if empty uses the connected user
     */
    public function view($entryId, $time = '', $showFooter = true, ?string $userNameForRendering = null)
    {
        if (is_array($entryId)) {
            // If entry ID is the full entry with all the values
            $entry = $entryId;
            $entryId = $entry['id_fiche'];
        } elseif ($entryId) {
            $entry = $this->entryManager->getOne($entryId, false, $time, empty($userNameForRendering), false, $userNameForRendering);
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
        $message = $_GET['message'] ?? '';
        // unset $_GET['message'] to prevent infinite loop when rendering entry with textarea and {{bazarliste}}
        unset($_GET['message']);
        // to synchronize with const in BazarAction (but do not include it here otherwise include shunts Performer job)
        $isUpdatingEntry = (isset($_GET['vue']) && $_GET['vue'] === 'consulter');
        if ($isUpdatingEntry) {
            unset($_GET['vue']);
        }
        // unshift stack to check if this entry is included into a bazarliste into a Field
        array_unshift($this->parentsEntries, $entryId);
        if (
            count(array_filter($this->parentsEntries, function ($value) use ($entryId) {
                return $value === $entryId;
            })) < 3 // max 3 levels
        ) {
            // use a custom template if exists (fiche-FORM_ID.tpl.html or fiche-FORM_ID.twig)
            $customTemplatePath = $this->getCustomTemplatePath($entry);
            if ($customTemplatePath) {
                $customTemplateValues = $this->getValuesForCustomTemplate($entry, $form, $userNameForRendering);
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
                            if (!in_array($field->getPropertyName(), $this->fieldsToExclude())) {
                                $renderedEntry .= $field->renderStaticIfPermitted($entry, $userNameForRendering);
                            }
                        }
                    }
                } else {
                    $renderedEntry = $this->render(
                        '@templates/alert-message.twig',
                        [
                            'type' => 'info',
                            'message' => str_replace('{{nb}}', $entry['id_typeannonce'], _t('BAZ_PAS_DE_FORM_AVEC_ID_DE_CETTE_FICHE')),
                        ]
                    );
                }
            }
        }

        // fake ->tag for the attached images
        $this->wiki->tag = $oldPageTag;
        // shift stack
        array_shift($this->parentsEntries);

        // Format owner
        $owner = $this->wiki->GetPageOwner($entryId) ?? $this->wiki->GetUserName();
        $isOwnerIpAddress = preg_replace('/([0-9]|\.)/', '', $owner) == '';
        if ($isOwnerIpAddress || !$owner) {
            $owner = _t('BAZ_UNKNOWN_USER');
        }
        if (!empty($this->config['sso_config']) && isset($this->config['sso_config']['bazar_user_entry_id']) && $this->pageManager->getOne($owner)) {
            $owner = $this->wiki->Format('[[' . $this->wiki->GetPageOwner($entryId) . ' ' . $this->wiki->GetPageOwner($entryId) . ']]');
        }

        // remake $_GET['message'] for BazarAction__ like in webhooks extension
        if (!empty($message)) {
            $_GET['message'] = $message;
        }
        if ($isUpdatingEntry) {
            $_GET['vue'] = 'consulter';
        }

        $user = $this->authController->getLoggedUser();
        if (!empty($user) && $this->favoritesManager->areFavoritesActivated() && (testUrlInIframe() == 'iframe')) {
            $currentuser = $user['name'];
            $isUserFavorite = $this->favoritesManager->isUserFavorite($currentuser, $entryId);
        }

        return $this->render('@bazar/entries/view.twig', [
            'form' => $form,
            'entry' => $entry,
            'entryId' => $entryId,
            'owner' => $owner,
            'message' => $message,
            'showFooter' => $showFooter,
            'currentuser' => $currentuser ?? null,
            'isUserFavorite' => $isUserFavorite ?? false,
            'canShow' => $this->wiki->GetPageTag() != $entry['id_fiche'], // hide if we are already in the show page
            'canEdit' => !$this->securityController->isWikiHibernated() && $this->aclService->hasAccess('write', $entryId),
            'canDelete' => !$this->securityController->isWikiHibernated() && ($this->wiki->UserIsAdmin($userNameForRendering) || $this->wiki->UserIsOwner($entryId)),
            'isAdmin' => $this->wiki->UserIsAdmin($userNameForRendering),
            'renderedEntry' => $renderedEntry,
            'incomingUrl' => $_GET['incomingurl'] ?? getAbsoluteUrl(),
        ]);
    }

    private function fieldsToExclude()
    {
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

        $results = $this->checkIfOnlyOneEntry($form);
        $incomingUrl = $this->getIncomingUrl();
        if (!empty($results['output'])) {
            return $results['output'];
        } elseif (empty($results['error'])) {
            list($state, $error) = $this->securityController->checkCaptchaBeforeSave('entry');
            try {
                if ($state && isset($_POST['bf_titre'])) {
                    $entry = $this->entryManager->create($formId, $_POST);
                    $errors = $this->eventDispatcher->yesWikiDispatch('entry.created', [
                        'id' => $entry['id_fiche'],
                        'data' => $entry,
                    ]);
                    // get the GET parameter 'incomingurl' for the incoming url
                    $redirectUrl = !empty($incomingUrl)
                        ? $incomingUrl
                        : (
                            !empty($redirectUrl)
                            ? $redirectUrl
                            : $this->wiki->Href(
                                testUrlInIframe(),
                                '',
                                [
                                    'vue' => 'consulter',
                                    'action' => 'voir_fiche',
                                    'id_fiche' => $entry['id_fiche'],
                                    'message' => 'ajout_ok',
                                ],
                                false
                            )
                        );
                    header('Location: ' . $redirectUrl);
                    $this->wiki->exit();
                }
            } catch (UserFieldException $e) {
                $error .= $this->render('@templates/alert-message.twig', [
                    'type' => 'warning',
                    'message' => $e->getMessage(),
                ]);
            }
        } else {
            $error = $results['error'];
        }

        $renderedInputs = $this->getRenderedInputs($form);

        return $this->render('@bazar/entries/form.twig', [
            'form' => $form,
            'renderedInputs' => $renderedInputs,
            'showConditions' => $form['bn_condition'] !== '' && !isset($_POST['accept_condition']),
            'passwordForEditing' => isset($this->config['password_for_editing']) && !empty($this->config['password_for_editing']) && isset($_POST['password_for_editing']) ? $_POST['password_for_editing'] : '',
            'incomingUrl' => $incomingUrl,
            'error' => $error,
            'captchaField' => $this->securityController->renderCaptchaField(),
            'imageSmallWidth' => $this->config['image-small-width'],
            'imageSmallHeight' => $this->config['image-small-height'],
            'imageMediumWidth' => $this->config['image-medium-width'],
            'imageMediumHeight' => $this->config['image-medium-height'],
            'imageBigWidth' => $this->config['image-big-width'],
            'imageBigHeight' => $this->config['image-big-height'],
        ]);
    }

    public function update($entryId)
    {
        $entry = $this->entryManager->getOne($entryId);
        $form = $this->formManager->getOne($entry['id_typeannonce']);

        list($state, $error) = $this->securityController->checkCaptchaBeforeSave('entry');
        $incomingUrl = $this->getIncomingUrl();
        try {
            if ($state && isset($_POST['bf_titre'])) {
                $entry = $this->entryManager->update($entryId, $_POST);
                $errors = $this->eventDispatcher->yesWikiDispatch('entry.updated', [
                    'id' => $entry['id_fiche'],
                    'data' => $entry,
                ]);
                $redirectUrl = !empty($incomingUrl)
                    ? $incomingUrl
                    : (
                        !empty($redirectUrl)
                        ? $redirectUrl
                        : $this->wiki->Href(testUrlInIframe(), '', [
                            'vue' => 'consulter',
                            'action' => 'voir_fiche',
                            'id_fiche' => $entry['id_fiche'],
                            'message' => 'modif_ok',
                        ], false)
                    );
                header('Location: ' . $redirectUrl);
                $this->wiki->exit();
            }
        } catch (UserFieldException $e) {
            $error .= $this->render('@templates/alert-message.twig', [
                'type' => 'warning',
                'message' => $e->getMessage(),
            ]);
        }

        $renderedInputs = $this->getRenderedInputs($form, $entry);

        return $this->render('@bazar/entries/form.twig', [
            'form' => $form,
            'entryId' => $entryId,
            'renderedInputs' => $renderedInputs,
            'showConditions' => false,
            'passwordForEditing' => isset($this->config['password_for_editing']) && !empty($this->config['password_for_editing']) && isset($_POST['password_for_editing']) ? $_POST['password_for_editing'] : '',
            'incomingUrl' => $incomingUrl,
            'error' => $error,
            'captchaField' => $this->securityController->renderCaptchaField(),
            'imageSmallWidth' => $this->config['image-small-width'],
            'imageSmallHeight' => $this->config['image-small-height'],
            'imageMediumWidth' => $this->config['image-medium-width'],
            'imageMediumHeight' => $this->config['image-medium-height'],
            'imageBigWidth' => $this->config['image-big-width'],
            'imageBigHeight' => $this->config['image-big-height'],
        ]);
    }

    public function delete($entryId, bool $redirectAfter = false): bool
    {
        if ($this->entryManager->isEntry($entryId)) {
            try {
                $entry = $this->entryManager->getOne($entryId);
                $this->entryManager->delete($entryId);
                if (!$this->entryManager->isEntry($entryId)) {
                    $this->triggerDeletedEvent($entryId, $entry);
                    if ($redirectAfter) {
                        flash(_t('BAZ_FICHE_SUPPRIMEE') . " ($entryId)", 'success');
                        $this->wiki->Redirect($this->wiki->Href('', 'BazaR', ['vue' => 'consulter'], false));
                    }

                    return true;
                }
            } catch (Throwable $th) {
                if ($redirectAfter) {
                    flash(_t('DELETEPAGE_NOT_DELETED') . " ($entryId) : {$th->getMessage()}", 'error');
                    $this->wiki->Redirect($this->wiki->Href('', 'BazaR', ['vue' => 'consulter'], false));
                }
                throw new Exception($th->getMessage(), $th->getCode(), $th);
            }

            return false;
        } else {
            throw new Exception('Not deleted because not entry' . (is_scalar($entryId) ? ' (' . strval($entryId) . ')' : ''));
        }
    }

    protected function triggerDeletedEvent($entryId, $entry)
    {
        return $this->eventDispatcher->yesWikiDispatch('entry.deleted', [
            'id' => $entryId,
            'data' => $entry,
        ]);
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
            "@bazar/fiche-{$entry['id_typeannonce']}.twig",
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
                $templatePath = $dir_name . '/' . strtolower($type) . '.tpl.html';

                return $this->getService(TemplateEngine::class)->hasTemplate($templatePath) ? $templatePath : null;
            }
        }

        return null;
    }

    /**
     * @param array       $entry
     * @param array|null  $form
     * @param string|null $userNameForRendering userName used to render the entry, if empty uses the connected user
     */
    private function getValuesForCustomTemplate($entry, $form, ?string $userNameForRendering = null)
    {
        $html = [];
        foreach ($form['prepared'] as $field) {
            if ($field instanceof BazarField) {
                $id = $field->getPropertyName();
                if (!empty($id) && !in_array($id, $this->fieldsToExclude())) {
                    $html[$id] = $field->renderStaticIfPermitted($entry, $userNameForRendering);
                    // reset $matches before preg_match
                    $matches = [];
                    if ($id == 'bf_titre') {
                        preg_match('/<h1 class="BAZ_fiche_titre">\s*(.*)\s*<\/h1>.*$/is', $html[$id], $matches);
                    } elseif (!empty($html[$id])) {
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
     * format queries form GET and from $arg in order to give the right 'queries' to EntryManager->search.
     *
     * @param array|string|null $arg
     * @param array             $get (copy of $_GET) but pass in parameters to be more visible in primary level controllers
     */
    public function formatQuery($arg, array $get): array
    {
        $queryArray = [];

        // Aggregate argument and $get values
        if (isset($get['query'])) {
            if (!empty($arg['query'])) {
                if (is_array($arg['query'])) {
                    $queryArray = $arg['query'];
                    $query = $get['query'];
                } else {
                    $query = $arg['query'] . '|' . $get['query'];
                }
            } else {
                $query = $get['query'];
            }
        } else {
            if (isset($arg['query']) && is_array($arg['query'])) {
                $queryArray = $arg['query'];
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
                    $queryArray[$res2[0]] = $queryArray[$res2[0]] . ',' . trim($res2[1] ?? '');
                } else {
                    $queryArray[$res2[0]] = trim($res2[1] ?? '');
                }
            }
        }

        return $queryArray;
    }

    /* PART TO FILTER ON DATE */

    /**
     * filter entries on date.
     *
     * @param array  $entries
     * @param string $datefilter
     *
     * @return array $entries
     */
    public function filterEntriesOnDate($entries, $datefilter): array
    {
        $TODAY_TEMPLATE = '/^(today|aujourdhui|=0(D)?)$/i';
        $FUTURE_TEMPLATE = '/^(futur|future|>0(D)?)$/i';
        $PAST_TEMPLATE = '/^(past|passe|<0(D)?)$/i';
        $DATE_TEMPLATE = "(\+|-)(([0-9]+)Y)?(([0-9]+)M)?(([0-9]+)D)?";
        $EQUAL_TEMPLATE = '/^=' . $DATE_TEMPLATE . '$/i';
        $MORE_TEMPLATE = '/^>' . $DATE_TEMPLATE . '$/i';
        $LOWER_TEMPLATE = '/^<' . $DATE_TEMPLATE . '$/i';
        $BETWEEN_TEMPLATE = '/^>' . $DATE_TEMPLATE . '&<' . $DATE_TEMPLATE . '$/i';

        if (preg_match_all($TODAY_TEMPLATE, $datefilter, $matches)) {
            $todayMidnigth = new DateTime();
            $todayMidnigth->setTime(0, 0);
            $entries = array_filter($entries, function ($entry) use ($todayMidnigth) {
                return $this->filterEntriesOnDateTraversing($entry, '=', $todayMidnigth);
            });
        } elseif (preg_match_all($FUTURE_TEMPLATE, $datefilter, $matches)) {
            $now = new DateTime();
            $entries = array_filter($entries, function ($entry) use ($now) {
                return $this->filterEntriesOnDateTraversing($entry, '>', $now);
            });
        } elseif (preg_match_all($PAST_TEMPLATE, $datefilter, $matches)) {
            $now = new DateTime();
            $entries = array_filter($entries, function ($entry) use ($now) {
                return $this->filterEntriesOnDateTraversing($entry, '<', $now);
            });
        } elseif (preg_match_all($EQUAL_TEMPLATE, $datefilter, $matches)) {
            $sign = $matches[1][0];
            $nbYears = $matches[3][0];
            $nbMonth = $matches[5][0];
            $nbDays = $matches[7][0];

            $dateMidnigth = $this->extractDate($sign, $nbYears, $nbMonth, $nbDays);
            $dateMidnigth->setTime(0, 0);
            $entries = array_filter($entries, function ($entry) use ($dateMidnigth) {
                return $this->filterEntriesOnDateTraversing($entry, '=', $dateMidnigth);
            });
        } elseif (preg_match_all($MORE_TEMPLATE, $datefilter, $matches)) {
            $sign = $matches[1][0];
            $nbYears = $matches[3][0];
            $nbMonth = $matches[5][0];
            $nbDays = $matches[7][0];

            $date = $this->extractDate($sign, $nbYears, $nbMonth, $nbDays);
            $entries = array_filter($entries, function ($entry) use ($date) {
                return $this->filterEntriesOnDateTraversing($entry, '>', $date);
            });
        } elseif (preg_match_all($LOWER_TEMPLATE, $datefilter, $matches)) {
            $sign = $matches[1][0];
            $nbYears = $matches[3][0];
            $nbMonth = $matches[5][0];
            $nbDays = $matches[7][0];

            $date = $this->extractDate($sign, $nbYears, $nbMonth, $nbDays);
            $entries = array_filter($entries, function ($entry) use ($date) {
                return $this->filterEntriesOnDateTraversing($entry, '<', $date);
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
                    return $this->filterEntriesOnDateTraversing($entry, '>', $dateMin);
                });
                $entries = array_filter($entries, function ($entry) use ($dateMax) {
                    return $this->filterEntriesOnDateTraversing($entry, '<', $dateMax);
                });
            }
        }

        return $entries;
    }

    private function extractDate(string $sign, string $nbYears, string $nbMonth, string $nbDays): DateTime
    {
        $dateInterval = new DateInterval(
            'P'
                . (!empty($nbYears) ? $nbYears . 'Y' : '')
                . (!empty($nbMonth) ? $nbMonth . 'M' : '')
                . (!empty($nbDays) ? $nbDays . 'D' : (empty($nbYears) && empty($nbMonth) && empty($nbDays) ? '0D' : ''))
        );
        $dateInterval->invert = ($sign == '-') ? 1 : 0;

        $date = new DateTime();
        $date->add($dateInterval);

        return $date;
    }

    private function filterEntriesOnDateTraversing(?array $entry, string $mode, DateTime $date): bool
    {
        if (empty($entry) || !isset($entry['bf_date_debut_evenement'])) {
            return false;
        }

        $entryStartDate = new DateTime($entry['bf_date_debut_evenement']);
        if (isset($entry['bf_date_fin_evenement']) && !empty(trim($entry['bf_date_fin_evenement']))) {
            $entryEndDate = new DateTime($entry['bf_date_fin_evenement']);
            if ($entryEndDate && strpos($entry['bf_date_fin_evenement'], 'T') === false) {
                // all day (so = midnigth of next day)
                $entryEndDate->add(new DateInterval('P1D'));
            }
        }
        if (empty($entryEndDate)) {
            $entryEndDate = (clone $entryStartDate)->setTime(0, 0)->add(new DateInterval('P1D')); // endDate to next day after start day if empty
        }
        $nextDay = (clone $date)->add(new DateInterval('P1D'));
        switch ($mode) {
            case '<':
                // start before date and whatever finish
                return
                    $date->diff($entryStartDate)->invert == 1
                ;
                break;
            case '>':
                // start after date or (before date but and end should be after date, end is needed)
                return
                    $date->diff($entryStartDate)->invert == 0
                    || !$this->dateIsStrictlyBefore($entryEndDate, $date)
                ;
                break;
            case '=':
            default:
                // start before next day midnight and should end after date midnigth
                return
                    $nextDay->diff($entryStartDate)->invert == 1
                    && !$this->dateIsStrictlyBefore($entryEndDate, $date)
                ;
        }
    }

    private function dateIsStrictlyBefore(DateTime $dateToCompare, DateTime $referenceDate): bool
    {
        $diff = $referenceDate->diff($dateToCompare);

        return $diff->invert == 1 || (
            $diff->invert == 0
            && $diff->days == 0
            && $diff->h == 0
            && $diff->i == 0
            && $diff->s == 0
            && $diff->f == 0
        );
    }

    /* END OF PART TO FILTER ON DATE */

    public function renderBazarList($entries, $param = [], $showNumEntries = true)
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
                    'message' => _t('BAZ_IL_Y_A') . ' 0 ' . _t('BAZ_FICHE'),
                ]
            );
        }

        return $this->wiki->Action('bazarliste', 0, $params);
    }

    /**
     * check if creation of entry is authorized for this form.
     *
     * @return array ["error" => string, "output" => string]
     */
    private function checkIfOnlyOneEntry(array $form): array
    {
        $results = [
            'error' => '',
            'output' => '',
        ];
        if (isset($form['bn_only_one_entry']) && $form['bn_only_one_entry'] === 'Y') {
            $formHasUserField = !empty(array_filter($form['prepared'], function ($field) {
                return $field instanceof UserField;
            }));
            $loggerUser = $this->authController->getLoggedUser();
            if (!$formHasUserField && empty($loggerUser)) {
                // forbidden : ask to connect
                $results['output'] = $this->render('@templates/alert-message.twig', [
                    'type' => 'warning',
                    'message' => _t('BAZ_USER_SHOULD_BE_CONNECTED_TO_ACCES_THIS_FORM'),
                ]);
                $pageLogin = $this->pageManager->GetOne('PageLogin');
                $results['output'] .= $this->wiki->format(!empty($pageLogin) ? '{{include page="PageLogin"}}' : '{{login}}');
            } elseif (!empty($loggerUser)) {
                $userName = $loggerUser['name'];
                $entries = $this->entryManager->search([
                    'formsIds' => [$form['bn_id_nature']],
                    'user' => $userName,
                ]);
                if (!empty($entries)) {
                    $firstEntry = $entries[array_keys($entries)[0]];
                    $message = !empty($form['bn_only_one_entry_message']) ? $form['bn_only_one_entry_message'] : _t('BAZ_FORM_DEFAULT_MESSAGE_FOR_OTHER_ENTRY_IN_FORM');
                    $message = str_replace('{formName}', $form['bn_label_nature'], $message);
                    $results['output'] = $this->render('@templates/alert-message.twig', [
                        'type' => 'info',
                        'message' => $message,
                    ]);
                    $results['output'] .= $this->view($firstEntry['id_fiche']);

                    return $results;
                }
            }
        }

        return $results;
    }

    public function getIncomingUrl(): string
    {
        $incomingUrl = (isset($_GET['incomingurl']) && is_string($_GET['incomingurl']))
            ? $_GET['incomingurl']
            : (
                (isset($_POST['incomingurl']) && is_string($_POST['incomingurl']))
                ? $_POST['incomingurl']
                : ''
            );
        if (!empty($incomingUrl)) {
            $incomingUrl = urldecode($incomingUrl);
            $incomingUrl = filter_var($incomingUrl, FILTER_VALIDATE_URL);
        }

        // TODO check if redirect to outside website ?
        return empty($incomingUrl) ? '' : $incomingUrl;
    }
}
