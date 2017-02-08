<?php
namespace AutoUpdate;

class ViewStatus extends View
{
    public function __construct($autoUpdate)
    {
        parent::__construct($autoUpdate);
        $this->template = "status";
    }

    protected function grabInformations()
    {
        $infos = array(
            'baseUrl' => $this->autoUpdate->baseUrl(),
            'isAdmin' => $this->autoUpdate->isAdmin(),
            'AU_UPDATE' => _t('AU_UPDATE'),
            'AU_FORCE_UPDATE' => _t('AU_FORCE_UPDATE'),
            'AU_WARNING' => _t('AU_WARNING'),
            'AU_VERSION_REPO' => _t('AU_VERSION_REPO'),
            'AU_VERSION_WIKI' => _t('AU_VERSION_WIKI'),
            'AU_INSTALL' => _t('AU_INSTALL'),
            'AU_ABSENT' => _t('AU_ABSENT'),
            'AU_DELETE_EXT' => _t('AU_DELETE_EXT'),
            'AU_DOCUMENTATION_LINK' => _t('AU_DOCUMENTATION_LINK'),
            'core' => $this->autoUpdate->repository->getCorePackage(),
            'themes' =>
                $this->autoUpdate->repository->getThemesPackages(),
            'tools' =>
                $this->autoUpdate->repository->getToolsPackages(),
            'showCore' => true,
            'showThemes' => true,
            'showTools' => true,
        );
        return $infos;
    }
}
