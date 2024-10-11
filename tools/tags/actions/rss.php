<?php

$tags = $this->GetParameter('tags');
$class = $this->GetParameter('class');
if (empty($class)) {
    $class = '';
}

if ($this->GetMethod() != 'rss' && $this->GetMethod() != 'xml' && $this->GetMethod() != 'tagrss') { //on affiche un lien dans la page si on n'est pas en xml
    echo '<a class="' . $class . ' rss-icon" href="' . $this->Href('tagrss', $this->GetPageTag(), 'tags=' . $tags) . '" title="' . _t('TAGS_RSS_FEED_FOR_NEW_PAGES_WITH_TAGS') . ' : ' . $tags . '">
		</a>' . "\n";

    return;
}
