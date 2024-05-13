<?php

use YesWiki\Core\Service\AclService;
use YesWiki\Bazar\Controller\EntryController;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\ListManager;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\ImportFilesManager;
use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\YesWikiHandler;

class DuplicateHandler extends YesWikiHandler
{
    protected $authController;
    protected $entryController;
    protected $importManager;

    public function run()
    {
        $this->authController = $this->getService(AuthController::class);
        $this->entryController = $this->getService(EntryController::class);
        $this->importManager = $this->getService(ImportFilesManager::class);
        $output = $title = '';
        if (!$this->wiki->page) {
            $output = $this->render('@templates\alert-message.twig', [
                'type' => 'warning',
                'message' => str_replace(
                    ["{beginLink}", "{endLink}"],
                    ["<a href=\"{$this->wiki->href('')}\">", "</a>"],
                    _t("NOT_FOUND_PAGE")
                ),
            ]);
        } elseif (!$this->getService(AclService::class)->hasAccess('read', $this->wiki->GetPageTag())) {
            // if no read access to the page
            if ($contenu = $this->getService(PageManager::class)->getOne("PageLogin")) {
                // si une page PageLogin existe, on l'affiche
                $output .= $this->wiki->Format($contenu["body"]);
            } else {
                // sinon on affiche le formulaire d'identification minimal
                $output .= '<div class="vertical-center white-bg">' . "\n"
                    . '<div class="alert alert-danger alert-error">' . "\n"
                    . _t('LOGIN_NOT_AUTORIZED') . '. ' . _t('LOGIN_PLEASE_REGISTER') . '.' . "\n"
                    . '</div>' . "\n"
                    . $this->wiki->Format('{{login signupurl="0"}}' . "\n\n")
                    . '</div><!-- end .vertical-center -->' . "\n";
            }
        } elseif (!empty($_POST)) {
            try {
                $data = $this->importManager->checkPostData($_POST);
                if (!$this->getService(AclService::class)->hasAccess('write', $_POST['pageTag'])) {
                    throw new \Exception(_t('LOGIN_NOT_AUTORIZED_EDIT') . ' ' . $data['pageTag']);
                }
                switch ($data['type']) {

                    case 'list':
                        $list = $this->getService(ListManager::class)->getOne($this->wiki->getPageTag());
                        $this->getService(ListManager::class)->create($data['pageTitle'], $list['label'], $data['pageTag']);
                        break;

                    case 'entry':
                        $entry = $this->getService(EntryManager::class)->getOne($this->wiki->getPageTag());
                        $entry['id_fiche'] = $data['pageTag'];
                        $entry['bf_titre'] = $data['pageTitle'];
                        $entry['antispam'] = 1;
                        $this->getService(EntryManager::class)->create($entry['id_typeannonce'], $entry);
                        $this->importManager->duplicateFiles($this->wiki->getPageTag(), $data['pageTag']);
                        break;

                    default:
                    case 'page':
                        $this->getService(PageManager::class)->save($data['pageTag'], $this->wiki->page['body']);
                        $this->importManager->duplicateFiles($this->wiki->getPageTag(), $data['pageTag']);
                        break;
                }
                // TODO: duplicate acls and metadatas
                if ($data['duplicate-action'] == 'edit') {
                    $this->wiki->Redirect($this->wiki->href('edit', $data['pageTag']));
                    return;
                }
                $this->wiki->Redirect($this->wiki->href('', $data['pageTag']));
                return;
            } catch (\Throwable $th) {
                $output = $this->render('@templates\alert-message-with-back.twig', [
                    'type' => 'warning',
                    'message' => $th->getMessage(),
                ]);
            }
        } elseif ($this->getService(AclService::class)->hasAccess('read', $this->wiki->GetPageTag())) {
            $isEntry = $this->getService(EntryManager::class)->isEntry($this->wiki->GetPageTag());
            $isList = $this->getService(ListManager::class)->isList($this->wiki->GetPageTag());
            $type = $isEntry ? 'entry' : ($isList ? 'list' : 'page');
            $pageTitle = '';
            if ($isEntry) {
                $title = _t('TEMPLATE_DUPLICATE_ENTRY') . ' ' . $this->wiki->GetPageTag();
                $entry = $this->getService(EntryManager::class)->getOne($this->wiki->GetPageTag());
                $pageTitle = $entry['bf_titre'] . ' (' . _t('DUPLICATE') . ')';
                $proposedTag = genere_nom_wiki($pageTitle);
            } elseif ($isList) {
                $title = _t('TEMPLATE_DUPLICATE_LIST') . ' ' . $this->wiki->GetPageTag();
                $list = $this->getService(ListManager::class)->getOne($this->wiki->GetPageTag());
                $pageTitle = $list['titre_liste'] . ' (' . _t('DUPLICATE') . ')';
                $proposedTag = genere_nom_wiki('Liste ' . $pageTitle);
            } else { // page
                $title = _t('TEMPLATE_DUPLICATE_PAGE') . ' ' . $this->wiki->GetPageTag();
                $proposedTag = genere_nom_wiki($this->wiki->GetPageTag() . ' ' . _t('DUPLICATE'));
            }
            $attachments = $this->importManager->findFiles($this->wiki->page['tag']);
            $totalSize = 0;
            foreach ($attachments as $a) {
                $totalSize = $totalSize + $a['size'];
            }
            $output .= $this->render('@core/handlers/duplicate-inner.twig', [
                'proposedTag' => $proposedTag,
                'attachments' => $attachments,
                'pageTitle' => $pageTitle,
                'totalSize' => $this->importManager->humanFilesize($totalSize),
                'type' => $type,
                'toExternalWiki' => isset($_GET['toUrl']) && $_GET['toUrl'] == "1"
            ]);
        }

        // in ajax request for modal, no title
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            $title = '';
        }
        return $this->renderInSquelette('@core/handlers/duplicate.twig', [
            'title' => $title,
            'output' => $output,
        ]);
    }
}
