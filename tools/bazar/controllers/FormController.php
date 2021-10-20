<?php

namespace YesWiki\Bazar\Controller;

use YesWiki\Bazar\Field\MapField;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Bazar\Service\Guard;
use YesWiki\Core\YesWikiController;
use YesWiki\Security\Controller\SecurityController;

class FormController extends YesWikiController
{
    protected $formManager;
    protected $securityController;

    private const LANG = [
            'BAZ_FORM_EDIT_YES',
            'BAZ_FORM_EDIT_NO',
        ];

    public function __construct(FormManager $formManager, SecurityController $securityController)
    {
        $this->formManager = $formManager;
        $this->securityController = $securityController;
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
                $values[$form['bn_id_nature']]['canEdit'] = !$this->securityController->isWikiHibernated() && $this->getService(Guard::class)->isAllowed('saisie_formulaire');
                $values[$form['bn_id_nature']]['canDelete'] = !$this->securityController->isWikiHibernated() &&$this->wiki->UserIsAdmin();
                $values[$form['bn_id_nature']]['isSemantic'] = isset($form['bn_sem_type']) && $form['bn_sem_type'] !== "";
                $values[$form['bn_id_nature']]['isGeo'] = !empty(array_filter($form['prepared'], function ($field) {
                    return ($field instanceof MapField);
                }));
                $values[$form['bn_id_nature']]['isDate'] = $this->getService(IcalFormatter::class)->isICALForm($form);
            }
        }

        return $this->render("@bazar/forms/forms_table.twig", [
            'message' => $message,
            'forms' => $values,
            'userIsAdmin' => $this->wiki->UserIsAdmin(),
            'isWikiHibernated' => $this->securityController->isWikiHibernated()
        ]);
    }

    public function create()
    {
        if ($this->wiki->UserIsAdmin()) {
            if (isset($_POST['valider'])) {
                $this->formManager->create($_POST);

                return $this->wiki->redirect($this->wiki->href('', '', ['vue' => 'formulaire', 'msg' => 'BAZ_NOUVEAU_FORMULAIRE_ENREGISTRE'], false));
            }

            return $this->render("@bazar/forms/forms_form.twig", [
                'formAndListIds' => baz_forms_and_lists_ids(),
                'groupsList' => $this->getGroupsListIfEnabled(),
                'lang' => $this->getLang()
            ]);
        } else {
            return $this->wiki->redirect($this->wiki->href('', '', ['vue' => 'formulaire', 'msg' => 'BAZ_AUTH_NEEDED'], false));
        }
    }

    public function update($id)
    {
        if ($this->getService(Guard::class)->isAllowed('saisie_formulaire')) {
            if (isset($_POST['valider'])) {
                $this->formManager->update($_POST);

                return $this->wiki->redirect($this->wiki->href('', '', ['vue' => 'formulaire', 'msg' => 'BAZ_FORMULAIRE_MODIFIE'], false));
            }

            return $this->render("@bazar/forms/forms_form.twig", [
                'form' => $this->formManager->getOne($id),
                'formAndListIds' => baz_forms_and_lists_ids(),
                'groupsList' => $this->getGroupsListIfEnabled(),
                'lang' => $this->getLang()
            ]);
        } else {
            return $this->wiki->redirect($this->wiki->href('', '', ['vue' => 'formulaire', 'msg' => 'BAZ_NEED_ADMIN_RIGHTS'], false));
        }
    }

    public function delete($id)
    {
        if ($this->wiki->UserIsAdmin()) {
            $this->formManager->clear($id);
            $this->formManager->delete($id);

            return $this->wiki->redirect($this->wiki->href('', '', ['vue' => 'formulaire', 'msg' => 'BAZ_FORMULAIRE_ET_FICHES_SUPPRIMES'], false));
        } else {
            return $this->wiki->redirect($this->wiki->href('', '', ['vue' => 'formulaire', 'msg' => 'BAZ_NEED_ADMIN_RIGHTS'], false));
        }
    }

    public function empty($id)
    {
        if ($this->wiki->UserIsAdmin()) {
            $this->formManager->clear($id);

            return $this->wiki->redirect($this->wiki->href('', '', ['vue' => 'formulaire', 'msg' => 'BAZ_FORMULAIRE_VIDE'], false));
        } else {
            return $this->wiki->redirect($this->wiki->href('', '', ['vue' => 'formulaire', 'msg' => 'BAZ_NEED_ADMIN_RIGHTS'], false));
        }
    }

    public function clone($id)
    {
        if ($this->getService(Guard::class)->isAllowed('saisie_formulaire')) {
            $this->formManager->clone($id);

            return $this->wiki->redirect($this->wiki->href('', '', ['vue' => 'formulaire', 'msg' => 'BAZ_FORM_CLONED'], false));
        } else {
            return $this->wiki->redirect($this->wiki->href('', '', ['vue' => 'formulaire', 'msg' => 'BAZ_AUTH_NEEDED'], false));
        }
    }

    private function getGroupsListIfEnabled(): ?array
    {
        return $this->wiki->UserIsAdmin()
            ? $this->wiki->GetGroupsList()
            : null;
    }

    private function getLang(): array
    {
        $lang = [];
        foreach (self::LANG as $name) {
            $lang[$name] = _t($name);
        }
        return $lang;
    }
}
