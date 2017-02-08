<?php
namespace AutoUpdate;

class ViewNoRepo extends View
{
    public function __construct($autoUpdate)
    {
        parent::__construct($autoUpdate);
        $this->template = "norepo";
    }

    protected function grabInformations()
    {
        $infos = array(
            //'wikiVersion' => $this->autoUpdate->getYesWikiRelease(),
            'AU_REPO_ERROR' => _t('AU_REPO_ERROR'),
            'AU_VERSION_REPO' => _t('AU_VERSION_REPO'),
            'AU_VERSION_WIKI' => _t('AU_VERSION_WIKI'),
        );
        return $infos;
    }
}
