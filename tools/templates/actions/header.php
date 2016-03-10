<?php
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

//=======Restes de wikini=================================================================================
$user = $this->GetUser();

$chemin_theme = 'themes/'.$this->config['favorite_theme'].'/squelettes/'.$this->config['favorite_squelette'];
if (file_exists($chemin_theme)) {
    $file_content = file_get_contents($chemin_theme);
} else {
    $file_content = file_get_contents('tools/templates/'.$chemin_theme);
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

//On ajoute la derniere version de modernizer
$template_header = preg_replace('/<\/head>/', '  <!-- HTML5 and CSS3 availability detection -->'."\n".
'  <script src="tools/templates/libs/vendor/modernizr-2.6.2.min.js"></script>'."\n".
'</head>', $template_header);

echo $template_header;
