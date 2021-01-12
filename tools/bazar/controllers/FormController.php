<?php

namespace YesWiki\Bazar\Controller;

use YesWiki\Bazar\Service\FormManager;
use YesWiki\Bazar\Service\Guard;
use YesWiki\Core\YesWikiController;

class FormController extends YesWikiController
{
    protected $formManager;

    public function __construct(FormManager $formManager)
    {
        $this->formManager = $formManager;
    }

    public function displayAll($message)
    {
        $forms = $this->formManager->getAll();

        // If there are forms to import
        if (isset($_POST['imported-form'])) {
            foreach ($_POST['imported-form'] as $id => $value) {
                $value = json_decode($value, true);
                $existingForms = multiArraySearch($forms, 'bn_label_nature', $value['bn_label_nature']);
                // If a form with the same name exist, replace it
                if (count($existingForms) > 0) {
                    // Replace with ID of existing formulaire
                    $value['bn_id_nature'] = $existingForms[0]['bn_id_nature'];
                    $this->formManager->update($value);
                } else {
                    $value['bn_id_nature'] = $id;
                    $this->formManager->create($value);
                }
            }

            return $this->wiki->redirect($this->wiki->href('', '', ['vue' => 'formulaire', 'msg' => 'BAZ_FORM_IMPORT_SUCCESSFULL'], false));
        }

        $values = [];
        if (is_array($forms)) {
            foreach ($forms as $form) {
                $values[$form['bn_id_nature']]['title'] = $form['bn_label_nature'];
                $values[$form['bn_id_nature']]['description'] = $form['bn_description'];
                $values[$form['bn_id_nature']]['canEdit'] = $this->getService(Guard::class)->isAllowed('saisie_formulaire');
                $values[$form['bn_id_nature']]['canDelete'] = $this->wiki->UserIsAdmin();
                $values[$form['bn_id_nature']]['isSemantic'] = isset($form['bn_sem_type']) && $form['bn_sem_type'] !== "";
            }
        }

        return $this->render("@bazar/forms/forms_table.twig", [
            'message' => $message,
            'forms' => $values,
            'loggedUser' => $this->wiki->GetUser()
        ]);
    }

    public function create()
    {
        if( isset($_POST['valider']) ) {
            $this->formManager->create($_POST);

            return $this->wiki->redirect($this->wiki->href('', '', ['vue' => 'formulaire', 'msg' => 'BAZ_NOUVEAU_FORMULAIRE_ENREGISTRE'], false));
        }

        return $this->render("@bazar/forms/forms_form.twig", [
            'formAndListIds' => baz_forms_and_lists_ids()
        ]);
    }

    public function update($id)
    {
        if( isset($_POST['valider']) ) {
            $this->formManager->update($_POST);

            return $this->wiki->redirect($this->wiki->href('', '', ['vue' => 'formulaire', 'msg' => 'BAZ_FORMULAIRE_MODIFIE'], false));
        }

        return $this->render("@bazar/forms/forms_form.twig", [
            'form' => $this->formManager->getOne($id),
            'formAndListIds' => baz_forms_and_lists_ids()
        ]);
    }

    public function delete($id)
    {
        $this->formManager->delete($id);

        return $this->wiki->redirect($this->wiki->href('', '', ['vue' => 'formulaire', 'msg' => 'BAZ_FORMULAIRE_ET_FICHES_SUPPRIMES'], false));
    }

    public function empty($id)
    {
        $this->formManager->clear($id);

        return $this->wiki->redirect($this->wiki->href('', '', ['vue' => 'formulaire', 'msg' => 'BAZ_FORMULAIRE_VIDE'], false));
    }
}
