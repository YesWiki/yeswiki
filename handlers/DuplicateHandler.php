<?php

use YesWiki\Bazar\Controller\EntryController;
use YesWiki\Bazar\Field\BazarField;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\DuplicationFollower;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\Performer;
use YesWiki\Core\YesWikiHandler;

class DuplicateHandler extends YesWikiHandler
{
    protected $aclService;
    protected $duplicationFollower;
    protected $entryController;
    protected $entryManager;
    protected $formManager;
    protected $pageManager;
    protected $performer;

    public function run()
    {
        // get services
        $this->aclService = $this->getService(AclService::class);
        $this->duplicationFollower = $this->getService(DuplicationFollower::class);
        $this->entryController = $this->getService(EntryController::class);
        $this->entryManager = $this->getService(EntryManager::class);
        $this->formManager = $this->getService(FormManager::class);
        $this->pageManager = $this->getService(PageManager::class);
        $this->performer = $this->getService(Performer::class);

        // check current user can read
        if (!$this->aclService->hasAccess('read')){
            return $this->finalRender($this->render('@templates/alert-message.twig',[
                'type' => 'danger',
                'message' => _t('TEMPLATE_NO_ACCESS_TO_PAGE')
            ]));
        }
        $tag = $this->wiki->getPageTag();
        $page = $this->pageManager->getOne($tag);
        $isEntry = $this->entryManager->isEntry($tag);

        $canDuplicateEntryIfNotRightToWrite = $this->params->has('canDuplicateEntryIfNotRightToWrite')
            ? $this->formatBoolean($this->params->get('canDuplicateEntryIfNotRightToWrite'),false)
            : false;

        // check current user can write for new entry/page
        if (!$this->aclService->hasAccess('write','--unknown-tag--') && (!$isEntry || !$canDuplicateEntryIfNotRightToWrite)){
            return $this->finalRender($this->render('@templates/alert-message.twig',[
                'type' => 'danger',
                'message' => _t('EDIT_NO_WRITE_ACCESS')
            ]));
        }

        if (!empty($page)){
            return $isEntry
                ? $this->duplicateEntry($tag)
                : $this->duplicatePage($tag);
        }
        $this->wiki->method = $this->isInIframe() ?  'editiframe' : 'edit';
        return $this->performer->run($this->wiki->method,'handler',[]);
    }

    protected function finalRender(string $content, bool $includePage = false): string
    {
        $output = $includePage
            ? <<<HTML
            <div class="page">
                $content
            </div>
            HTML
            : $content;
        return $this->wiki->Header().$content.$this->wiki->Footer() ;
    }

    protected function duplicateEntry(string $tag): string
    {
        $entry = $this->entryManager->getOne($tag); // with current rights
        $form = $this->formManager->getOne($entry['id_typeannonce']);
        if (empty($form['prepared'])){
            throw new Exception("Impossible to duplicate because form is not existing !");
        }
        if (!empty($_GET['created']) && $_GET['created'] === '1'){
            $followedEntryIds = [];
            if ($this->duplicationFollower->isFollowed($tag, $followedEntryIds)){
                $firstId = array_shift($followedEntryIds);
                if (count($followedEntryIds) > 0){
                    flash(_t('DUPLICATE_OTHER_ENTRIES_CREATED',[
                        'links'=> implode(',',array_map(
                            function ($id) {
                                return <<<HTML
                                    <a class="new-tab" href="{$this->wiki->Href('',$id)}">$id</a>
                                    HTML;
                            },
                            $followedEntryIds
                        ))
                    ]),'success');
                }
                $this->wiki->Redirect($this->wiki->Href(
                    $this->isInIframe() ? 'iframe' : '', // handler
                    $firstId,
                    [
                        'message' => 'ajout_ok'
                    ],
                    false
                ));
                throw new Exception("Error Processing Request");
            } else {
                return $this->finalRender(
                    $this->render('@templates/alert-message.twig',[
                        'type' => 'info',
                        'message' => _t('DUPLICATION_TROUBLE')
                    ]).
                    $this->entryController->view($tag),true);
            }
        }
        if (!isset($_POST['bf_titre'])){
            foreach ($form['prepared'] as $field) {
                if ($field instanceof BazarField){
                    $propName = $field->getPropertyName();
                    if (!empty($propName) && isset($entry[$propName])){
                        $_POST[$propName] = $entry[$propName];
                        $_REQUEST[$propName] = $entry[$propName];
                    }
                }
            }
            // clean inputs
            if (isset($_POST['bf_titre'])){
                unset($_POST['bf_titre']);
            }
            $redirectUrl = $this->wiki->Href(
                $this->isInIframe() ? 'iframe' : '', // handler
                $tag
            );
            return $this->finalRender($this->entryController->create($form['bn_id_nature'], $redirectUrl),true);
        }
        $redirectUrl = $this->wiki->Href(
            $this->isInIframe() ? 'duplicateiframe' : 'duplicate', // handler
            $tag,
            ['created'=>true], // params
            false // html outputs ?
        );
        
        return $this->finalRender($this->entryController->create($form['bn_id_nature'], $redirectUrl),true);
    }

    protected function duplicatePage(string $tag): string
    {
        $message = '';
        $type = '';
        if (isset($_POST['newName'])){
            if(empty($_POST['newName']) || !is_string($_POST['newName'])){
                $type = 'warning';
                $message = _t('DUPLICATION_NOT_POSSIBLE_IF_NO_NAME');
            } else {
                $page = $this->pageManager->getOne($_POST['newName']);
                if (!empty($page)){
                    $type = 'danger';
                    $message = _t('DUPLICATION_NOT_POSSIBLE_IF_EXISTING');
                } else {
                    // fake body for new Tag
                    $this->wiki->page['tag'] = $_POST['newName'];
                    $this->wiki->tag = $_POST['newName'];
                    unset($_POST['submit']);
                    $this->wiki->method = $this->isInIframe() ? 'editiframe' : 'edit';
                    flash(_t('DUPLICATION_IN_COURSE',[
                        'originTag' => $tag,
                        'destinationTag' => $_POST['newName'],
                    ]),'info');
                    return $this->performer->run($this->wiki->method,'handler',[]);
                }
            }
        }
        return $this->finalRender($this->render('@core/duplicate-handler-ask-new-name.twig',[
            'type' => $type,
            'message' => $message,
            'isInIframe' => $this->isInIframe()
        ]));
    }

    protected function isInIframe()
    {
        return preg_match('/duplicateiframe(?:&|\?|$)/Ui', getAbsoluteUrl());
    }
}
