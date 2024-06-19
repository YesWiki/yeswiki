<?php

if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}

$content = $this->getParameter('content');
switch ($content) {
    // todo finish the cases
    case 'title':
        echo 'title';
        break;
    case 'description':
        echo 'description';
        break;
    case 'image':
        echo 'image';
        break;
    default:
        echo $this->GetPageTag();
        break;
}
