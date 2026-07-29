<?php

namespace YesWiki\Content\Handler;

use YesWiki\Content\Controller\EntryController;
use YesWiki\Content\Service\DuplicationManager;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\ListManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiHandler;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Kernel\Performable\RegisteredHandler;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\Redirector;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\MarkdownFormatterService;

class DuplicateHandler extends YesWikiHandler implements RegisteredHandler
{
    /** `/PageName/duplicate` -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'duplicate';
    }

    protected $authenticationService;
    protected $entryController;
    protected $duplicationManager;

    public function run()
    {
        $this->authenticationService = $this->getService(AuthenticationService::class);
        $this->entryController = $this->getService(EntryController::class);
        $this->duplicationManager = $this->getService(DuplicationManager::class);
        $title = $error = '';
        $toExternalWiki = $this->getRequest()->query->get('toUrl') == '1';
        if (!$this->getService(PageContext::class)->getPage()) {
            $error .= $this->render('@templates\alert-message.twig', [
                'type' => 'warning',
                'message' => str_replace(
                    ['{beginLink}', '{endLink}'],
                    ["<a href=\"{$this->getService(UrlFormatter::class)->href('')}\">", '</a>'],
                    _t('NOT_FOUND_PAGE')
                ),
            ]);
        } elseif (!$this->getService(AclService::class)->hasAccess('read', $this->getService(PageContext::class)->getTag())) {
            // if no read access to the page
            if ($content = $this->getService(PageManager::class)->getOne('PageLogin')) {
                // si une page PageLogin existe, on l'affiche
                $error .= $this->getService(MarkdownFormatterService::class)->format($content['body']);
            } else {
                // sinon on affiche le formulaire d'identification minimal
                $error .= '<div class="vertical-center white-bg">' . "\n"
                    . '<div class="alert alert-danger alert-error">' . "\n"
                    . _t('LOGIN_NOT_AUTORIZED') . '. ' . _t('LOGIN_PLEASE_REGISTER') . '.' . "\n"
                    . '</div>' . "\n"
                    . $this->getService(MarkdownFormatterService::class)->format('{{login signupurl="0"}}' . "\n\n")
                    . '</div><!-- end .vertical-center -->' . "\n";
            }
        } elseif (!empty($this->getRequest()->request->all())) {
            try {
                $data = $this->duplicationManager->checkPostData($this->getRequest()->request->all());
                $this->duplicationManager->duplicateLocally($data);
                if ($data['duplicate-action'] == 'edit') {
                    $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href('edit', $data['newTag']));
                } elseif ($data['duplicate-action'] == 'return') {
                    $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href());
                }
                $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href('', $data['newTag']));
            } catch (\Throwable $th) {
                $error .= $this->render('@templates\alert-message-with-back.twig', [
                    'type' => 'warning',
                    'message' => $th->getMessage(),
                ]);
            }
        } elseif (!$toExternalWiki && !$this->getService(AclService::class)->isAdmin()) {
            $error .= $this->render('@templates\alert-message-with-back.twig', [
                'type' => 'warning',
                'message' => _t('ONLY_ADMINS_CAN_DUPLICATE') . '.',
            ]);
        } elseif ($this->getService(AclService::class)->hasAccess('read', $this->getService(PageContext::class)->getTag())) {
            $isEntry = $this->getService(EntryManager::class)->isEntry($this->getService(PageContext::class)->getTag());
            $isList = $this->getService(ListManager::class)->isList($this->getService(PageContext::class)->getTag());
            $type = $isEntry ? 'entry' : ($isList ? 'list' : 'page');
            $pageTitle = '';
            if ($isEntry) {
                $title = _t('TEMPLATE_DUPLICATE_ENTRY') . ' ' . $this->getService(PageContext::class)->getTag();
                $originalContent = $this->getService(EntryManager::class)->getOne($this->getService(PageContext::class)->getTag());
                if ($toExternalWiki) {
                    $pageTitle = $originalContent['bf_titre'];
                    $proposedTag = $this->getService(PageContext::class)->getTag();
                    $originalContent = $this->getService(PageContext::class)->getPage()['body'];
                    $form = $this->getService(FormManager::class)->getOne($this->getService(EntryManager::class)->getOne($proposedTag)['form_id']);
                } else {
                    $pageTitle = $originalContent['bf_titre'] . ' (' . _t('DUPLICATE') . ')';
                    $proposedTag = genere_nom_wiki($pageTitle);
                }
            } elseif ($isList) {
                $title = _t('TEMPLATE_DUPLICATE_LIST') . ' ' . $this->getService(PageContext::class)->getTag();
                $originalContent = $this->getService(ListManager::class)->getOne($this->getService(PageContext::class)->getTag());
                if ($toExternalWiki) {
                    $pageTitle = $originalContent['titre_liste'];
                    $proposedTag = $this->getService(PageContext::class)->getTag();
                } else {
                    $pageTitle = $originalContent['titre_liste'] . ' (' . _t('DUPLICATE') . ')';
                    $proposedTag = genere_nom_wiki('Liste ' . $pageTitle);
                }
            } else { // page
                $title = _t('TEMPLATE_DUPLICATE_PAGE') . ' ' . $this->getService(PageContext::class)->getTag();
                if ($toExternalWiki) {
                    $proposedTag = $this->getService(PageContext::class)->getTag();
                } else {
                    $proposedTag = genere_nom_wiki($this->getService(PageContext::class)->getTag() . ' ' . _t('DUPLICATE'));
                }
                $originalContent = $this->getService(PageContext::class)->getPage()['body'];
            }
            $attachments = $this->duplicationManager->findFiles($this->getService(PageContext::class)->getPage()['tag']);
            $totalSize = 0;
            foreach ($attachments as $a) {
                $totalSize = $totalSize + $a['size'];
            }
        }

        if ($toExternalWiki) {
            $title .= ' ' . _t('TO_ANOTHER_YESWIKI');
        }
        // in ajax request for modal, no title
        if ($this->getRequest()->isXmlHttpRequest()) {
            $title = '';
        }

        return $this->renderFullPage('@core/handlers/duplicate.twig', [
            'title' => $title,
            'originalTag' => $this->getService(PageContext::class)->getTag(),
            'error' => $error,
            'sourceUrl' => $this->getService(UrlFormatter::class)->href(),
            'proposedTag' => $proposedTag ?? '',
            'attachments' => $attachments ?? [],
            'pageTitle' => $pageTitle ?? '',
            'originalContent' => $originalContent ?? '',
            'totalSize' => $this->duplicationManager->humanFilesize($totalSize ?? 0),
            'type' => $type ?? '',
            'form' => $form ?? '',
            'baseUrl' => preg_replace('/\?$/Ui', '', $this->getService(RuntimeConfig::class)['base_url']),
            'toExternalWiki' => $toExternalWiki,
        ]);
    }
}
