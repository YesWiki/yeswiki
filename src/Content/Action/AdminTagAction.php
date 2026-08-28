<?php

namespace YesWiki\Content\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\CsrfTokenChecker;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Search\Service\TagsManager;

class AdminTagAction extends YesWikiAction implements RegisteredAction, ProvidesComponents
{
    /** `{{admintag}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'admintag';
    }

    public function components(): array
    {
        return [
            Component::for('admintag')
                ->category(Category::Admin)
                ->label(_t('AB_tags_admintag_label'))
                ->icon('tags')
                ->previewHeight('200px')
                ->adminOnly(),
        ];
    }

    /** @return string */
    public function run()
    {
        $isAdmin = $this->getService(AclService::class)->isAdmin();
        $tagsManager = $this->getService(TagsManager::class);

        $request = $this->getRequest();
        if ($isAdmin && $request->isMethod('POST') && $request->request->getString('delete_tag') !== '') {
            if ($this->getService(HibernationService::class)->isWikiHibernated()) {
                throw new \Exception(_t('WIKI_IN_HIBERNATION'));
            }
            try {
                $this->getService(CsrfTokenChecker::class)->checkToken('main', 'POST', 'csrf-token', false);
                $tagsManager->removeByIds(explode(',', $request->request->getString('delete_tag')));
            } catch (\Throwable $error) {
                return '<div class="alert alert-danger">' . htmlspecialchars($error->getMessage(), ENT_QUOTES) . '</div>';
            }
        }

        $rows = $tagsManager->getAllTriples();

        if (empty($rows)) {
            return '<div class="alert alert-info">' . _t('TAGS_NO_TAG') . '</div>';
        }

        $tags = [];
        foreach ($rows as $row) {
            $tagName = stripslashes($row['value']);
            $tags[$tagName][] = $row;
        }

        return $this->render('@core/admintag-action.twig', [
            'tags' => $tags,
            'isAdmin' => $isAdmin,
        ]);
    }
}
