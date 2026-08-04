<?php

namespace YesWiki\Content\Controller;

use YesWiki\Content\Action\BazarAction;
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
    protected $listManager;
    protected $hibernationService;
    protected $aclService;
    protected $fieldFactory;

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

    public function displayAll()
    {
        $post = $this->getRequest()->request;
        $refusal = '';
        if ($post->has('imported-list')) {
            if ($this->mayCreate()) {
                foreach ($post->all('imported-list') as $listRaw) {
                    $list = json_decode($listRaw, true);
                    $this->listManager->create($list['title'], $list['nodes']);
                }
                echo '<div class="alert alert-success">' . _t('BAZ_LIST_IMPORT_SUCCESSFULL') . '.</div>';
            } else {
                // the table below is still theirs to read: say no to the import, not to
                // the screen
                $refusal = $this->refusal();
            }
        }

        // a list with a read ACL is not everyone's to see, and this screen is everyone's
        // to open: it lists what the reader may read, and nothing else
        $lists = array_filter(
            $this->listManager->getAll(),
            fn ($key) => $this->aclService->hasAccess('read', $key),
            ARRAY_FILTER_USE_KEY
        );

        foreach ($lists as $key => $list) {
            $lists[$key]['canEdit'] = $this->mayEdit((string)$key);
            $lists[$key]['canDelete'] = $this->mayDelete((string)$key);
            // Small trick : create a fake SelectListField so we can reuse the code to compute the options
            $field = $this->fieldFactory->create(['liste', $list['id'], '', '', '', '', '', '', '', '', '', '', '', '', '', '']);
            $lists[$key]['options'] = $field->getOptions();
        }

        return $refusal . $this->render('@core/lists/list_table.twig', [
            'lists' => $lists,
            'canCreate' => $this->mayCreate(),
        ]);
    }

    /**
     * Who may change the lists: an admin, or -- for one list -- whoever owns it.
     *
     * That is what this screen enforced when it was `/admin/lists`, and going public must
     * not hand anybody a power they did not have. The write ACL alone will not do it: most
     * wikis leave `default_write_acl` open, so `hasAccess('write')` says yes to a passer-by,
     * and the edit and delete buttons were offered to every visitor the moment the screen
     * became one they could open.
     */
    private function mayCreate(): bool
    {
        return !$this->hibernationService->isWikiHibernated() && $this->aclService->isAdmin();
    }

    private function mayEdit(string $id): bool
    {
        // ...and the list's own write ACL on top, for a wiki that sets one
        return $this->mayDelete($id) && $this->aclService->hasAccess('write', $id);
    }

    private function mayDelete(string $id): bool
    {
        return !$this->hibernationService->isWikiHibernated()
            && ($this->aclService->isAdmin() || $this->aclService->isOwner($id));
    }

    /**
     * Every way into this controller says who it is for, because the *screen* no longer
     * does: it used to sit behind `/admin/lists` and the route's `@admins` was the only
     * check anywhere -- creating, importing and deleting a list asked nothing at all.
     * Public now, so each one asks.
     *
     * Rendered rather than thrown: an exception here comes back as "an unexpected error
     * occurred, please contact the administrator" with the file and line that raised it,
     * and being told no is neither unexpected nor the administrator's problem.
     */
    private function refusal(): string
    {
        return (string)$this->render('@core/alert-message.twig', [
            'type' => 'danger',
            'message' => _t('BAZ_DROIT_INSUFFISANT'),
        ]);
    }

    public function create()
    {
        if (!$this->mayCreate()) {
            return $this->refusal();
        }

        $post = $this->getRequest()->request;
        if ($post->has('submit')) {
            $title = $post->get('title');
            $listId = $this->listManager->create($title, json_decode($post->get('nodes'), true));

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

    private function shouldPostMessageOnSubmit()
    {
        return $this->getRequest()->query->get('onsubmit') === 'postmessage';
    }

    public function update($id)
    {
        // the form itself, not only the save: opening an editor for a list you may not
        // write is an offer that fails on the button
        if (!$this->mayEdit((string)$id)) {
            return $this->refusal();
        }
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

    public function delete($id)
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
