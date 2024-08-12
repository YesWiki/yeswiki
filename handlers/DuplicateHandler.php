<?php

use YesWiki\Bazar\Controller\EntryController;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Bazar\Service\ListManager;
use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\DuplicationManager;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\YesWikiHandler;

class DuplicateHandler extends YesWikiHandler
{
    protected $authController;
    protected $entryController;
    protected $duplicationManager;

    public function run()
    {
        $this->authController = $this->getService(AuthController::class);
        $this->entryController = $this->getService(EntryController::class);
        $this->duplicationManager = $this->getService(DuplicationManager::class);
        $title = $error = '';
        $toExternalWiki = isset($_GET['toUrl']) && $_GET['toUrl'] == '1';
        if (!$this->wiki->page) {
            $error .= $this->render('@templates\alert-message.twig', [
                'type' => 'warning',
                'message' => str_replace(
                    ['{beginLink}', '{endLink}'],
                    ["<a href=\"{$this->wiki->href('')}\">", '</a>'],
                    _t('NOT_FOUND_PAGE')
                ),
            ]);
        } elseif (!$this->getService(AclService::class)->hasAccess('read', $this->wiki->GetPageTag())) {
            // if no read access to the page
            if ($content = $this->getService(PageManager::class)->getOne('PageLogin')) {
                // si une page PageLogin existe, on l'affiche
                $error .= $this->wiki->Format($content['body']);
            } else {
                // sinon on affiche le formulaire d'identification minimal
                $error .= '<div class="vertical-center white-bg">' . "\n"
                    . '<div class="alert alert-danger alert-error">' . "\n"
                    . _t('LOGIN_NOT_AUTORIZED') . '. ' . _t('LOGIN_PLEASE_REGISTER') . '.' . "\n"
                    . '</div>' . "\n"
                    . $this->wiki->Format('{{login signupurl="0"}}' . "\n\n")
                    . '</div><!-- end .vertical-center -->' . "\n";
            }
        } elseif (!empty($_POST)) {
            try {
                $data = $this->duplicationManager->checkPostData($_POST);
                $this->duplicationManager->duplicateLocally($data);
                if ($data['duplicate-action'] == 'edit') {
                    $this->wiki->Redirect($this->wiki->href('edit', $data['newTag']));

                    return;
                } elseif ($data['duplicate-action'] == 'return') {
                    $this->wiki->Redirect($this->wiki->href());

                    return;
                }
                $this->wiki->Redirect($this->wiki->href('', $data['newTag']));

                return;
            } catch (Throwable $th) {
                $error .= $this->render('@templates\alert-message-with-back.twig', [
                    'type' => 'warning',
                    'message' => $th->getMessage(),
                ]);
            }
        } elseif (!$toExternalWiki && !$this->wiki->UserIsAdmin()) {
            $error .= $this->render('@templates\alert-message-with-back.twig', [
                'type' => 'warning',
                'message' => _t('ONLY_ADMINS_CAN_DUPLICATE') . '.',
            ]);
        } elseif ($this->getService(AclService::class)->hasAccess('read', $this->wiki->GetPageTag())) {
            $isEntry = $this->getService(EntryManager::class)->isEntry($this->wiki->GetPageTag());
            $isList = $this->getService(ListManager::class)->isList($this->wiki->GetPageTag());
            $type = $isEntry ? 'entry' : ($isList ? 'list' : 'page');
            $pageTitle = '';
            if ($isEntry) {
                $title = _t('TEMPLATE_DUPLICATE_ENTRY') . ' ' . $this->wiki->GetPageTag();
                $originalContent = $this->getService(EntryManager::class)->getOne($this->wiki->GetPageTag());
                if ($toExternalWiki) {
                    $pageTitle = $originalContent['bf_titre'];
                    $proposedTag = $this->wiki->GetPageTag();
                    $originalContent = $this->wiki->page['body'];
                    $form = $this->getService(FormManager::class)->getOne($this->getService(EntryManager::class)->getOne($proposedTag)['id_typeannonce']);
                } else {
                    $pageTitle = $originalContent['bf_titre'] . ' (' . _t('DUPLICATE') . ')';
                    $proposedTag = genere_nom_wiki($pageTitle);
                }
            } elseif ($isList) {
                $title = _t('TEMPLATE_DUPLICATE_LIST') . ' ' . $this->wiki->GetPageTag();
                $originalContent = $this->getService(ListManager::class)->getOne($this->wiki->GetPageTag());
                if ($toExternalWiki) {
                    $pageTitle = $originalContent['titre_liste'];
                    $proposedTag = $this->wiki->GetPageTag();
                } else {
                    $pageTitle = $originalContent['titre_liste'] . ' (' . _t('DUPLICATE') . ')';
                    $proposedTag = genere_nom_wiki('Liste ' . $pageTitle);
                }
            } else { // page
                $title = _t('TEMPLATE_DUPLICATE_PAGE') . ' ' . $this->wiki->GetPageTag();
                if ($toExternalWiki) {
                    $proposedTag = $this->wiki->GetPageTag();
                } else {
                    $proposedTag = genere_nom_wiki($this->wiki->GetPageTag() . ' ' . _t('DUPLICATE'));
                }
                $originalContent = $this->wiki->page['body'];
            }
            $attachments = $this->duplicationManager->findFiles($this->wiki->page['tag']);
            $totalSize = 0;
            foreach ($attachments as $a) {
                $totalSize = $totalSize + $a['size'];
            }
        }

        if ($toExternalWiki) {
            $title .= ' ' . _t('TO_ANOTHER_YESWIKI');
        }
        // in ajax request for modal, no title
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            $title = '';
        }

        return $this->renderInSquelette('@core/handlers/duplicate.twig', [
            'title' => $title,
            'originalTag' => $this->wiki->GetPageTag(),
            'error' => $error,
            'sourceUrl' => $this->wiki->href(),
            'proposedTag' => $proposedTag ?? '',
            'attachments' => $attachments ?? [],
            'pageTitle' => $pageTitle ?? '',
            'originalContent' => $originalContent ?? '',
            'totalSize' => $this->duplicationManager->humanFilesize($totalSize ?? 0),
            'type' => $type ?? '',
            'form' => $form ?? '',
            'baseUrl' => preg_replace('/\?$/Ui', '', $this->wiki->config['base_url']),
            'toExternalWiki' => $toExternalWiki,
        ]);
    }
}
