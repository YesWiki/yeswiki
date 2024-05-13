<?php

use YesWiki\Wiki;
use YesWiki\Core\Service\AclService;
use YesWiki\Bazar\Controller\EntryController;
use YesWiki\Bazar\Service\EntryManager;
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
                'type' => 'info',
                'message' => str_replace(
                    ["{beginLink}", "{endLink}"],
                    ["<a href=\"{$this->wiki->href('edit')}\">", "</a>"],
                    _t("NOT_FOUND_PAGE")
                ),
            ]);
        } elseif ($this->getService(AclService::class)->hasAccess('read', $this->wiki->GetPageTag())) {
            $title = _t('TEMPLATE_DUPLICATE_PAGE') . ' ' . $this->wiki->GetPageTag();
            $attachments = $this->importManager->findDirectLinkAttachements($this->wiki->page['tag']);
            $totalSize = 0;
            foreach ($attachments as $a) {
                $totalSize = $totalSize + $a['size'];
            }
            $output .= $this->render('@core/handlers/duplicate-inner.twig', [
                'attachments' => $attachments,
                'totalSize' => $this->importManager->humanFilesize($totalSize),
                'isEntry' => $this->getService(EntryManager::class)->isEntry($this->wiki->GetPageTag()),
                'toExternalWiki' => isset($_GET['toUrl']) && $_GET['toUrl'] == "1"
            ]);
        } else { // if no read access to the page
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
