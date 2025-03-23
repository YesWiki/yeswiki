<?php



$url = $this->Href();
$page_name = $this->GetPageTag();

$platforms = $this->config['share_platforms'] ?? [ 'facebook', 'X', 'delicio', 'email'];

$html = $this->render('@templates/share.twig', [
    'url' => $url,
    'page_name' => $page_name,
    'platforms' => $platforms,
]);

if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    echo mb_convert_encoding('<div class="page">' . "\n" . $html . "\n" . '<div>', 'UTF-8', 'ISO-8859-1');
} else {
    echo $this->Header();
    echo "<div class=\"page\">\n<h2>" . _t('TEMPLATE_SEE_SHARING_OPTIONS') . ' ' . $this->GetPageTag() . "</h2>\n".utf8_decode($html). "\n<hr class=\"hr_clear\" />\n</div>\n";
    echo $this->Footer();
}
