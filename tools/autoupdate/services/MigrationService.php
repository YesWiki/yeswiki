<?php

namespace YesWiki\AutoUpdate\Service;

use Exception;
use ReflectionClass;
use ReflectionMethod;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Security\Controller\SecurityController;
use YesWiki\Wiki;

// This is a simple mecanism to perform migrations
// Create a new private method in this class, it will be run after the wiki get updated
class MigrationService
{
  public const TRIPLES_MIGRATION_ID = 'migration';
  private $wiki;

  public function __construct(Wiki $wiki)
  {
    $this->wiki = $wiki;
  }

  function run()
  {
    if ($this->wiki->services->get(SecurityController::class)->isWikiHibernated()) {
      throw new Exception(_t('WIKI_IN_HIBERNATION'));
    }

    // All private methods are considered as migrations
    $reflection = new ReflectionClass($this);
    $migrationsMethods = $reflection->getMethods(ReflectionMethod::IS_PRIVATE);
    $tripleStore = $this->wiki->services->get(TripleStore::class);

    $messages = [];
    foreach ($migrationsMethods as $method) {
      $methodName = $method->getName();
      try {
        // Check if migration is already done
        if (!$tripleStore->exist($methodName, TripleStore::TYPE_URI, self::TRIPLES_MIGRATION_ID, '', '')) {
          // peform migration by calling the method
          $this->$methodName();
          // Mark the migration as done by writing a new triple in the DB
          $tripleStore->create($methodName, TripleStore::TYPE_URI, self::TRIPLES_MIGRATION_ID, '', '');

          $messages[] = ['status' => _t('AU_OK'), 'text' => "Migration $methodName"];
        }
      } catch (Exception $e) {
        $messages[] = ['status' => _t('AU_ERROR'), 'text' => "Migration $methodName failed with error {$e->getMessage()}"];
      }
    }
    return $messages;
  }


  // All private methods below are considered as migrations
  // Add new migration on the top

  // private function newMigration() {}
  // private function oldMigration() {}
}