<?php

namespace YesWiki\Admin\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Admin\Entity\Starter;
use YesWiki\Content\Entity\MenuNode;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Entity\PageType;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Files\Service\ProgramFiles;
use YesWiki\Render\Service\LayoutService;

/** The first screen of a fresh wiki: the Starters it offers, and what choosing them creates. */
class Onboarding
{
    public const STARTERS_DIRECTORY = 'src/Admin/starters';

    public const FORM_ID_PLACEHOLDER = '%formId%';

    public function __construct(
        private readonly ProgramFiles $programFiles,
        private readonly PageManager $pageManager,
        private readonly FormManager $formManager,
        private readonly LayoutService $layout,
        private readonly ParameterBagInterface $params,
    ) {
    }

    /** Whether the wiki is still waiting to be told what it is for, which is as long as it has no home page. */
    public function isPending(): bool
    {
        return !$this->pageManager->tagExists($this->rootPage());
    }

    public function rootPage(): string
    {
        $rootPage = $this->params->get('root_page');

        return is_string($rootPage) ? $rootPage : '';
    }

    /**
     * Every Starter shipped with the Program, by slug.
     *
     * @return array<string, Starter>
     */
    public function starters(): array
    {
        $starters = [];
        foreach ($this->programFiles->files(self::STARTERS_DIRECTORY) as $file) {
            $slug = basename($file, '.json');
            $starter = str_ends_with($file, '.json') ? Starter::fromJson($slug, $this->programFiles->read($file)) : null;
            if ($starter !== null) {
                $starters[$slug] = $starter;
            }
        }
        ksort($starters);

        return $starters;
    }

    /**
     * Create the chosen Starters and the home page, which ends the onboarding.
     *
     * @param list<string> $slugs
     *
     * @throws \InvalidArgumentException
     */
    public function apply(array $slugs): void
    {
        $starters = $this->starters();
        $unknown = array_diff($slugs, array_keys($starters));
        if ($unknown !== []) {
            throw new \InvalidArgumentException('Unknown starter: ' . implode(', ', $unknown));
        }

        $links = [];
        $navigation = [];
        foreach (array_values(array_unique($slugs)) as $slug) {
            $this->create($starters[$slug]);
            $links[] = $starters[$slug]->navigation;
            $navigation[] = ['id' => 'starter-' . $slug] + $starters[$slug]->navigation;
        }

        $this->extendNavigation($navigation);
        $this->pageManager->save($this->rootPage(), [PageBody::CONTENT => $this->homePage($links)], '', true, null, PageType::PAGE);
    }

    private function create(Starter $starter): void
    {
        foreach ($starter->lists as $list) {
            $this->saveIfFree($list['tag'], ['title' => $list['title'], 'nodes' => $list['nodes']], PageType::LIST);
        }

        $formId = $starter->form['id'] ?? null;
        if (!is_scalar($formId) || $formId === '' || $this->formManager->getOne($formId) !== null) {
            $formId = $this->formManager->findNewId();
        }
        $this->formManager->create(['id' => $formId] + $starter->form);

        foreach ($starter->pages as $tag => $content) {
            $this->saveIfFree($tag, [PageBody::CONTENT => str_replace(self::FORM_ID_PLACEHOLDER, (string)$formId, $content)], PageType::PAGE);
        }

        foreach ($starter->menus as $menu) {
            $this->saveIfFree($menu['tag'], ['title' => $menu['title'], 'nodes' => $this->nodes($menu['nodes'])], PageType::MENU);
        }
    }

    /** @param array<string, mixed> $body */
    private function saveIfFree(string $tag, array $body, string $type): void
    {
        if ($this->pageManager->tagExists($tag)) {
            return;
        }
        $this->pageManager->save($tag, $body, '', true, null, $type);
        $this->pageManager->cacheType($tag, $type);
    }

    /** @param list<array<mixed>> $entries */
    private function extendNavigation(array $entries): void
    {
        $tag = $this->layout->navbar();
        $menu = $tag === '' ? null : $this->pageManager->getOne($tag, null, false, true);
        if ($entries === [] || $menu === null) {
            return;
        }

        $body = $menu['body'];
        $body['nodes'] = array_merge(is_array($body['nodes'] ?? null) ? $body['nodes'] : [], $this->nodes($entries));
        $this->pageManager->save($tag, $body, '', true, null, PageType::MENU);
    }

    /**
     * @param list<array<mixed>> $nodes
     *
     * @return list<array<string, mixed>>
     */
    private function nodes(array $nodes): array
    {
        return array_values(array_filter(array_map(
            static fn (array $node): ?array => MenuNode::fromArray($node)?->toArray(),
            $nodes
        )));
    }

    /** @param list<array{label: string, link: string}> $links */
    private function homePage(array $links): string
    {
        $name = $this->params->get('yeswiki_name');
        $content = '# ' . (is_string($name) && $name !== '' ? $name : $this->rootPage()) . "\n\n" . _t('ONBOARDING_HOME_PAGE_INTRO') . "\n";
        if ($links !== []) {
            $content .= "\n" . implode("\n", array_map(static fn (array $link): string => '- [' . $link['label'] . '](' . $link['link'] . ')', $links)) . "\n";
        }

        return $content;
    }
}
