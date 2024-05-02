<?php

namespace YesWiki\Core;

use YesWiki\Core\Service\DbService;
use YesWiki\Wiki;

abstract class YesWikiMigration
{
  protected $wiki;
  protected $dbService;

  public function setWiki(Wiki $wiki): void
  {
    $this->wiki = $wiki;
  }

  public function setDbService(DbService $dbService): void
  {
    $this->dbService = $dbService;
  }
}