<?php

$output = '<body class="yeswiki-render">' . "\n"
    . '<div class="container">' . "\n"
    . '<div class="yeswiki-page-widget page-widget page" ' . $this->Format('{{doubleclic iframe="1"}}') . '>' . "\n";

$rawContent = is_string($_GET['content'] ?? null) ? $_GET['content'] : '';
$actionBlocks = [];
$protectedContent = preg_replace_callback(
    '/\{\{\s*\w[^}]*\}\}/s',
    function ($matches) use (&$actionBlocks) {
        $actionBlocks[] = $matches[0];

        return "\x02" . (count($actionBlocks) - 1) . "\x03";
    },
    $rawContent
);
$protectedContent = strip_tags($protectedContent);
$this->page['body'] = preg_replace_callback(
    '/\x02(\d+)\x03/',
    fn ($matches) => $actionBlocks[(int)$matches[1]] ?? '',
    $protectedContent
);

$output .= $this->Format($this->page['body']);
$output .= '</div><!-- end .page-widget -->' . "\n";

$header = explode('<body', $this->Header());
$output = $header[0] . $output;
$output .= preg_replace('/^.+<script/Us', '<script', $this->Footer());
echo $output;
