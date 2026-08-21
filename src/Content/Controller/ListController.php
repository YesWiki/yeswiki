<?php

namespace YesWiki\Content\Controller;

use YesWiki\Content\Action\BazarAction;
use YesWiki\Content\Field\EnumField;
use YesWiki\Content\Service\FieldFactory;
use YesWiki\Content\Service\ListManager;
use YesWiki\Core\YesWikiController;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\Mailer;
use YesWiki\Kernel\Service\Redirector;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\UrlFormatter;

class ListController extends YesWikiController
{
    protected ListManager $listManager;
    protected HibernationService $hibernationService;
    protected AclService $aclService;
    protected FieldFactory $fieldFactory;

    public function __construct(
        ListManager $listManager,
        HibernationService $hibernationService,
        AclService $aclService,
        FieldFactory $fieldFactory
    ) {
        $this->listManager = $listManager;
        $this->hibernationService = $hibernationService;
        $this->aclService = $aclService;
        $this->fieldFactory = $fieldFactory;
    }

    public function displayAll(): string
    {
        $post = $this->getRequest()->request;
        $refusal = '';
        if ($post->has('imported-list')) {
            if ($this->mayCreate()) {
                foreach ($post->all('imported-list') as $listRaw) {
                    $list = is_string($listRaw) ? json_decode($listRaw, true) : null;
                    // a list that does not decode to {title, nodes} is not one this can import
                    if (!is_array($list) || !isset($list['title'])) {
                        continue;
                    }
                    $this->listManager->create($list['title'], $list['nodes'] ?? null);
                }
                echo '<div class="alert alert-success">' . _t('BAZ_LIST_IMPORT_SUCCESSFULL') . '.</div>';
            } else {
                $refusal = $this->refusal();
            }
        }

        $lists = array_filter(
            $this->listManager->getAll(),
            fn ($key) => $this->aclService->hasAccess('read', $key),
            ARRAY_FILTER_USE_KEY
        );

        foreach ($lists as $key => $list) {
            // getAll() yields null for a list whose body will not load; it has nothing to show
            if ($list === null) {
                unset($lists[$key]);
                continue;
            }
            $lists[$key]['canEdit'] = $this->mayEdit((string)$key);
            $lists[$key]['canDelete'] = $this->mayDelete((string)$key);

            $field = $this->fieldFactory->create(['liste', $list['id'] ?? '', '', '', '', '', '', '', '', '', '', '', '', '', '', '']);
            $lists[$key]['options'] = $field instanceof EnumField ? $field->getOptions() : [];
        }

        return $refusal . $this->render('@core/lists/list_table.twig', [
            'lists' => $lists,
            'canCreate' => $this->mayCreate(),
        ]);
    }

    /** Who may change the lists: an admin, or -- for one list -- whoever owns it. */
    private function mayCreate(): bool
    {
        return !$this->hibernationService->isWikiHibernated() && $this->aclService->isAdmin();
    }

    private function mayEdit(string $id): bool
    {
        return $this->mayDelete($id) && $this->aclService->hasAccess('write', $id);
    }

    private function mayDelete(string $id): bool
    {
        return !$this->hibernationService->isWikiHibernated()
            && ($this->aclService->isAdmin() || $this->aclService->isOwner($id));
    }

    /**
     * Every way into this controller says who it is for, because the *screen* no longer does: it used to sit behind `/admin/lists` and the route's `@admins` was the only check anywhere -- creating, importing and deleting a list asked nothing at all.
     */
    private function refusal(): string
    {
        return (string)$this->render('@core/alert-message.twig', [
            'type' => 'danger',
            'message' => _t('BAZ_DROIT_INSUFFISANT'),
        ]);
    }

    public function create(): string
    {
        if (!$this->mayCreate()) {
            return $this->refusal();
        }

        $post = $this->getRequest()->request;
        if ($post->has('submit')) {
            $title = (string)$post->get('title', '');
            $listId = $this->listManager->create($title, json_decode((string)$post->get('nodes', ''), true));

            if ($this->shouldPostMessageOnSubmit()) {
                return $this->render('@core/iframe_result.twig', [
                    'data' => ['msg' => 'list_created', 'id' => $listId, 'title' => $title],
                ]);
            }

            $this->getService(Redirector::class)->redirect(
                $this->getService(UrlFormatter::class)->href('', '', [BazarAction::URL_VIEW_PARAM => BazarAction::VIEW_LISTS], false)
            );
        }

        return $this->render('@core/lists/list_form.twig', [
            'list' => ['title' => '', 'nodes' => []],
        ]);
    }

    private function shouldPostMessageOnSubmit(): bool
    {
        return $this->getRequest()->query->get('onsubmit') === 'postmessage';
    }

    /**
     * @param mixed $id the list id, straight off the query string
     */
    public function update($id): string
    {
        if (!$this->mayEdit((string)$id)) {
            return $this->refusal();
        }
        $list = $this->listManager->getOne($id);
        $post = $this->getRequest()->request;
        if ($post->has('submit')) {
            if ($this->aclService->hasAccess('write', $id)) {
                $title = (string)$post->get('title', '');
                $this->listManager->update($id, $title, json_decode((string)$post->get('nodes', ''), true));

                if ($this->shouldPostMessageOnSubmit()) {
                    return $this->render('@core/iframe_result.twig', [
                        'data' => ['msg' => 'list_updated', 'id' => $id, 'title' => $title],
                    ]);
                }

                $this->getService(Redirector::class)->redirect(
                    $this->getService(UrlFormatter::class)->href('', '', [BazarAction::URL_VIEW_PARAM => BazarAction::VIEW_LISTS], false)
                );
            } else {
                throw new \Exception('Not allowed');
            }
        }

        return $this->render('@core/lists/list_form.twig', [
            'list' => $list,
        ]);
    }

    /**
     * @param mixed $id the list id, straight off the query string
     */
    public function delete($id): string
    {
        if (!$this->mayDelete((string)$id)) {
            return $this->refusal();
        }
        $this->listManager->delete($id);

        if ($this->getService(RuntimeConfig::class)['BAZ_ENVOI_MAIL_ADMIN']) {
            $this->getService(Mailer::class)->notifyAdminsListDeleted($id);
        }

        $this->getService(Redirector::class)->redirect(
            $this->getService(UrlFormatter::class)->href('', '', [BazarAction::URL_VIEW_PARAM => BazarAction::VIEW_LISTS], false)
        );
    }
}
