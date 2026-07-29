<?php

namespace YesWiki\Content\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\InclusionStack;
use YesWiki\Render\Service\MarkdownFormatterService;
use YesWiki\Render\Service\ThemeManager;

class LinkTracker
{
    protected ContainerInterface $container;
    protected $dbService;
    protected $hibernationService;
    protected InclusionStack $inclusionStack;
    protected $pageManager;
    protected $userManager;
    protected $params;

    public $enabled;
    public $links;

    public function __construct(ContainerInterface $container, DbService $dbService, PageManager $pageManager, UserManager $userManager, ParameterBagInterface $params, HibernationService $hibernationService, InclusionStack $inclusionStack)
    {
        $this->container = $container;
        $this->dbService = $dbService;
        $this->pageManager = $pageManager;
        $this->userManager = $userManager;
        $this->params = $params;
        $this->hibernationService = $hibernationService;
        $this->inclusionStack = $inclusionStack;

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
        $inclusions = $this->inclusionStack->getAll();
        if ($inclusions && count($inclusions) < 2 && !in_array($tag, $this->links)) {
            $this->links[] = $tag;

            return true;
        }

        return false;
    }

    public function add($tag)
    {
        if ($this->track() && $this->container->get(\YesWiki\Kernel\Service\PageContext::class)->getTag() !== $tag) {
            $this->links[] = $tag;
        }
    }

    public function getAll(): array
    {
        return $this->links;
    }

    public function persist()
    {
        if ($this->hibernationService->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        $fromTag = $this->container->get(\YesWiki\Kernel\Service\PageContext::class)->getTag();

        // Delete old links for this page
        $this->dbService->query('DELETE FROM ' . $this->dbService->prefixTable('links') . "WHERE from_tag = '" . $this->dbService->escape($fromTag) . "'");

        if ($tags = $this->getAll()) {
            $written = [];
            foreach ($tags as $toTag) {
                if (!isset($written[strtolower($toTag)])) {
                    $this->dbService->query('INSERT INTO ' . $this->dbService->prefixTable('links') . "(from_tag, to_tag) VALUES ('" . $this->dbService->escape($fromTag) . "', '" . $this->dbService->escape($toTag) . "')");
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
            $previousTag = $this->container->get(\YesWiki\Kernel\Service\PageContext::class)->getTag();
            $previousPage = $this->container->get(\YesWiki\Kernel\Service\PageContext::class)->getPage();
            $previousInclusions = $this->inclusionStack->replace();
        }
        $this->clear();
        $this->container->get(\YesWiki\Kernel\Service\PageContext::class)->setTag($page['tag'] ?? null);
        $this->container->get(\YesWiki\Kernel\Service\PageContext::class)->assignPage($page);
        $this->start();
        $this->inclusionStack->register($this->container->get(\YesWiki\Kernel\Service\PageContext::class)->getTag());
        $body = $this->preventTrackingActions($page['body']);
        $body = $this->preventNotTrackingActions($body);
        $this->container->get(MarkdownFormatterService::class)->format($body);
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
            return $tag !== $this->container->get(\YesWiki\Kernel\Service\PageContext::class)->getTag();
        });
        $this->links = $childrenTags;
        $this->persist();
        $this->inclusionStack->unregisterLast();
        $this->clear();

        if ($refreshPreviousTag) {
            if (!empty($previousTag) && !empty($previousPage)) {
                $this->container->get(\YesWiki\Kernel\Service\PageContext::class)->setTag($previousTag);
                $this->container->get(\YesWiki\Kernel\Service\PageContext::class)->assignPage($previousPage);
            }
            $this->inclusionStack->replace($previousInclusions);
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
