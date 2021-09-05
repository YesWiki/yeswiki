<?php

namespace YesWiki\Core\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Security\Controller\SecurityController;
use YesWiki\Wiki;

class LinkTracker
{
    protected $wiki;
    protected $dbService;
    protected $securityController;
    protected $userManager;
    protected $params;

    public $enabled;
    public $links;

    public function __construct(Wiki $wiki, DbService $dbService, UserManager $userManager, ParameterBagInterface $params, SecurityController $securityController)
    {
        $this->wiki = $wiki;
        $this->dbService = $dbService;
        $this->userManager = $userManager;
        $this->params = $params;
        $this->securityController = $securityController;

        $this->enabled = false;
        $this->links = [];
    }

    public function start() : bool
    {
        return $this->track(true);
    }

    public function stop() : bool
    {
        return $this->track(false);
    }

    public function track($newState = null) : bool
    {
        $oldState = $this->enabled;
        if ($newState !== null) {
            $this->enabled = $newState;
        }
        return $oldState;
    }

    public function forceAddIfNotIncluded(string $tag) : bool
    {
        $inclusions = $this->wiki->GetAllInclusions() ;
        if ($inclusions && count($inclusions) <2 && !in_array($tag, $this->links)) {
            $this->links[] = $tag ;
            return true ;
        } else {
            return false ;
        }
    }

    public function add($tag)
    {
        if ($this->track()) {
            $this->links[] = $tag;
        }
    }

    public function getAll() : array
    {
        return $this->links;
    }

    public function persist()
    {
        if ($this->securityController->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        $fromTag = $this->wiki->GetPageTag();
        
        // Delete old links for this page
        $this->dbService->query('DELETE FROM '.$this->dbService->prefixTable('links')."WHERE from_tag = '" . $this->dbService->escape($fromTag) . "'");
        
        if ($tags = $this->getAll()) {
            $written = [];
            foreach ($tags as $toTag) {
                if (!isset($written[strtolower($toTag)])) {
                    $this->dbService->query('INSERT INTO '.$this->dbService->prefixTable('links')."SET from_tag = '" . $this->dbService->escape($fromTag) . "', to_tag = '" . $this->dbService->escape($toTag) . "'");
                    $written[strtolower($toTag)] = 1;
                }
            }
        }
    }

    public function clear()
    {
        $this->links = [];
    }
}
