<?php

namespace YesWiki\Content\Action;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Content\Service\PageSummary;
use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\PerformableArguments;
use YesWiki\Kernel\Service\StringUtilService;
use YesWiki\Render\Service\MarkdownFormatterService;
use YesWiki\Render\Service\TemplateEngine;
use YesWiki\Search\Service\TagsManager;

/** `{{includepages}}` -- converted from the procedural actions/includepages.php by ticket 06. */
class IncludepagesAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'includepages';
    }

    public function run(): string
    {
        ob_start();
        try {
            $this->emit();
        } catch (\Throwable $t) {
            $this->output .= (string)ob_get_clean();

            throw $t;
        }

        return (string)ob_get_clean();
    }

    private function emit(): void
    {
        $nbcartrunc = 200;
        $output = '';
        $class = $this->getService(PerformableArguments::class)->get('class');
        $pages = $this->getService(PerformableArguments::class)->get('pages');

        if (empty($pages)) {
            $output .= '<div class="alert alert-danger"><strong>' . _t('TAGS_ACTION_INCLUDEPAGES') . '</strong> : ' . _t('TAGS_NO_PARAM_PAGES') . '</div>' . "\n";
        } else {
            $template = $this->getService(PerformableArguments::class)->get('template');
            if (empty($template)) {
                $template = 'pages_list.twig';
            }

            $aclService = $this->getService(AclService::class);
            $resultat = explode(',', $pages);
            $element = [];
            foreach ($resultat as $page) {
                $page = $this->getService(PageManager::class)->getOne(trim($page));
                if (!is_array($page) || !$aclService->hasAccess('read', $page['tag'])) {
                    continue;
                }
                $body = PageBody::content($page['body']);
                $element[$page['tag']]['tagnames'] = '';
                $element[$page['tag']]['tagbadges'] = '';
                $element[$page['tag']]['body'] = $body;
                $element[$page['tag']]['owner'] = $page['owner'];
                $element[$page['tag']]['user'] = $page['user'];
                $element[$page['tag']]['time'] = $page['time'];
                $element[$page['tag']]['title'] = $this->getService(PageSummary::class)->title($page);
                $element[$page['tag']]['image'] = $this->getService(PageSummary::class)->image($page);
                $element[$page['tag']]['desc'] = StringUtilService::truncateOnWord(strip_tags($this->getService(MarkdownFormatterService::class)->format($body)), $nbcartrunc);
                foreach (TagsManager::keywordsOf($page) as $keyword) {
                    $element[$page['tag']]['tagnames'] .= StringUtilService::withoutAccents($keyword) . ' ';
                    $element[$page['tag']]['tagbadges'] .= '<span class="label label-info">' . htmlspecialchars($keyword, ENT_QUOTES) . '</span>&nbsp;';
                }
            }

            $output .= $this->getService(TemplateEngine::class)->renderSafely("@core/$template", ['elements' => $element]);
        }

        if (empty($class)) {
            echo $output . "\n";
        } else {
            echo '<div class="' . $class . '">' . "\n" . $output . "\n" . '</div>' . "\n";
        }
    }
}
