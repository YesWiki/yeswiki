<?php

namespace YesWiki\Bazar\Controller;

use YesWiki\Bazar\Service\FieldFactory;
use YesWiki\Bazar\Service\ListManager;
use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\Mailer;
use YesWiki\Core\YesWikiController;
use YesWiki\Security\Controller\SecurityController;

class ListController extends YesWikiController
{
    protected $listManager;
    protected $securityController;
    protected $aclService;
    protected $authController;
    protected $fieldFactory;

    public function __construct(
        ListManager $listManager,
        SecurityController $securityController,
        AclService $aclService,
        AuthController $authController,
        FieldFactory $fieldFactory
    ) {
        $this->listManager = $listManager;
        $this->securityController = $securityController;
        $this->aclService = $aclService;
        $this->authController = $authController;
        $this->fieldFactory = $fieldFactory;
    }

    public function displayAll($lang = 0)
    {
        if (isset($_POST['imported-list'])) {
            foreach ($_POST['imported-list'] as $listRaw) {
                $list = json_decode($listRaw, true);
                $this->listManager->create($list['title'], $list['nodes']);
            }
            echo '<div class="alert alert-success">' . _t('BAZ_LIST_IMPORT_SUCCESSFULL') . '.</div>';
            echo '<div class="alert alert-success">' . _t('BAZ_LIST_IMPORT_SUCCESSFULL') . '.</div>';
        }

        $lists = $this->listManager->getAll($lang);

        foreach ($lists as $key => $list) {
            $lists[$key]['canEdit'] = !$this->securityController->isWikiHibernated() && $this->wiki->HasAccess('write', $key);
            $lists[$key]['canDelete'] = !$this->securityController->isWikiHibernated() && ($this->wiki->UserIsAdmin() || $this->wiki->UserIsOwner($key));
            // Small trick : create a fake SelectListField so we can reuse the code to compute the options
            $field = $this->fieldFactory->create(['liste', $list['id'], '', '', '', '', '', '', '', '', '', '', '', '', '', '']);
            $lists[$key]['options'] = $field->getOptions();
        }

        return $this->render('@bazar/lists/list_table.twig', [
            'lists' => $lists,
            'loggedUser' => $this->authController->getLoggedUser(),
            'canCreate' => !$this->securityController->isWikiHibernated(),
        ]);
    }

    public function create()
    {
        if (isset($_POST['submit'])) {
            $listeId = $this->listManager->create($_POST['title'], json_decode($_POST['nodes'], true), json_decode($_POST['extra_lang'], true));

            if ($this->shouldPostMessageOnSubmit()) {
                return $this->render('@core/iframe_result.twig', [
                    'data' => ['msg' => 'list_created', 'id' => $listeId, 'title' => $_POST['title']],
                ]);
            }

            $this->wiki->Redirect(
                $this->wiki->Href('', '', [BAZ_VARIABLE_VOIR => BAZ_VOIR_LISTES], false)
            );
        }

        return $this->render('@bazar/lists/list_form.twig', [
            'list' => ['title' => '', 'nodes' => []],
        ]);
    }

    private function shouldPostMessageOnSubmit()
    {
        return isset($_GET['onsubmit']) && $_GET['onsubmit'] === 'postmessage';
    }

    public function update($id)
    {
        $list = $this->listManager->getOne($id, 0, true);

        if (isset($_POST['submit'])) {
            if ($this->aclService->hasAccess('write', $id)) {
                $this->listManager->update($id, $_POST['title'], json_decode($_POST['nodes'], true), json_decode($_POST['extra_lang'], true));

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
            'default_language' => $GLOBALS['default_language'] ?? 'fr',
            'extra_lang' => $GLOBALS['wiki']->config['extra_lang'] ?? [],
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
