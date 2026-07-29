<?php

namespace YesWiki\Content\Controller;

use YesWiki\Content\Service\FieldFactory;
use YesWiki\Content\Service\ListManager;
use YesWiki\Core\YesWikiController;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\Mailer;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\UrlFormatter;

class ListController extends YesWikiController
{
    protected $listManager;
    protected $hibernationService;
    protected $aclService;
    protected $authenticationService;
    protected $fieldFactory;

    public function __construct(
        ListManager $listManager,
        HibernationService $hibernationService,
        AclService $aclService,
        AuthenticationService $authenticationService,
        FieldFactory $fieldFactory
    ) {
        $this->listManager = $listManager;
        $this->hibernationService = $hibernationService;
        $this->aclService = $aclService;
        $this->authenticationService = $authenticationService;
        $this->fieldFactory = $fieldFactory;
    }

    public function displayAll()
    {
        $post = $this->getRequest()->request;
        if ($post->has('imported-list')) {
            foreach ($post->all('imported-list') as $listRaw) {
                $list = json_decode($listRaw, true);
                $this->listManager->create($list['title'], $list['nodes']);
            }
            echo '<div class="alert alert-success">' . _t('BAZ_LIST_IMPORT_SUCCESSFULL') . '.</div>';
            echo '<div class="alert alert-success">' . _t('BAZ_LIST_IMPORT_SUCCESSFULL') . '.</div>';
        }

        $lists = $this->listManager->getAll();

        foreach ($lists as $key => $list) {
            $lists[$key]['canEdit'] = !$this->hibernationService->isWikiHibernated() && $this->getService(AclService::class)->hasAccess('write', $key);
            $lists[$key]['canDelete'] = !$this->hibernationService->isWikiHibernated() && ($this->getService(AclService::class)->isAdmin() || $this->getService(AclService::class)->isOwner($key));
            // Small trick : create a fake SelectListField so we can reuse the code to compute the options
            $field = $this->fieldFactory->create(['liste', $list['id'], '', '', '', '', '', '', '', '', '', '', '', '', '', '']);
            $lists[$key]['options'] = $field->getOptions();
        }

        return $this->render('@core/lists/list_table.twig', [
            'lists' => $lists,
            'loggedUser' => $this->authenticationService->getLoggedUser(),
            'canCreate' => !$this->hibernationService->isWikiHibernated(),
        ]);
    }

    public function create()
    {
        $post = $this->getRequest()->request;
        if ($post->has('submit')) {
            $title = $post->get('title');
            $listeId = $this->listManager->create($title, json_decode($post->get('nodes'), true));

            if ($this->shouldPostMessageOnSubmit()) {
                return $this->render('@core/iframe_result.twig', [
                    'data' => ['msg' => 'list_created', 'id' => $listeId, 'title' => $title],
                ]);
            }

            $this->wiki->Redirect(
                $this->getService(UrlFormatter::class)->href('', '', [BAZ_VARIABLE_VOIR => BAZ_VOIR_LISTES], false)
            );
        }

        return $this->render('@core/lists/list_form.twig', [
            'list' => ['title' => '', 'nodes' => []],
        ]);
    }

    private function shouldPostMessageOnSubmit()
    {
        return $this->getRequest()->query->get('onsubmit') === 'postmessage';
    }

    public function update($id)
    {
        $list = $this->listManager->getOne($id);
        $post = $this->getRequest()->request;
        if ($post->has('submit')) {
            if ($this->aclService->hasAccess('write', $id)) {
                $title = $post->get('title');
                $this->listManager->update($id, $title, json_decode($post->get('nodes'), true));

                if ($this->shouldPostMessageOnSubmit()) {
                    return $this->render('@core/iframe_result.twig', [
                        'data' => ['msg' => 'list_updated', 'id' => $id, 'title' => $title],
                    ]);
                }

                $this->wiki->Redirect(
                    $this->getService(UrlFormatter::class)->href('', '', [BAZ_VARIABLE_VOIR => BAZ_VOIR_LISTES], false)
                );
            } else {
                throw new \Exception('Not allowed');
            }
        }

        return $this->render('@core/lists/list_form.twig', [
            'list' => $list,
        ]);
    }

    public function delete($id)
    {
        $this->listManager->delete($id);

        if ($this->getService(RuntimeConfig::class)['BAZ_ENVOI_MAIL_ADMIN']) {
            $this->getService(Mailer::class)->notifyAdminsListDeleted($id);
        }

        $this->wiki->Redirect(
            $this->getService(UrlFormatter::class)->href('', '', [BAZ_VARIABLE_VOIR => BAZ_VOIR_LISTES], false)
        );
    }
}
