<?php


use YesWiki\Security\Controller\SecurityController;
use YesWiki\Core\YesWikiHandler;

class UpdateHandler extends YesWikiHandler
{
    public function run()
    {
        if ($this->getService(SecurityController::class)->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }

        $output = '';

        // BE CAREFULL this comment is used for extensions to add content above, don't delete it!
        $output .= '<!-- end handler /update -->';

        // if (!method_exists(Wiki::class, 'isCli') || !$this->wiki->isCli()) {
        //     $output = $this->wiki->header() . $output;
        //     // add button to return to previous page
        //     $output .= ;
        //     $output .= $this->wiki->footer();
        // }
        return $output;
    }
}