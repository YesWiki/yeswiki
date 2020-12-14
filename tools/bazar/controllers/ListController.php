<?php

namespace YesWiki\Bazar\Controller;

use YesWiki\Bazar\Service\ListManager;
use YesWiki\Core\Service\Mailer;
use YesWiki\Core\YesWikiController;

class ListController extends YesWikiController
{
    public function displayAll()
    {
        $listManager = $this->getService(ListManager::class);

        if (isset($_POST['imported-list'])) {
            foreach ($_POST['imported-list'] as $listRaw) {
                $list = json_decode($listRaw, true);
                $listManager->create($list['titre_liste'], $list['label']);
            }
            echo '<div class="alert alert-success">'._t('BAZ_LIST_IMPORT_SUCCESSFULL').'.</div>';
        }

        $lists = $listManager->getAll();

        $values = [];
        foreach ($lists as $key => $list) {
            $values[$key]['title'] = $list['titre_liste'];
            $values[$key]['options'] = $list['label'];
            $values[$key]['canEdit'] = $GLOBALS['wiki']->HasAccess('write', $key);
            $values[$key]['canDelete'] = $GLOBALS['wiki']->UserIsAdmin() || $GLOBALS['wiki']->UserIsOwner($key);
        }

        return $this->render('@bazar/lists/list_table.twig', [ 'lists' => $values ]);
    }

    public function create() 
    {
        $listManager = $this->getService(ListManager::class);

        if( isset($_POST['valider']) ) {
            $i = 1;
            $values = [];
            foreach ($_POST['label'] as $label) {
                if (($label != null || $label != '') && ($_POST['id'][$i] != null || $_POST['id'][$i] != '')) {
                    $values[$_POST['id'][$i]] = $label;
                    ++$i;
                }
            }
            
            $listManager->create($_POST['titre_liste'], $values);

            $GLOBALS['wiki']->Redirect(
                $GLOBALS['wiki']->Href('', '', [BAZ_VARIABLE_VOIR => BAZ_VOIR_LISTES], false)
            );
        }

        return $this->render('@bazar/lists/list_form.twig');
    }

    public function update($id) 
    {
        $listManager = $this->getService(ListManager::class);
        $list = $listManager->getOne($id);

        if( isset($_POST['valider']) ) {
            if( $GLOBALS['wiki']->HasAccess('write', $id) ) {
                $i = 1;
                $values = [];

                foreach ($_POST['label'] as $label) {
                    if (($label != null || $label != '') && ($_POST['id'][$i] != null || $_POST['id'][$i] != '')) {
                        $values[$_POST['id'][$i]] = $label;
                    }
                    ++$i;
                }

                $listManager->update($id, $_POST['titre_liste'], $values);

                $GLOBALS['wiki']->Redirect(
                    $GLOBALS['wiki']->Href('', '', [BAZ_VARIABLE_VOIR => BAZ_VOIR_LISTES], false)
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
        $listManager = $this->getService(ListManager::class);

        $listManager->delete($id);

        if ($GLOBALS['wiki']->config['BAZ_ENVOI_MAIL_ADMIN']) {
            $this->getService(Mailer::class)->notifyAdminsListDeleted($id);
        }

        $GLOBALS['wiki']->Redirect(
            $GLOBALS['wiki']->href('', '', [BAZ_VARIABLE_VOIR => BAZ_VOIR_LISTES], false)
        );
    }
}
