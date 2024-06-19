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
    protected $pageManager;
    protected $userManager;
    protected $params;

    public $enabled;
    public $links;

    public function __construct(Wiki $wiki, DbService $dbService, PageManager $pageManager, UserManager $userManager, ParameterBagInterface $params, SecurityController $securityController)
    {
        $this->wiki = $wiki;
        $this->dbService = $dbService;
        $this->pageManager = $pageManager;
        $this->userManager = $userManager;
        $this->params = $params;
        $this->securityController = $securityController;

        $this->enabled = false;
        $this->links = [];
    }

    public function start(): bool
    {
        return $this->track(true);
    }

    public function stop(): bool
    {
        return $this->track(false);
    }

    public function track($newState = null): bool
    {
        $oldState = $this->enabled;
        if ($newState !== null) {
            $this->enabled = $newState;
        }

        return $oldState;
    }

    public function forceAddIfNotIncluded(string $tag): bool
    {
        $inclusions = $this->wiki->GetAllInclusions();
        if ($inclusions && count($inclusions) < 2 && !in_array($tag, $this->links)) {
            $this->links[] = $tag;

            return true;
        } else {
            return false;
        }
    }

    public function add($tag)
    {
        if ($this->track() && $this->wiki->tag !== $tag) {
            $this->links[] = $tag;
        }
    }

    public function getAll(): array
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
        $this->dbService->query('DELETE FROM ' . $this->dbService->prefixTable('links') . "WHERE from_tag = '" . $this->dbService->escape($fromTag) . "'");

        if ($tags = $this->getAll()) {
            $written = [];
            foreach ($tags as $toTag) {
                if (!isset($written[strtolower($toTag)])) {
                    $this->dbService->query('INSERT INTO ' . $this->dbService->prefixTable('links') . "SET from_tag = '" . $this->dbService->escape($fromTag) . "', to_tag = '" . $this->dbService->escape($toTag) . "'");
                    $written[strtolower($toTag)] = 1;
                }
            }
        }
    }

    public function clear()
    {
        $this->links = [];
    }

    /**
     * register links for the $pageTag.
     *
     * @return array $childrenTags
     */
    public function registerLinks(array $page, bool $trackMetadata = false, bool $refreshPreviousTag = true): array
    {
        if ($refreshPreviousTag) {
            $previousTag = $this->wiki->tag;
            $previousPage = $this->wiki->page;
            $previousInclusions = $this->wiki->SetInclusions();
        }
        $this->clear();
        $this->wiki->tag = $page['tag'] ?? null;
        $this->wiki->setPage($page);
        $this->start();
        $this->wiki->RegisterInclusion($this->wiki->tag);
        $body = $this->preventTrackingActions($page['body']);
        $body = $this->preventNotTrackingActions($body);
        $this->wiki->Format($body);
        if (!empty($page['owner'])) {
            $ownerPage = $this->pageManager->getOne($page['owner']);
            if (!empty($ownerPage)) {
                $this->add($page['owner']);
            }
        }
        if ($trackMetadata && !empty($page['metadatas'])) {
            foreach (ThemeManager::SPECIAL_METADATA as $specialPageKey) {
                if ($specialPageKey !== 'favorite_preset' && !empty($page['metadatas'][$specialPageKey])) {
                    $specialPage = $this->pageManager->getOne($page['metadatas'][$specialPageKey]);
                    if (!empty($specialPage)) {
                        $this->add($specialPage['tag']);
                    }
                }
            }
        }
        $this->stop();
        $childrenTags = array_filter($this->getAll(), function ($tag) {
            return $tag !== $this->wiki->tag;
        });
        $this->links = $childrenTags;
        $this->persist();
        $this->wiki->UnregisterLastInclusion();
        $this->clear();

        if ($refreshPreviousTag) {
            if (!empty($previousTag) && !empty($previousPage)) {
                $this->wiki->tag = $previousTag;
                $this->wiki->setPage($previousPage);
            }
            $this->wiki->SetInclusions($previousInclusions);
        }

        return $childrenTags;
    }

    private function preventTrackingActions(string $body): string
    {
        if (preg_match_all('/{{(?:include\\s*page="|redirect\\s*page="|listpages\\s*tree="|bazar [^}]*redirecturl="(?<!http:\\/\\/|https:\\/\\/))([^"]*)"\\s*[^}]*}}/i', $body, $matches)) {
            foreach ($matches[0] as $key => $value) {
                $body = str_replace($matches[0][$key], '', $body);
                $page = $this->pageManager->getOne($matches[1][$key]);
                if (!empty($page)) {
                    $this->add($page['tag']);
                }
            }
        }

        return $body;
    }

    private function preventNotTrackingActions(string $body): string
    {
        if (preg_match_all('/{{(?:gerertheme|setwikidefaulttheme|admintag|editgroups|userstable|editconfig|gererdroits|update\\s*(?:version="[^"]*")?|syndication(?:[^}]|\\s)*|listpages(?:[^}]|\\s)*|bazarliste(?:[^}]|\\s)*|bazarcarto(?:[^}]|\\s)*|bazar(?:[^}]|\\s)*)\\s*}}/i', $body, $matches)) {
            foreach ($matches[0] as $key => $value) {
                $body = str_replace($matches[0][$key], '', $body);
            }
        }

        return $body;
    }
}
