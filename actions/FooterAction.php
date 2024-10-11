<?php

use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\ThemeManager;
use YesWiki\Core\YesWikiAction;

class FooterAction extends YesWikiAction
{
    public function run()
    {
        try {
            $themeManager = $this->getService(ThemeManager::class);
            $themeLoaded = $themeManager->loadTheme();
        } catch (Throwable $t) {
            // catch errors and exception to avoid a loop with error management in Performer
            $output = '<style>.alert-error-message{border: red solid 4px;background-color: #FE8;padding: 2px;color:gray;}</style>' . "\n";
            $output .= '<div class="alert-error-message alert">' . "\n";
            $output .= _t('PERFORMABLE_ERROR') . '<br/>' . $t->getMessage() . ' in <i>' . $t->getFile();
            $output .= '</i> on line <i>' . $t->getLine() . '</i><br/>';
            $output .= '<a href="' . $this->wiki->Href() . '">Return</a>' . "\n";
            $output .= '</div>';

            return $output;
        }
        $output = null;
        if ($themeLoaded) {
            $output = $themeManager->renderFooter();
            // on affiche les requetes SQL et le temps de chargement en mode debug
            if ($this->wiki->GetConfigValue('debug') == 'yes') {
                $debug_log_sql_queries = '';
                $T_SQL = 0;

                $queryLog = $this->getService(DbService::class)->getQueryLog();
                foreach ($queryLog as $query) {
                    $debug_log_sql_queries .= $query['query'] . ' (' . round($query['time'], 4) . ")<br />\n";
                    $T_SQL = $T_SQL + $query['time'];
                }

                $end = microtime(true);
                $debug_log = "<div class=\"debug\">\n<h4>Query log</h4>\n";
                $debug_log .= '<strong>' . round($end - T_START, 4) . " s total time<br />\n";
                $debug_log .= round($T_SQL, 4) . ' s total SQL time</strong> (' . round((($T_SQL / ($end - T_START)) * 100), 2) . "% of total time)<br />\n";
                $debug_log .= '<strong>' . count($queryLog) . " queries :</strong><br />\n";
                $debug_log .= $debug_log_sql_queries;
                $debug_log .= "</div>\n";
                $output = (!empty($output)) ? str_replace('</body>', $debug_log . '</body>', $output) : $debug_log;
            }
        }

        return $output;
    }
}
