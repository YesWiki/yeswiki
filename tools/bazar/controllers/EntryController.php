<?php

namespace YesWiki\Bazar\Controller;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Bazar\Field\BazarField;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Bazar\Service\SemanticTransformer;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\TemplateEngine;
use YesWiki\Core\YesWikiController;

class EntryController extends YesWikiController
{
    protected $entryManager;
    protected $formManager;
    protected $aclService;
    protected $semanticTransformer;
    protected $pageManager;
    protected $templateEngine;
    protected $config;

    public function __construct(
        EntryManager $entryManager,
        FormManager $formManager,
        AclService $aclService,
        SemanticTransformer $semanticTransformer,
        PageManager $pageManager,
        ParameterBagInterface $config
    ) {
        $this->entryManager = $entryManager;
        $this->formManager = $formManager;
        $this->aclService = $aclService;
        $this->semanticTransformer = $semanticTransformer;
        $this->pageManager = $pageManager;
        $this->config = $config->all();
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
            foreach ($form['prepared'] as $field) {
                if ($field instanceof BazarField) {
                    // TODO handle html_outside_app mode for images
                    $renderedEntry .= $field->renderStaticIfPermitted($entry);
                }
            }
        }

        // fake ->tag for the attached images
        $this->wiki->tag = $oldPageTag;

        $showOwner = false;
        $owner = $this->wiki->GetPageOwner($entryId);

        // If owner is not an IP address
        if ($owner != '' && $owner != 'WikiAdmin' && preg_replace('/([0-9]|\.)/', '', $owner) != '') {
            $showOwner = true;
            // Make the user name clickable when the parameter 'bazar_user_entry_id' is defined in the config file and a corresponding bazar entry exists
            // TODO Once the integration of login-sso is done, replace $this->pageManager->getOne with the proper fonction
            if (!empty($this->config['sso_config']) && isset($this->config['sso_config']['bazar_user_entry_id']) && $this->pageManager->getOne($owner)) {
                $owner = $this->wiki->Format('[[' . $this->wiki->GetPageOwner($entryId) . ' ' . $this->wiki->GetPageOwner($entryId) . ']]');
            }
        }

        return $this->render('@bazar/entries/view.twig', [
            "form" => $form,
            "entry" => $entry,
            "entryId" => $entryId,
            "owner" => $owner,
            "message" => $_GET['message'] ?? '',
            "showOwner" => $showOwner,
            "showFooter" => $showFooter,
            "canEdit" =>  $this->aclService->hasAccess('write', $entryId),
            "canDelete" => $this->wiki->UserIsAdmin() or $this->wiki->UserIsOwner(),
            "renderedEntry" => $renderedEntry,
            "incomingUrl" => $_GET['incomingurl'] ?? getAbsoluteUrl()
        ]);
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
            if ($this->wiki->services->get(TemplateEngine::class)->hasTemplate($templatePath)) {
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
                return $this->wiki->services->get(TemplateEngine::class)->hasTemplate($templatePath) ? $templatePath : null;
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
                if (!empty($id)) {
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
}
