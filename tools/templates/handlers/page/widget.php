<?php
/*
*/
if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}

$width = (isset($_GET['width'])) ? $_GET['width'] : '100%';
$height = (isset($_GET['height'])) ? $_GET['height'] : 700;

echo $this->Header();
echo "<h2>"._t('TEMPLATE_WIDGET_TITLE')."</h2>";
echo "<pre>\n";
echo htmlentities('<iframe class="yeswiki_frame" width="'.$width.'" height="'.$height.'" frameborder="0" src="'.$this->Href('iframe').'"></iframe>')."\n";
echo '</pre>'."\n";
echo '<div class="alert alert-info">'._t('TEMPLATE_WIDGET_COPY_PASTE').'</div>'."\n";
echo '<iframe class="yeswiki_frame" width="'.$width.'" height="'.$height.'" frameborder="0" src="'.$this->Href('iframe').'"></iframe>';
echo $this->Footer();

?>
