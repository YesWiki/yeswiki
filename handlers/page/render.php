<?php

$output = '';
// on recupere les entetes html mais pas ce qu'il y a dans le body
$header = explode('<body', $this->Header());
$output .= $header[0] . '<body class="yeswiki-iframe-body">'."\n"
    .'<div class="container">'."\n"
    .'<div class="yeswiki-page-widget page-widget page" '.$this->Format('{{doubleclic iframe="1"}}').'>'."\n";

$this->page['body'] = $_GET['content']; // fake Page for actions and handlers

$output .= $this->Format($this->page['body']);
$output .= '</div><!-- end .page-widget -->'."\n";
// on recupere juste les javascripts et la fin des balises body et html
$output .= preg_replace('/^.+<script/Us', '<script', $this->Footer());
echo $output;
