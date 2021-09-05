<?php

namespace YesWiki\Bazar\Controller;

use YesWiki\Bazar\Service\ListManager;
use YesWiki\Core\Service\Mailer;
use YesWiki\Core\YesWikiController;
use YesWiki\Security\Controller\SecurityController;

class ListController extends YesWikiController
{
    protected $listManager;
    protected $securityController;

    public function __construct(ListManager $listManager, SecurityController $securityController)
    {
        $this->listManager = $listManager;
        $this->securityController = $securityController;
    }

    public function displayAll()
    {
        if (isset($_POST['imported-list'])) {
            foreach ($_POST['imported-list'] as $listRaw) {
                $list = json_decode($listRaw, true);
                $this->listManager->create($list['titre_liste'], $list['label']);
            }
            echo '<div class="alert alert-success">'._t('BAZ_LIST_IMPORT_SUCCESSFULL').'.</div>';
        }

        $lists = $this->listManager->getAll();

        $values = [];
        foreach ($lists as $key => $list) {
            $values[$key]['title'] = $list['titre_liste'];
            $values[$key]['options'] = $list['label'];
            $values[$key]['canEdit'] = !$this->securityController->isWikiHibernated() && $this->wiki->HasAccess('write', $key);
            $values[$key]['canDelete'] = !$this->securityController->isWikiHibernated() && ($this->wiki->UserIsAdmin() || $this->wiki->UserIsOwner($key));
        }

        return $this->render('@bazar/lists/list_table.twig', [
            'lists' => $values,
            'loggedUser' => $this->wiki->GetUser(),
            'canCreate' =>$this->securityController->isWikiHibernated()
            ]);
    }

    public function create()
    {
        if (isset($_POST['valider'])) {
            $i = 1;
            $values = [];
            foreach ($_POST['label'] as $label) {
                if (($label != null || $label != '') && ($_POST['id'][$i] != null || $_POST['id'][$i] != '')) {
                    $values[$_POST['id'][$i]] = $label;
                    ++$i;
                }
            }
            
            $this->listManager->create($_POST['titre_liste'], $values);

            $this->wiki->Redirect(
                $this->wiki->Href('', '', [BAZ_VARIABLE_VOIR => BAZ_VOIR_LISTES], false)
            );
        }

        return $this->render('@bazar/lists/list_form.twig');
    }

    public function update($id)
    {
        $list = $this->listManager->getOne($id);

        if (isset($_POST['valider'])) {
            if ($this->wiki->HasAccess('write', $id)) {
                $i = 1;
                $values = [];

                foreach ($_POST['label'] as $label) {
                    if (($label != null || $label != '') && ($_POST['id'][$i] != null || $_POST['id'][$i] != '')) {
                        $values[$_POST['id'][$i]] = $label;
                    }
                    ++$i;
                }

                $this->listManager->update($id, $_POST['titre_liste'], $values);

                $this->wiki->Redirect(
                    $this->wiki->Href('', '', [BAZ_VARIABLE_VOIR => BAZ_VOIR_LISTES], false)
                );
            } else {
                throw new \Exception('Not allowed');
            }
        }

        return $this->render('@bazar/lists/list_form.twig', [
            'listId' => $id,
            'title' => $list['titre_liste'],
            'labels' => $list['label']
        ]);
    }
    
    public function delete($id)
    {
        $this->listManager->delete($id);

        if ($this->wiki->config['BAZ_ENVOI_MAIL_ADMIN']) {
            $this->getService(Mailer::class)->notifyAdminsListDeleted($id);
        }

        $this->wiki->Redirect(
            $this->wiki->href('', '', [BAZ_VARIABLE_VOIR => BAZ_VOIR_LISTES], false)
        );
    }
}
