<?php

namespace YesWiki\Core;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Core\Service\DbService;
use YesWiki\Wiki;

abstract class YesWikiMigration
{
    protected $wiki;
    protected $params;
    protected $dbService;

    public function setWiki(Wiki $wiki): void
    {
        $this->wiki = $wiki;
    }

    /**
     * Setter for the parameters.
     */
    public function setParams(ParameterBagInterface $params): void
    {
        $this->params = $params;
    }

    public function setDbService(DbService $dbService): void
    {
        $this->dbService = $dbService;
    }

    /**
     * give service from name.
     *
     * @return mixed
     */
    protected function getService(string $className)
    {
        return $this->wiki->services->get($className);
    }
}