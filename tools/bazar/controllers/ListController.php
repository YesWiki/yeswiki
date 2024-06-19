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
                $this->listManager->create($list['title'], $list['nodes']);
            }
            echo '<div class="alert alert-success">' . _t('BAZ_LIST_IMPORT_SUCCESSFULL') . '.</div>';
            echo '<div class="alert alert-success">' . _t('BAZ_LIST_IMPORT_SUCCESSFULL') . '.</div>';
        }

        $lists = $this->listManager->getAll();

        foreach ($lists as $key => $list) {
            $lists[$key]['canEdit'] = !$this->securityController->isWikiHibernated() && $this->wiki->HasAccess('write', $key);
            $lists[$key]['canDelete'] = !$this->securityController->isWikiHibernated() && ($this->wiki->UserIsAdmin() || $this->wiki->UserIsOwner($key));
        }

        return $this->render('@bazar/lists/list_table.twig', [
            'lists' => $lists,
            'loggedUser' => $this->wiki->GetUser(),
            'canCreate' => !$this->securityController->isWikiHibernated(),
        ]);
    }

    public function create()
    {
        if (isset($_POST['valider'])) {
            $i = 1;
            $nodes = [];
            foreach ($_POST['label'] as $label) {
                if (($label != null || $label != '') && ($_POST['id'][$i] != null || $_POST['id'][$i] != '')) {
                    $nodes[] = ['id' => $_POST['id'][$i], 'label' => $label];
                    $i++;
                }
            }

            $listeId = $this->listManager->create($_POST['title'], $nodes);

            if ($this->shouldPostMessageOnSubmit()) {
                return $this->render('@core/iframe_result.twig', [
                    'data' => ['msg' => 'list_created', 'id' => $listeId, 'title' => $_POST['title']],
                ]);
            }

            $this->wiki->Redirect(
                $this->wiki->Href('', '', [BAZ_VARIABLE_VOIR => BAZ_VOIR_LISTES], false)
            );
        }

        return $this->render('@bazar/lists/list_form.twig');
    }

    private function shouldPostMessageOnSubmit()
    {
        return isset($_GET['onsubmit']) && $_GET['onsubmit'] === 'postmessage';
    }

    public function update($id)
    {
        $list = $this->listManager->getOne($id);

        if (isset($_POST['valider'])) {
            if ($this->wiki->HasAccess('write', $id)) {
                $i = 1;
                $nodes = [];

                foreach ($_POST['label'] as $label) {
                    if (($label != null || $label != '') && ($_POST['id'][$i] != null || $_POST['id'][$i] != '')) {
                        $nodes[] = ['id' => $_POST['id'][$i], 'label' => $label];
                    }
                    $i++;
                }

                $this->listManager->update($id, $_POST['title'], $nodes);

                if ($this->shouldPostMessageOnSubmit()) {
                    return $this->render('@core/iframe_result.twig', [
                        'data' => ['msg' => 'list_updated', 'id' => $id, 'title' => $_POST['title']],
                    ]);
                }

                $this->wiki->Redirect(
                    $this->wiki->Href('', '', [BAZ_VARIABLE_VOIR => BAZ_VOIR_LISTES], false)
                );
            } else {
                throw new \Exception('Not allowed');
            }
        }

        return $this->render('@bazar/lists/list_form.twig', [
            'list' => $list,
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
