<?php
$GLOBALS['inIframe'] = true;
ob_start();
echo $this->Run($this->getPageTag(), 'edit');
$buffer = ob_get_contents();
ob_end_clean();
$output = '';
// on recupere les entetes html mais pas ce qu'il y a dans le body
$header = explode('<body', $this->Header());
$output .= $header[0] . '<body class="yeswiki-body">'."\n".'<div class="yeswiki-page-widget page-widget page">'."\n";

// on replace la méthode d'edition classique pour mettre celle de l'édition en iframe
$buffer = str_replace(
    'action="'.$this->href('edit', $this->getPageTag()).'"',
    'action="'.$this->href('editiframe', $this->getPageTag()).'"',
    $buffer
);
$buffer = str_replace(
    'onclick="location.href=\''.$this->href('', $this->getPageTag()),
    'onclick="location.href=\''.$this->href('iframe', $this->getPageTag()),
    $buffer
);
$output .= str_replace(
    'value="'.$this->getPageTag().'/edit"',
    'value="'.$this->getPageTag().'/editiframe"',
    $buffer
);

$output .= '</div><!-- end div.page-widget -->'."\n";

// on efface le style par defaut du fond pour l'iframe
$styleiframe = '<style>
html {
overflow-y: auto;
background-color : transparent;
background-image : none;
}
.yeswiki-body {
background-color : transparent;
background-image : none;
text-align : left;
width : auto;
min-width : 0;
padding-top : 0;
}
.yeswiki-page-widget { min-height:auto !important; }
</style>' . "\n";

$this->addJavascriptFile('tools/templates/libs/vendor/iframeResizer.contentWindow.min.js');
// on recupere juste les javascripts et la fin des balises body et html
$output .= preg_replace('/^.+<script/Us', $styleiframe . '<script', $this->Footer());
echo $output;
