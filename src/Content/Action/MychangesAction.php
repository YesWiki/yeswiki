<?php

namespace YesWiki\Content\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Component\Setting;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\PerformableArguments;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Render\Service\LinkRenderer;

/** `{{mychanges}}` -- converted from the procedural actions/mychanges.php by ticket 06. */
class MychangesAction extends YesWikiAction implements RegisteredAction, ProvidesComponents
{
    public static function performableName(): string
    {
        return 'mychanges';
    }

    public function components(): array
    {
        return [
            Component::for('mychanges')
                ->category(Category::Admin)
                ->label(_t('AB_advanced_action_mychanges_label'))
                ->icon('history')
                ->previewHeight('200px')
                ->settings(
                    Setting::checkbox('bydate')
                        ->label(_t('AB_advanced_action_mychanges_bydate_label'))
                        ->default('')
                        ->checkedValues('1', ''),
                ),
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
            $my_edits_count = 0;
            $curChar = '';
            $curday = '';
            $last_tag = '';
            $dbService = $this->getService(DbService::class);
            $userCol = $dbService->quoteIdentifier('user');

            if ($bydate = $this->getService(PerformableArguments::class)->get('bydate')) {
                echo '<b>' . _t('YOUR_MODIFIED_PAGES_ORDERED_BY_MODIFICATION_DATE') . ".</b><br /><br />\n";

                if ($pages = $this->getService(DbService::class)->loadAll(
                    'SELECT tag, time FROM ' . $this->getService(RuntimeConfig::class)['table_prefix']
                    . "pages WHERE $userCol = ? AND tag NOT LIKE 'Comment%' ORDER BY time ASC, tag ASC",
                    [$this->getService(AuthenticationService::class)->getLoggedUserName()]
                )) {
                    foreach ($pages as $page) {
                        $edited_pages[$page['tag']] = $page['time'];
                    }

                    arsort($edited_pages);

                    foreach ($edited_pages as $page['tag'] => $page['time']) {
                        list($day, $time) = explode(' ', $page['time']);
                        if ($day != $curday) {
                            if ($curday) {
                                echo "<br />\n";
                            }
                            echo "<b>$day:</b><br />\n";
                            $curday = $day;
                        }

                        echo "&nbsp;&nbsp;&nbsp;($time) (",$this->getService(LinkRenderer::class)->linkToPage($page['tag'], 'revisions', 'history'),') ',$this->getService(LinkRenderer::class)->linkToPage($page['tag'], '', ''),"<br />\n";
                    }
                // no "you modified nothing" test here: this branch prints one line per row
                // of a result set the `if` above already found non-empty, so the count it
                // used to keep could never be 0. The empty case is the `else` below.
                } else {
                    echo '<i>' . _t('NO_PAGE_FOUND') . '.</i>';
                }
            } else {
                echo '<b>' . _t('YOUR_MODIFIED_PAGES_ORDERED_BY_NAME') . ".</b><br /><br />\n";

                if ($pages = $this->getService(DbService::class)->loadAll(
                    'SELECT tag, time FROM ' . $this->getService(RuntimeConfig::class)['table_prefix']
                    . "pages WHERE $userCol = ? AND tag NOT LIKE 'Comment%' ORDER BY tag ASC, time DESC",
                    [$this->getService(AuthenticationService::class)->getLoggedUserName()]
                )) {
                    foreach ($pages as $page) {
                        if ($last_tag != $page['tag']) {
                            $last_tag = $page['tag'];

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

                            echo '&nbsp;&nbsp;&nbsp;(',$page['time'],') (',$this->getService(LinkRenderer::class)->linkToPage($page['tag'], 'revisions', 'history'),') ',$this->getService(LinkRenderer::class)->linkToPage($page['tag'], '', ''),"<br />\n";

                            $my_edits_count++;
                        }
                    }

                    if ($my_edits_count == 0) {
                        echo '<i>' . _t('YOU_DIDNT_MODIFY_ANY_PAGE') . '.</i>';
                    }
                } else {
                    echo '<i>' . _t('NO_PAGE_FOUND') . '.</i>';
                }
            }
        } else {
            echo '<div class="alert alert-danger">' . _t('YOU_ARENT_LOGGED_IN') . ' : ' . _t('IMPOSSIBLE_TO_SHOW_YOUR_MODIFIED_PAGES') . ".</div>\n";
        }
    }
}
