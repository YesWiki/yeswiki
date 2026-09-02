<?php

namespace YesWiki\Content\Handler;

use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use Tamtamchik\SimpleFlash\Flash;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiHandler;
use YesWiki\Identity\Service\AclOptions;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\CsrfTokenChecker;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Performable\RegisteredHandler;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\Redirector;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\LinkRenderer;

/** `/PageName/acls` -- who may read, write and comment a page, and who owns it. */
class AclsHandler extends YesWikiHandler implements RegisteredHandler
{
    public static function performableName(): string
    {
        return 'acls';
    }

    public function run(): string
    {
        $pageContext = $this->getService(PageContext::class);
        $aclService = $this->getService(AclService::class);
        $page = $pageContext->getPage();
        $tag = $pageContext->getTag();

        if (!$page || !($aclService->isOwner() || $aclService->isAdmin())) {
            return $this->renderFullPage('@core/alert-message.twig', [
                'type' => 'danger',
                'message' => _t('YW_CANNOT_CHANGE_ACLS'),
            ]);
        }

        if ($this->getRequest()->isMethod('POST')) {
            return $this->save($tag, !empty($page['parent']));
        }

        $options = $this->getService(AclOptions::class);
        $privileges = [AclOptions::READ, AclOptions::WRITE, AclOptions::COMMENT];
        $acls = [];
        $choices = [];
        foreach ($privileges as $privilege) {
            $acls[$privilege] = $aclService->load($tag, $privilege)['list'] ?? '';
            $choices[$privilege] = $options->for($privilege);
        }

        return $this->renderFullPage('@core/handlers/acls.twig', [
            'pageLink' => $this->getService(LinkRenderer::class)->linkToPage($tag),
            'acls' => $acls,
            'options' => $choices,
            'hasParent' => !empty($page['parent']),
            'users' => array_column($this->getService(UserManager::class)->getAll(['name']), 'name'),
            'hibernated' => $this->getService(HibernationService::class)->isWikiHibernated(),
        ]);
    }

    private function save(string $tag, bool $hasParent): string
    {
        $redirector = $this->getService(Redirector::class);
        $back = $this->getService(UrlFormatter::class)->href();

        try {
            $this->getService(CsrfTokenChecker::class)->checkToken('main', 'POST', 'csrf-token', false);
            $post = $this->getRequest()->request;
            $this->getService(AclService::class)->saveMany($tag, [
                AclOptions::READ => (string)$post->get('read_acl', ''),
                AclOptions::WRITE => (string)$post->get('write_acl', ''),
                AclOptions::COMMENT => $hasParent ? '' : (string)$post->get('comment_acl', ''),
            ]);
            $message = _t('YW_ACLS_UPDATED');

            $newOwner = trim((string)$post->get('newowner', ''));
            if ($newOwner !== '') {
                $this->getService(PageManager::class)->setOwner($tag, $newOwner);
                $message .= _t('YW_NEW_OWNER') . $newOwner;
            }
            Flash::success($message);
        } catch (TokenNotFoundException|\Exception $failed) {
            Flash::error($failed->getMessage());
        }

        return $redirector->redirect($back);
    }
}
