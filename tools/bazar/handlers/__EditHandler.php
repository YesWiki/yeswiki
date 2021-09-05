<?php

namespace YesWiki\Bazar;

use YesWiki\Bazar\Controller\EntryController;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Core\YesWikiHandler;

class __EditHandler extends YesWikiHandler
{
    public function run()
    {
        $entryManager = $this->getService(EntryManager::class);
        $entryController = $this->getService(EntryController::class);

        if ($this->wiki->HasAccess('write') && $entryManager->isEntry($this->wiki->GetPageTag())) {
            $plugin_output_new = $this->wiki->Header();
            $plugin_output_new .= '<div class="page">';
            $plugin_output_new .= $this->isWikiHibernated()
                ? $this->getMessageWhenHibernated()
                : $entryController->update($this->wiki->GetPageTag());
            $plugin_output_new .= '</div>';
            $plugin_output_new .= $this->wiki->Footer();

            // we use die so that the script stop there and the default handler of wiki isn't called
            die($plugin_output_new);
        }
    }
}
