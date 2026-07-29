<?php

namespace YesWiki\Render\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\ThrowableFormatter;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\ThemeManager;

class FooterAction extends YesWikiAction implements RegisteredAction
{
    /** `{{footer}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'footer';
    }

    public function run()
    {
        try {
            $themeManager = $this->getService(ThemeManager::class);
            $themeLoaded = $themeManager->loadTheme();
        } catch (\Throwable $t) {
            // catch errors and exception to avoid a loop with error management in Performer
            $output = '<style>.alert-error-message{border: red solid 4px;background-color: #FE8;padding: 2px;color:gray;}</style>' . "\n";
            $output .= '<div class="alert-error-message alert">' . "\n";
            $output .= _t('PERFORMABLE_ERROR') . '<br/>' . $this->getService(ThrowableFormatter::class)->dump($t) . '<br/>';
            $output .= '<a href="' . $this->getService(UrlFormatter::class)->href() . '">Return</a>' . "\n";
            $output .= '</div>';

            return $output;
        }
        $output = null;
        if ($themeLoaded) {
            $output = $themeManager->renderFooter();
            // on affiche les requetes SQL et le temps de chargement en mode debug
            if ($this->wiki->GetConfigValue('debug')) {
                $debug_log_sql_queries = '';
                $T_SQL = 0;

                $queryLog = $this->getService(DbService::class)->getQueryLog();
                foreach ($queryLog as $query) {
                    $debug_log_sql_queries .= $query['query'] . ' (' . round($query['time'], 4) . ")<br />\n";
                    $T_SQL = $T_SQL + $query['time'];
                }

                // SQL queries maybe contain classified informations, so let's keep them for admins
                if (!$this->wiki->UserIsAdmin()) {
                    $debug_log_sql_queries = _t('LOGS_ARE_FOR_ADMINS_ONLY');
                }

                $end = microtime(true);
                $debug_log = "<div class=\"debug\">\n<h4>Query log</h4>\n";
                $debug_log .= '<strong>' . round($end - T_START, 4) . " s total time<br />\n";
                $debug_log .= round($T_SQL, 4) . ' s total SQL time</strong> (' . round(($T_SQL / ($end - T_START)) * 100, 2) . "% of total time)<br />\n";
                $debug_log .= '<strong>' . count($queryLog) . " queries :</strong><br />\n";
                $debug_log .= $debug_log_sql_queries;
                $debug_log .= "</div>\n";
                $output = (!empty($output)) ? str_replace('</body>', $debug_log . '</body>', $output) : $debug_log;
            }
        }

        return $output;
    }
}
