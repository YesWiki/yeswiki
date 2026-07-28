<?php

$title = htmlspecialchars($this->services->get(YesWiki\Render\Service\TemplateHelperService::class)->getTitleFromBody($this->page), ENT_COMPAT | ENT_HTML5);
if ($title) {
    echo $title;
} else {
    echo $this->GetPageTag();
}
