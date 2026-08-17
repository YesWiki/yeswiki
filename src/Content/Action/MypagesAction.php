<?php

namespace YesWiki\Content\Action;

use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Render\Service\LinkRenderer;

/** `{{mypages}}` -- converted from the procedural actions/mypages.php by ticket 06. */
class MypagesAction extends YesWikiAction implements RegisteredAction, ProvidesComponents
{
    public static function performableName(): string
    {
        return 'mypages';
    }

    public function components(): array
    {
        return [
            Component::for('mypages')
                ->category(Category::Admin)
                ->label(_t('AB_advanced_action_mypages_label'))
                ->icon('file')
                ->previewHeight('200px'),
        ];
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
        if ($user = $this->getService(AuthenticationService::class)->getLoggedUser()) {
            echo '<b>' . _t('LIST_OF_PAGES_WHERE_YOU_ARE_THE_OWNER') . ".</b><br /><br />\n";

            $my_pages_count = 0;
            $curChar = '';

            if ($pages = $this->getService(PageManager::class)->getAll()) {
                foreach ($pages as $page) {
                    if ($this->getService(AuthenticationService::class)->getLoggedUserName() == $page['owner'] && !preg_match('/^Comment/', $page['tag'])) {
                        $firstChar = strtoupper($page['tag'][0]);
                        if (!preg_match('/' . WN_UPPER . '/', $firstChar)) {
                            $firstChar = '#';
                        }

                        if ($firstChar != $curChar) {
                            if ($curChar) {
                                echo "<br />\n";
                            }
                            echo "<b>$firstChar</b><br />\n";
                            $curChar = $firstChar;
                        }

                        echo $this->getService(LinkRenderer::class)->linkToPage($page['tag']),"<br />\n";

                        $my_pages_count++;
                    }
                }

                if ($my_pages_count == 0) {
                    echo '<i>' . _t('YOU_DONT_OWN_ANY_PAGE') . '.</i>';
                }
            } else {
                echo '<i>' . _t('NO_PAGE_FOUND') . '.</i>';
            }
        } else {
            echo '<div class="alert alert-danger">' . _t('YOU_ARENT_LOGGED_IN') . ' : ' . _t('IMPOSSIBLE_TO_SHOW_YOUR_MODIFIED_PAGES') . ".</div>\n";
        }
    }
}
