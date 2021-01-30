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
        TemplateEngine $templateEngine,
        ParameterBagInterface $config
    ) {
        $this->entryManager = $entryManager;
        $this->formManager = $formManager;
        $this->aclService = $aclService;
        $this->semanticTransformer = $semanticTransformer;
        $this->pageManager = $pageManager;
        $this->templateEngine = $templateEngine;
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

        $customTemplateValues = $this->getValuesForCustomTemplate($entry, $form);
        $renderedEntry = '';

        // Try rendering a custom template
        try {
            $customTemplateName = $this->getCustomTemplateName($entry);
            $renderedEntry = $this->templateEngine->render("@bazar/$customTemplateName", $customTemplateValues);
        } catch (\YesWiki\Core\Service\TemplateNotFound $e) {
            // No template found, ignore
        }

        // if not found, try rendering a semantic template
        if (empty($renderedEntry) && !empty($customTemplateValues['html']['semantic'])) {
            try {
                $customTemplateName = $this->getCustomSemanticTemplateName($customTemplateValues['html']['semantic']);
                if ($customTemplateName) {
                    $renderedEntry = $this->templateEngine->render("@bazar/$customTemplateName", $customTemplateValues);
                }
            } catch (\YesWiki\Core\Service\TemplateNotFound $e) {
                // No template found, ignore
            }
        }

        // If not found, use default template
        if (empty($renderedEntry)) {
            for ($i = 0; $i < count($form['template']); ++$i) {
                // Check if we should display the field
                if (empty($form['prepared'][$i]->getReadAccess()) ||
                    $this->aclService->check($form['prepared'][$i]->getReadAccess(), null, true,
                        $entryId)) {
                    if ($form['prepared'][$i] instanceof BazarField) {
                        // TODO handle html_outside_app mode for images
                        $renderedEntry .= $form['prepared'][$i]->renderStatic($entry);
                    } else {
                        $functionName = $form['template'][$i][0];
                        if (function_exists($functionName)) {
                            $renderedEntry .= $functionName(
                                $formtemplate,
                                $form['template'][$i],
                                'html',
                                $entry
                            );
                        }
                    }
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
            // TODO Once the integration of login-sso is done, replace the $this->pageManager->getOne with the proper fonction
            if (!empty($this->config['sso_config']) && isset($this->config['sso_config']['bazar_user_entry_id']) && $this->pageManager->getOne($owner)) {
                $owner = $this->wiki->Format('[[' . $this->wiki->GetPageOwner($entryId) . ' ' . $this->wiki->GetPageOwner($entryId) . ']]');
            }
        }

        return $this->render('@bazar/entries/view.twig', [
            "form" => $form,
            "entry" => $entry,
            "entryId" => $entryId,
            "owner" => $owner,
            "message" => $_GET['message'] ?? null,
            "showOwner" => $showOwner,
            "showFooter" => $showFooter && $this->aclService->hasAccess('write', $entryId),
            "canDelete" => $this->wiki->UserIsAdmin() or $this->wiki->UserIsOwner(),
            "renderedEntry" => $renderedEntry,
            "absoluteUrl" => getAbsoluteUrl()
        ]);
    }

    public function create($formId, $redirectUrl = null)
    {
        $form = $this->formManager->getOne($formId);

        if (isset($_POST['bf_titre'])) {
            $entry = $this->entryManager->create($formId, $_POST);
            if (empty($redirectUrl)) {
                $redirectUrl = $this->wiki->Href('', '',
                    ['vue' => 'consulter', 'action' => 'voir_fiche', 'id_fiche' => $entry['id_fiche']], false);
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
        header('Location: ' . $this->wiki->Href('', 'BazaR', ['vue' => 'consulter', 'message' => 'delete_ok']));
    }

    private function getRenderedInputs($form, $entry = null)
    {
        $renderedFields = [];
        for ($i = 0; $i < count($form['prepared']); ++$i) {
            if ($form['prepared'][$i] instanceof BazarField) {
                $renderedFields[] = $form['prepared'][$i]->renderInputIfPermitted($entry);
            } else {
                if (function_exists($form['template'][$i][0])) {
                    $renderedFields[] = $form['template'][$i][0]($formtemplate, $form['template'][$i], 'saisie',
                        $entry);
                }
            }
        }
        return $renderedFields;
    }

    private function getCustomTemplateName($entry)
    {
        return "fiche-{$entry['id_typeannonce']}.tpl.html";
    }

    private function getCustomSemanticTemplateName($semanticData)
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
                return $dir_name . "/" . strtolower($type) . ".tpl.html";
            }
        }

        return null;
    }

    private function getValuesForCustomTemplate($entry, $form)
    {
        $html = $formtemplate = [];
        for ($i = 0; $i < count($form['template']); ++$i) {
            if (empty($form['template'][$i][11]) ||
                $this->aclService->check($form['template'][$i][11], null, true, $entry['id_fiche'])) {
                if ($form['prepared'][$i] instanceof BazarField) {
                    $id = $form['prepared'][$i]->getPropertyName();
                    $html[$id] = $form['prepared'][$i]->renderStatic($entry);
                } else {
                    if (function_exists($form['template'][$i][0])) {
                        $id = $form['template'][$i][1];
                        $html[$id] = $form['template'][$i][0]($formtemplate, $form['template'][$i], 'html', $entry);
                    }
                }
                preg_match_all('/<span class="BAZ_texte">\s*(.*)\s*<\/span>/is', $html[$id], $matches);
                if (isset($matches[1][0]) && $matches[1][0] != '') {
                    $html[$id] = $matches[1][0];
                }
            }
        }

        try {
            $html['semantic'] = $this->semanticTransformer->convertToSemanticData($form['bn_id_nature'], $html,
                true);
        } catch (\Exception $e) {
            // Do nothing if semantic type is not available
        }

        $values['html'] = $html;
        $values['fiche'] = $entry;
        $values['form'] = $form;

        return $values;
    }
}
