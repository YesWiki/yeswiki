<?php
// DEPRECIATED keep same filename without class to prevent error at update
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}
//=======Restes de wikini=================================================================================
$user = $this->GetUser();

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

//On recupere la partie haut du template et on execute les actions wikini
$template_decoupe = explode("{WIKINI_PAGE}", $file_content);
$template_header = $template_decoupe[0];

if ($act = preg_match_all("/".'(\\{\\{)'.'(.*?)'.'(\\}\\})'."/is", $template_header, $matches)) {
    $i = 0;
    $j = 0;
    foreach ($matches as $valeur) {
        foreach ($valeur as $val) {
            if (isset($matches[2][$j]) && $matches[2][$j]!='') {
                $action = $matches[2][$j];
                $template_header = str_replace('{{'.$action.'}}', $this->Format('{{'.$action.'}}', 'action'), $template_header);
            }
            $j++;
        }
        $i++;
    }
}

if ($this->GetConfigValue('debug')=='yes') {
    $template_header .= "<div>DEPRECIATED HEADER USED FOR BACKUP</div>\n";
}

echo $template_header;
