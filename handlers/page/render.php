<?php

$output = '<body class="yeswiki-render">' . "\n"
    . '<div class="container">' . "\n"
    . '<div class="yeswiki-page-widget page-widget page" ' . $this->Format('{{doubleclic iframe="1"}}') . '>' . "\n";

$this->page['body'] = strip_tags($_GET['content']); // fake Page for actions and handlers, all html is striped

$output .= $this->Format($this->page['body']);
$output .= '</div><!-- end .page-widget -->' . "\n";
// ajout des en-têtes en pieds de page

// on recupere les entetes html mais pas ce qu'il y a dans le body
$header = explode('<body', $this->Header());
$output = $header[0] . $output;
// on recupere juste les javascripts et la fin des balises body et html
$output .= preg_replace('/^.+<script/Us', '<script', $this->Footer());
echo $output;
