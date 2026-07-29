<?php

namespace YesWiki\Core;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Kernel\Service\DbService;

abstract class YesWikiMigration
{
    /**
     * Every migration implements its body here (run by MigrationService).
     *
     * @return void
     */
    abstract public function run();

    protected ContainerInterface $services;
    protected $params;
    protected $dbService;

    public function setServices(ContainerInterface $services): void
    {
        $this->services = $services;
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
     */
    protected function getService(string $className)
    {
        return $this->services->get($className);
    }
}
