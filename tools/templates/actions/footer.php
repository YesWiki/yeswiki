<?php
if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}

$chemin_theme = 'themes/'.$this->config['favorite_theme'].'/squelettes/'.$this->config['favorite_squelette'];
if (file_exists($chemin_theme)) {
	$file_content = file_get_contents($chemin_theme);
} else {
	$file_content = file_get_contents('tools/templates/'.$chemin_theme);
}
//On recupere la partie bas du template et on execute les actions wikini
$template_decoupe = explode("{WIKINI_PAGE}", $file_content);
$template_footer = $template_decoupe[1];

if ($act=preg_match_all ("/".'(\\{\\{)'.'(.*?)'.'(\\}\\})'."/is", $template_footer, $matches)) {
	$i = 0; $j = 0;
	foreach($matches as $valeur) {
		foreach($valeur as $val) {
			if (isset($matches[2][$j]) && $matches[2][$j]!='') {
				$action= $matches[2][$j];
				$template_footer=str_replace('{{'.$action.'}}', $this->Format('{{'.$action.'}}'), $template_footer);
			}
			$j++;
		}
		$i++;
	}
}

//si quelque chose est passée dans la variable globale pour le javascript, on l'intègre
echo ((isset($GLOBALS['js'])) ? str_replace('</body>', $GLOBALS['js'].'</body>', $template_footer) : $template_footer);

//on vide la variable globale pour le javascript
$GLOBALS['js'] = '';

?>