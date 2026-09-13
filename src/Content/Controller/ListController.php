<?php

namespace YesWiki\Content\Controller;

use YesWiki\Content\Action\BazarAction;
use YesWiki\Content\Entity\Translations;
use YesWiki\Content\Service\ContentNotifier;
use YesWiki\Content\Service\ListManager;
use YesWiki\Content\Service\ListOverview;
use YesWiki\Content\Service\TranslatableContent;
use YesWiki\Core\YesWikiController;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Service\Redirector;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\LanguageSwitch;

class ListController extends YesWikiController
{
    protected ListManager $listManager;
    protected AclService $aclService;
    private ListOverview $listOverview;

    public function __construct(
        ListManager $listManager,
        AclService $aclService,
        ListOverview $listOverview
    ) {
        $this->listManager = $listManager;
        $this->aclService = $aclService;
        $this->listOverview = $listOverview;
    }

    public function displayAll(): string
    {
        $post = $this->getRequest()->request;
        $refusal = '';
        if ($post->has('imported-list')) {
            if ($this->listOverview->mayCreate()) {
                foreach ($post->all('imported-list') as $listRaw) {
                    $list = is_string($listRaw) ? json_decode($listRaw, true) : null;
                    if (!is_array($list) || !isset($list['title'])) {
                        continue;
                    }
                    $this->listManager->create($list['title'], $list['nodes'] ?? null);
                }
                echo '<div class="alert alert-success">' . _t('BAZ_LIST_IMPORT_SUCCESSFULL') . '.</div>';
            } else {
                $refusal = $this->refusal();
            }
        }

        return $refusal . $this->render('@core/lists/list_table.twig', $this->listOverview->all());
    }

    /**
     * Every way into this controller says who it is for, because the *screen* no longer does: it used to sit behind `/admin/lists` and the route's `@admins` was the only check anywhere -- creating, importing and deleting a list asked nothing at all.
     */
    private function refusal(): string
    {
        return (string)$this->render('@core/alert-message.twig', [
            'type' => 'danger',
            'message' => _t('BAZ_DROIT_INSUFFISANT'),
        ]);
    }

    public function create(): string
    {
        if (!$this->listOverview->mayCreate()) {
            return $this->refusal();
        }

        $post = $this->getRequest()->request;
        if ($post->has('submit')) {
            $title = (string)$post->get('title', '');
            $listId = $this->listManager->create($title, json_decode((string)$post->get('nodes', ''), true));

            if ($this->shouldPostMessageOnSubmit()) {
                return $this->render('@core/iframe_result.twig', [
                    'data' => ['msg' => 'list_created', 'id' => $listId, 'title' => $title],
                ]);
            }

            $this->getService(Redirector::class)->redirect(
                $this->getService(UrlFormatter::class)->href('', '', [BazarAction::URL_VIEW_PARAM => BazarAction::VIEW_LISTS], false)
            );
        }

        return $this->render('@core/lists/list_form.twig', [
            'list' => ['title' => '', 'nodes' => []],
        ]);
    }

    private function shouldPostMessageOnSubmit(): bool
    {
        return $this->getRequest()->query->get('onsubmit') === 'postmessage';
    }

