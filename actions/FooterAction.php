<?php

use YesWiki\Core\YesWikiAction;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\ThemeManager;

class FooterAction extends YesWikiAction
{
    public function run()
    {
        $themeManager = $this->getService(ThemeManager::class);
        $output = null;
        if ($themeManager->loadTheme()) {
            $output = $themeManager->renderFooter() ;
            // on affiche les requetes SQL et le temps de chargement en mode debug
            if ($this->wiki->GetConfigValue('debug')=='yes') {
                $debug_log_sql_queries = '';
                $T_SQL=0;
            
                $queryLog = $this->getService(DbService::class)->getQueryLog();
                foreach ($queryLog as $query) {
                    $debug_log_sql_queries .= $query['query'].' ('.round($query['time'], 4).")<br />\n";
                    $T_SQL = $T_SQL + $query['time'];
                }
            
                $end = microtime(true);
                $debug_log = "<div class=\"debug\">\n<h4>Query log</h4>\n";
                $debug_log .= "<strong>".round($end-T_START, 4)." s total time<br />\n";
                $debug_log .= round($T_SQL, 4)." s total SQL time</strong> (".round((($T_SQL/($end-T_START))*100), 2)."% of total time)<br />\n";
                $debug_log .= "<strong>".count($queryLog)." queries :</strong><br />\n";
                $debug_log .= $debug_log_sql_queries;
                $debug_log .= "</div>\n";
                $output = (!empty($output)) ? str_replace('</body>', $debug_log.'</body>', $output) : $debug_log ;
            }
        }
        return $output;
    }
}
