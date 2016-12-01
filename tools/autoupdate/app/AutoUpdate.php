<?php
namespace AutoUpdate;

class AutoUpdate
{
    const DEFAULT_REPO = 'https://repository.yeswiki.net/';
    const DEFAULT_VERS = 'Cercopitheque'; // Pour gérer les vielles version de
                                          // YesWiki
    private $wiki;
    public $repository = null;

    public function __construct($wiki)
    {
        $this->wiki = $wiki;
    }

    public function initRepository()
    {
        $this->repository = new Repository($this->repositoryAddress());
        return $this->repository->load();
    }

    public function isAdmin()
    {
        return $this->wiki->UserIsAdmin();
    }

    public function getWikiConfiguration()
    {
        $configuration = new Configuration(
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

    private function repositoryAddress()
    {
        $repositoryAddress = $this::DEFAULT_REPO;

        if (isset($this->wiki->config['yeswiki_repository'])) {
            $repositoryAddress = $this->wiki->config['yeswiki_repository'];
        }

        if (substr($repositoryAddress, -1, 1) !== '/') {
            $repositoryAddress .= '/';
        }

        $repositoryAddress .= $this->getYesWikiVersion();
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
