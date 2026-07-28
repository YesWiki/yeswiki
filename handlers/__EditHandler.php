<?php

use YesWiki\Core\Controller\EntryController;
use YesWiki\Identity\Service\InputFilter;
use YesWiki\Identity\Service\AclService;
use YesWiki\Core\Service\EntryManager;
use YesWiki\Search\Service\TagsManager;
use YesWiki\Core\YesWikiHandler;

class __EditHandler extends YesWikiHandler
{
    public function run()
    {
        // relocated from tools/bazar/handlers/__EditHandler.php (ticket 24): if the page
        // being edited is a bazar entry, show the entry-edit form instead of the default
        // wiki-page edit form, and stop there -- runs before the tag-saving logic below,
        // matching this callback's actual pre-relocation execution order.
        $entryManager = $this->getService(EntryManager::class);
        $entryController = $this->getService(EntryController::class);

        if ($this->wiki->HasAccess('write') && $entryManager->isEntry($this->wiki->GetPageTag())) {
            $this->output = '<div class="page">';
            ob_start();
            $this->output .= $this->isWikiHibernated()
                ? $this->getMessageWhenHibernated()
                : $entryController->update($this->wiki->GetPageTag());
            $this->output .= ob_get_contents();
            ob_end_clean();
            $this->output .= '</div>';

            $this->output = $this->wiki->Header() . $this->output;
            $this->output .= $this->wiki->Footer();

            // we use die so that the script stop there and the default handler of wiki isn't called
            $this->wiki->exit($this->output);
        }

        // get services
        $aclService = $this->getService(AclService::class);
        $tagsManager = $this->getService(TagsManager::class);

        if (
            !$this->params->get('hide_keywords')
            && $aclService->hasAccess('write')
        ) {
            // save new tag if authorized
            $post = $this->getRequest()->request;
            if (
                $post->get('submit') == InputFilter::EDIT_PAGE_SUBMIT_VALUE
                && $post->has('pagetags')
                && $post->get('antispam') == 1
            ) {
                $tagsManager->save($this->wiki->GetPageTag(), stripslashes($post->get('pagetags')));
            }

            // display: the live-search tag-input widget (javascripts/yw-tags-input.js)
            // queries GET /api/tags itself as the user types -- no tag list to dump here
            if ($aclService->hasAccess('read')) {
                $this->wiki->AddJavascriptFile('javascripts/yw-tags-input.js');
            }
        }
    }
}
