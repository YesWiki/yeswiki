<?php
if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}

echo '<div class="text-center">(>^_^)> '._t('RUNNING_WITH').' <a data-toggle="tooltip" data-placement="top" title="'.$this->config["yeswiki_version"].' '.$this->config["yeswiki_release"].'" href="http://www.yeswiki.net">YesWiki</a> <(^_^<)</div>'."\n";