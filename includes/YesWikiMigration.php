<?php

namespace YesWiki\Core;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Bazar\Service\FormManager;
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
     */
    protected function getService(string $className)
    {
        return $this->wiki->services->get($className);
    }

    /**
     * The forms of the `nature` table, ready for use and keyed by id: what a migration that predates form pages works on.
     */
    protected function legacyForms(): array
    {
        if ($this->dbService->count("SHOW TABLES LIKE '" . $this->dbService->escape(trim($this->dbService->prefixTable('nature'))) . "'") === 0) {
            return [];
        }
        $formManager = $this->getService(FormManager::class);
        $forms = [];
        foreach ($this->dbService->loadAll('SELECT * FROM ' . $this->dbService->prefixTable('nature') . 'ORDER BY bn_id_nature') as $row) {
            $forms[$row['bn_id_nature']] = $formManager->getFromRawData($row);
        }

        return $forms;
    }
}
