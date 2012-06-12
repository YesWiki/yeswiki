<?php
if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}

$yeswiki_javascripts = "\n".
'	<!-- javascripts -->'."\n";

$yeswiki_javascripts .= '	<script src="//ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js"></script>'."\n".
'	<script>window.jQuery || document.write(\'<script src="tools/templates/libs/jquery-1.7.2.min.js"><\/script>\')</script>'."\n";

// polyfills pour IE, chargés en premier
$yeswiki_javascripts .= '	<script type="text/javascript">
		// responsive media queries
		if ( ! Modernizr.mq(\'(min-width)\') ) {
		  document.write(\'<script src="tools/templates/libs/respond.min.js"><\/script>\');
		}

		// placeholder
		$(function() {
		    // check placeholder browser support
		    if (!Modernizr.input.placeholder)
		    {
		 
		        // set placeholder values
		        $(this).find(\'[placeholder]\').each(function()
		        {
		            if ($(this).val() == \'\') // if field is empty
		            {
		                $(this).val( $(this).attr(\'placeholder\') ).addClass(\'placeholder\');
		            }
		        });
		 
		        // focus and blur of placeholders
		        $(\'[placeholder]\').focus(function()
		        {
		            if ($(this).val() == $(this).attr(\'placeholder\'))
		            {
		                $(this).val(\'\');
		                $(this).removeClass(\'placeholder\');
		            }
		        }).blur(function()
		        {
		            if ($(this).val() == \'\' || $(this).val() == $(this).attr(\'placeholder\'))
		            {
		                $(this).val($(this).attr(\'placeholder\'));
		                $(this).addClass(\'placeholder\');
		            }
		        });
		 
		        // remove placeholders on submit
		        $(\'[placeholder]\').closest(\'form\').submit(function()
		        {
		            $(this).find(\'[placeholder]\').each(function()
		            {
		                if ($(this).val() == $(this).attr(\'placeholder\'))
		                {
		                    $(this).val(\'\');
		                }
		            })
		        });
		 
		    }
		});
	</script>'."\n";

// javascripts de base, nécessaires au bon fonctionnement de YesWiki
$yeswiki_javascripts .= '	<script src="tools/templates/libs/bootstrap.min.js"></script>'."\n".
'	<script src="tools/templates/libs/jquery.tools.min.js"></script>'."\n".
'	<script src="tools/templates/libs/yeswiki-base.js"></script>'."\n";

// on récupère le bon chemin pour le theme
if (is_dir('themes/'.$this->config['favorite_theme'].'/javascripts')) {
	$repertoire = 'themes/'.$this->config['favorite_theme'].'/javascripts';
} else {
	$repertoire = 'tools/templates/themes/'.$this->config['favorite_theme'].'/javascripts';
}

// on ajoute les javascripts du theme
$dir = (is_dir($repertoire) ? opendir($repertoire) : false);
while ($dir && ($file = readdir($dir)) !== false) {
  if (substr($file, -3, 3)=='.js') $scripts[] = '	<script src="'.$repertoire.'/'.$file.'"></script>'."\n";
}
closedir($dir);

// on trie les javascripts par ordre alphabéthique
if (isset($scripts) && is_array($scripts)) {
	asort($scripts);
	foreach ($scripts as $key => $val) {
	    $yeswiki_javascripts .= $val;
	}
}

// si quelque chose est passée dans la variable globale pour le javascript, on l'intègre
$yeswiki_javascripts .= isset($GLOBALS['js']) ? $GLOBALS['js'] : '';

// on vide la variable globale pour le javascript
$GLOBALS['js'] = '';


// on affiche
echo $yeswiki_javascripts;
?>
