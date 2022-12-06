<?php

namespace AutoUpdate;

use YesWiki\Core\Service\ConfigurationService;

class AutoUpdate
{
    public const DEFAULT_REPO = 'https://repository.yeswiki.net/';
    public const DEFAULT_VERS = 'Cercopitheque'; // Pour gérer les vielles version de
    // YesWiki
    private $wiki;
    public $repository = null;

    protected $configurationService;

    public function __construct($wiki)
    {
        $this->wiki = $wiki;
        $this->configurationService = $wiki->services->get(ConfigurationService::class);
    }

    /*	Parameter $requestedVersion contains the name of the YesWiki version
        requested by version parameter of {{update}} action
        if empty, no specifc version is requested
    */
    public function initRepository($requestedVersion='')
    {
        $this->repository = new Repository($this->repositoryAddress($requestedVersion), $this->wiki->getDataPath());
        return $this->repository->load();
    }

    public function isAdmin()
    {
        return $this->wiki->UserIsAdmin();
    }

    public function getWikiConfiguration()
    {
        $configuration = $this->configurationService->getConfiguration(
            $this->getWikiDir() . '/wakka.config.php'
        );
        $configuration->load();
        return $configuration;
    }

    public function baseUrl()
    {
        return $this->wiki->config['base_url'] . $this->wiki->tag;
    }

    private function getWikiDir()
    {
        return dirname(dirname(dirname(__DIR__)));
    }

    /*	Parameter $requestedVersion contains the name of the YesWiki version
        requested by version parameter of {{update}} action
        if empty, no specifc version is requested
    */
    private function repositoryAddress($requestedVersion='')
    {
        $repositoryAddress = $this::DEFAULT_REPO;

        if (isset($this->wiki->config['yeswiki_repository'])) {
            $repositoryAddress = $this->wiki->config['yeswiki_repository'];
        }

        if (substr($repositoryAddress, -1, 1) !== '/') {
            $repositoryAddress .= '/';
        }

        if ($requestedVersion != '') {
            $repositoryAddress .= strtolower($requestedVersion);
        } else {
            $repositoryAddress .= $this->getYesWikiVersion();
        }
        return $repositoryAddress;
    }

    private function getYesWikiVersion()
    {
        $version = $this::DEFAULT_VERS;
        if (isset($this->wiki->config['yeswiki_version'])) {
            $version = $this->wiki->config['yeswiki_version'];
        }
        return strtolower($version);
    }
}
