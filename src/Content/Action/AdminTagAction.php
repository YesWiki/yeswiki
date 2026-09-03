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
    /** What separates a keyword from its Content in a posted pair: the one character neither can hold. */
    public const PAIR_SEPARATOR = "\x1f";

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
                $tagsManager->remove(self::pairsPosted($request->request->getString('delete_tag')));
            } catch (\Throwable $error) {
                return '<div class="alert alert-danger">' . htmlspecialchars($error->getMessage(), ENT_QUOTES) . '</div>';
            }
        }

        $pairs = $tagsManager->pairs();

        if (empty($pairs)) {
            return '<div class="alert alert-info">' . _t('TAGS_NO_TAG') . '</div>';
        }

        $tags = [];
        foreach ($pairs as $pair) {
            $tags[stripslashes($pair['keyword'])][] = $pair;
        }

        return $this->render('@core/admintag-action.twig', [
            'tags' => $tags,
            'isAdmin' => $isAdmin,
            'separator' => self::PAIR_SEPARATOR,
        ]);
    }

    /**
     * The pairs the form posted, as `keyword<US>tag` joined by newlines.
     *
     * The screen used to post triple ids, because that is what it was addressing. `search_keywords`
     * is keyed by the pair itself and has no surrogate id, so the pair is what travels -- which is
     * also what the button has always meant (ticket 62). The unit separator is the one character a
     * keyword cannot contain, so nothing has to be escaped.
     *
     * @return list<array{keyword: string, tag: string}>
     */
    private static function pairsPosted(string $posted): array
    {
        $pairs = [];
        foreach (explode("\n", $posted) as $line) {
            $parts = explode(self::PAIR_SEPARATOR, trim($line), 2);
            if (count($parts) === 2 && $parts[0] !== '' && $parts[1] !== '') {
                $pairs[] = ['keyword' => $parts[0], 'tag' => $parts[1]];
            }
        }

        return $pairs;
    }
}