    /**
     * @param mixed $id the list id, straight off the query string
     */
    public function update($id): string
    {
        if (!$this->listOverview->mayEdit((string)$id)) {
            return $this->refusal();
        }
        $stored = $this->listManager->getUntranslated($id);
        $translatable = $this->getService(TranslatableContent::class);
        $source = $translatable->wikiLanguage();
        $editing = $translatable->editingLanguage($this->getRequest()->query->get('editlang'), $source);
        $translating = $editing !== $source;

        $list = $this->listForEditing($stored ?? [], $editing, $source);
        $post = $this->getRequest()->request;
        if ($post->has('submit')) {
            if ($this->aclService->hasAccess('write', $id)) {
                $title = (string)$post->get('title', '');
                if ($translating) {
                    $this->listManager->saveTranslations((string)$id, $editing, $translatable->sanitize(
                        $this->postedTranslations($title, json_decode((string)$post->get('nodes', ''), true)),
                        $translatable->listPaths($stored ?? [])
                    ));

                    return $this->getService(Redirector::class)->redirect(
                        $this->getService(UrlFormatter::class)->href('', '', [BazarAction::URL_VIEW_PARAM => BazarAction::VIEW_LISTS], false)
                    );
                }

                $this->listManager->update($id, $title, json_decode((string)$post->get('nodes', ''), true));

                if ($this->shouldPostMessageOnSubmit()) {
                    return $this->render('@core/iframe_result.twig', [
                        'data' => ['msg' => 'list_updated', 'id' => $id, 'title' => $title],
                    ]);
                }

                $this->getService(Redirector::class)->redirect(
                    $this->getService(UrlFormatter::class)->href('', '', [BazarAction::URL_VIEW_PARAM => BazarAction::VIEW_LISTS], false)
                );
            } else {
                throw new \Exception('Not allowed');
            }
        }

        $this->getService(LanguageSwitch::class)->writing(
            $translatable->editingLanguages($source, $editing, $stored ?? [], $translatable->listPaths($stored ?? []))
        );

        return $this->render('@core/lists/list_form.twig', [
            'list' => $list,
            'editLanguage' => $editing,
            'editingTranslation' => $translating,
        ]);
    }

    /**
     * The editor's post, as the paths a value list's translations are addressed by.
     *
     * @param array<array-key, mixed>|null $nodes
     *
     * @return array<string, string>
     */
    private function postedTranslations(string $title, ?array $nodes): array
    {
        $values = ['title' => $title];
        self::collectNodeLabels(is_array($nodes) ? $nodes : [], 'nodes', $values);

        return $values;
    }

    /**
     * @param array<array-key, mixed> $nodes
     * @param array<string, string>   &$values
     */
    private static function collectNodeLabels(array $nodes, string $prefix, array &$values): void
    {
        foreach ($nodes as $node) {
            if (!is_array($node) || !isset($node['id'])) {
                continue;
            }
            $path = $prefix . '.' . (string)$node['id'];
            if (isset($node['label']) && is_scalar($node['label'])) {
                $values[$path . '.label'] = (string)$node['label'];
            }
            if (is_array($node['children'] ?? null)) {
                self::collectNodeLabels($node['children'], $path . '.children', $values);
            }
        }
    }

    /**
     * The list as the editor should show it for $language: its translations, blank where it has none.
     *
     * @param array<string, mixed> $list
     *
     * @return array<string, mixed>
     */
    private function listForEditing(array $list, string $language, string $source): array
    {
        if ($list === [] || $language === $source) {
            return $list;
        }

        $blanked = Translations::strip($list);
        $blanked['title'] = '';
        $blanked['nodes'] = self::blankLabels($blanked['nodes'] ?? []);

        return Translations::applied($blanked, Translations::of($list, $language));
    }

    /**
     * Blank every label, keeping what it said as `sourceLabel` so the editor can show a
     * translator what they are translating. The editor never posts that key back.
     *
     * @param array<array-key, mixed> $nodes
     *
     * @return array<array-key, mixed>
     */
    private static function blankLabels(array $nodes): array
    {
        foreach ($nodes as $index => $node) {
            if (!is_array($node)) {
                continue;
            }
            $nodes[$index]['sourceLabel'] = (string)($node['label'] ?? '');
            $nodes[$index]['label'] = '';
            if (is_array($node['children'] ?? null)) {
                $nodes[$index]['children'] = self::blankLabels($node['children']);
            }
        }

        return $nodes;
    }

    /**
     * @param mixed $id the list id, straight off the query string
     */
    public function delete($id): string
    {
        if (!$this->listOverview->mayDelete((string)$id)) {
            return $this->refusal();
        }
        $this->listManager->delete($id);

        if ($this->getService(RuntimeConfig::class)['BAZ_ENVOI_MAIL_ADMIN']) {
            $this->getService(ContentNotifier::class)->notifyAdminsListDeleted($id);
        }

        $this->getService(Redirector::class)->redirect(
            $this->getService(UrlFormatter::class)->href('', '', [BazarAction::URL_VIEW_PARAM => BazarAction::VIEW_LISTS], false)
        );
    }
}
