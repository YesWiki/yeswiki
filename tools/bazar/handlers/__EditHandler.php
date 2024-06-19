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
    }
}
