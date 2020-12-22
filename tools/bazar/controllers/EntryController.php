<?php

namespace YesWiki\Bazar\Controller;

use YesWiki\Bazar\Field\BazarField;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\YesWikiController;

class EntryController extends YesWikiController
{
    protected $entryManager;
    protected $formManager;

    public function __construct(EntryManager $entryManager, FormManager $formManager)
    {
        $this->entryManager = $entryManager;
        $this->formManager = $formManager;
    }

    public function selectForm()
    {
        $forms = $this->formManager->getAll();

        return $this->render("@bazar/entries/select_form.twig", ['forms' => $forms]);
    }

    public function create($formId, $redirectUrl = null)
    {
        $form = $this->formManager->getOne($formId);

        if( isset($_POST['bf_titre']) ) {
            $entry = $this->entryManager->create($formId, $_POST);
            if (empty($redirectUrl)) {
                $redirectUrl = $this->wiki->Href('', '', ['vue' => 'consulter', 'action' => 'voir_fiche', 'id_fiche' => $entry['id_fiche']], false);
            }
            header('Location: ' . $redirectUrl);
            exit;
        }

        return $this->render("@bazar/entries/form.twig", [
            'form' => $form,
            'renderedFields' => $this->getRenderedFields($form),
            'showConditions' => $form['bn_condition'] !== '' && !isset($_POST['accept_condition']),
            'passwordForEditing' => isset($this->wiki->config['password_for_editing']) && !empty($this->config['password_for_editing']) && isset($_POST['password_for_editing']) ? $_POST['password_for_editing'] : ''
        ]);
    }

    public function update($entryId)
    {
        $entry = $this->entryManager->getOne($entryId);
        $form = $this->formManager->getOne($entry['id_typeannonce']);

        if( isset($_POST['bf_titre']) ) {
            $entry = $this->entryManager->update($entryId, $_POST);
            if (empty($redirectUrl)) {
                $redirectUrl = $this->wiki->Href(testUrlInIframe(), '', ['vue' => 'consulter', 'action' => 'voir_fiche', 'id_fiche' => $entry['id_fiche'], 'message' => 'modif_ok'], false);
            }
            header('Location: ' . $redirectUrl);
            exit;
        }

        return $this->render("@bazar/entries/form.twig", [
            'form' => $form,
            'entryId' => $entryId,
            'renderedFields' => $this->getRenderedFields($form, $entry),
            'showConditions' => false,
            'passwordForEditing' => isset($this->wiki->config['password_for_editing']) && !empty($this->config['password_for_editing']) && isset($_POST['password_for_editing']) ? $_POST['password_for_editing'] : ''
        ]);
    }

    public function delete($entryId)
    {
        $this->entryManager->delete($entryId);
        header('Location: '.$this->wiki->Href('', 'BazaR', ['vue' => 'consulter', 'message' => 'delete_ok']));
    }

    private function getRenderedFields($form, $entry = null)
    {
        $renderedFields = [];
        for ($i = 0; $i < count($form['prepared']); ++$i) {
            if( $form['prepared'][$i] instanceof BazarField ) {
                $renderedFields[] = $form['prepared'][$i]->renderInputIfPermitted($entry);
            } else if (function_exists($form['template'][$i][0])){
                $renderedFields[] = $form['template'][$i][0]($formtemplate, $form['template'][$i], 'saisie', $entry);
            }
        }
        return $renderedFields;
    }
}
