<?php

namespace YesWiki\Bazar\Controller;

use YesWiki\Bazar\Service\FieldFactory;
use YesWiki\Bazar\Service\ListManager;
use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\Mailer;
use YesWiki\Core\YesWikiController;
use YesWiki\Bazar\Service\Guard;
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

            $lists[$key]['canEdit'] = !$this->securityController->isWikiHibernated() && $this->aclService->hasAccess('write', $key);
            $lists[$key]['canDelete'] = !$this->securityController->isWikiHibernated() && ($this->wiki->UserIsAdmin() || $this->wiki->UserIsOwner($key));
            // Small trick : create a fake SelectListField so we can reuse the code to compute the options
            $field = $this->fieldFactory->create(['liste', $list['id'], '', '', '', '', '', '', '', '', '', '', '', '', '', '']);
            $lists[$key]['options'] = $field->getOptions();
        }

        return $this->render('@bazar/lists/list_table.twig', [
            'lists' => $lists,
            'loggedUser' => $this->authController->getLoggedUser(),
            'canCreate' => !$this->securityController->isWikiHibernated(),
            'isMultilang' => isset($this->wiki->config['supported_langs']),
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
                $this->wiki->Href('', '', [BAZ_VARIABLE_VOIR => BAZ_VOIR_LISTES], false)
            );
        }

        return $this->render('@bazar/lists/list_form.twig', [
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
                $this->listManager->update($id, $title, json_decode($post->get('nodes'), true), $list['extralang'] ?? []);

                if ($this->shouldPostMessageOnSubmit()) {
                    return $this->render('@core/iframe_result.twig', [
                        'data' => ['msg' => 'list_updated', 'id' => $id, 'title' => $title],
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

    public function translate($id) {
        if ($this->getService(Guard::class)->isAllowed('saisie_formulaire')) {
            if ($this->getRequest()->getMethod() === 'POST') {
                $req = $this->getRequest()->request;
                $list = json_decode($req->get('jsonlist'), true);
                dump($list['extralang']);
                dump($id);
                $this->listManager->update($id, $list['title'], $list['nodes'], $list['extralang'] ?? []);


            } else {
                $list = $this->listManager->getOne($id, null,  'all');
                $default_lang = $this->wiki->config['default_language'] ?? 'fr';

                return $this->render('@bazar/forms/list_translate.twig', ['list' => $list,
                'default_language' => $default_lang,
                'langs' => array_filter($this->wiki->config['supported_langs'], function($el) use ($default_lang) {
                    return $el != $default_lang;
                })
                ]);

            }
        } else {
            return $this->wiki->redirect($this->wiki->href('', '', ['vue' => 'formulaire', 'msg' => 'BAZ_NEED_ADMIN_RIGHTS'], false));
        }
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
