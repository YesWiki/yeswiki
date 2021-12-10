<?php
// DEPRECIATED keep same filename without class to prevent error at update
use YesWiki\Core\Service\DbService;

if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

if (!empty($this->config['use_fallback_theme'])) {
    $chemin_theme = 'themes/'.$this->config['favorite_theme'].'/squelettes/'.$this->config['favorite_squelette'];
    $file_content = file_get_contents($chemin_theme);
} else {
    $chemin_theme = 'themes/'.$this->config['favorite_theme'].'/squelettes/'.$this->config['favorite_squelette'];
    if (file_exists('custom/'.$chemin_theme)) {
        $file_content = file_get_contents('custom/'.$chemin_theme);
    } else {
        $file_content = file_get_contents($chemin_theme);
    }
}

//On recupere la partie bas du template et on execute les actions wikini
$template_decoupe = explode("{WIKINI_PAGE}", $file_content);
$template_footer = $template_decoupe[1];

if ($act = preg_match_all("/".'(\\{\\{)'.'(.*?)'.'(\\}\\})'."/is", $template_footer, $matches)) {
    $i = 0;
    $j = 0;
    foreach ($matches as $valeur) {
        foreach ($valeur as $val) {
            if (isset($matches[2][$j]) && $matches[2][$j]!='') {
                $action= $matches[2][$j];
                $template_footer = str_replace('{{'.$action.'}}', $this->Format('{{'.$action.'}}', 'action'), $template_footer);
            }
            $j++;
        }
        $i++;
    }
}

// on affiche les requetes SQL et le temps de chargement en mode debug
if ($this->GetConfigValue('debug')=='yes') {
    $debug_log_sql_queries = '';
    $T_SQL=0;

    $queryLog = $this->services->get(DbService::class)->getQueryLog();
    foreach ($queryLog as $query) {
        $debug_log_sql_queries .= $query['query'].' ('.round($query['time'], 4).")<br />\n";
        $T_SQL = $T_SQL + $query['time'];
    }

    $end = microtime(true);
    $debug_log = "<div>DEPRECIATED FOOTER USED FOR BACKUP</div>\n";
    $debug_log .= "<div class=\"debug\">\n<h4>Query log</h4>\n";
    $debug_log .= "<strong>".round($end-T_START, 4)." s total time<br />\n";
    $debug_log .= round($T_SQL, 4)." s total SQL time</strong> (".round((($T_SQL/($end-T_START))*100), 2)."% of total time)<br />\n";
    $debug_log .= "<strong>".count($queryLog)." queries :</strong><br />\n";
    $debug_log .= $debug_log_sql_queries;
    $debug_log .= "</div>\n";
    $template_footer = str_replace('</body>', $debug_log.'</body>', $template_footer);
}

echo $template_footer;
