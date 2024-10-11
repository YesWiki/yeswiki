<?php

// Vérification de sécurité
if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}

ob_start();
?>
<div class="page">
<?php
// Valeur par défaut du paramétre "global"
$global = !empty($_REQUEST['global']);
// Si le paramétre "global" a été spécifié
if ($global) {
    $title = str_replace(
        ['{beginLink}', '{endLink}'],
        ["<a href=\"{$this->href('referrers', '', 'global=1')}\">", '</a>'],
        _t('LINK_TO_REFERRERS_DOMAINS')
    );
    $referrers = $this->LoadReferrers();
} else {
    $since = $this->GetConfigValue('referrers_purge_time')
        ? ' (' . str_replace(
            '{time}',
            $this->GetConfigValue('referrers_purge_time') == 1
                ? _t('REFERRERS_SITES_24_HOURS')
                : str_replace('{nb}', $this->GetConfigValue('referrers_purge_time'), _t('REFERRERS_SITES_X_DAYS')),
            _t('REFERRERS_SITES_SINCE')
        ) . ')'
        : '';
    $title = str_replace(
        ['{tag}', '{since}', '{beginLink}', '{endLink}'],
        [$this->Link($this->GetPageTag()), $since, "<a href=\"{$this->href('referrers')}\">", '</a>'],
        _t('LINK_TO_REFERRERS_SITES_NO_GLOBAL')
    );
    $referrers = $this->LoadReferrers($this->GetPageTag());
}

echo "<b>$title</b><br /><br />\n";
if ($referrers) {
    for ($a = 0; $a < count($referrers); $a++) {
        $temp_parse_url = parse_url($referrers[$a]['referrer']);
        $temp_parse_url = (!empty($temp_parse_url['host'])) ? strtolower(preg_replace("/^www\./Ui", '', $temp_parse_url['host'])) : 'inconnu';

        if (isset($referrer_sites["$temp_parse_url"])) {
            $referrer_sites["$temp_parse_url"] += $referrers[$a]['num'];
        } else {
            $referrer_sites["$temp_parse_url"] = $referrers[$a]['num'];
        }
    }

    array_multisort($referrer_sites, SORT_DESC, SORT_NUMERIC);
    reset($referrer_sites);

    echo "<table border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
    foreach ($referrer_sites as $site => $site_count) {
        echo '<tr>';
        echo '<td width="30" align="right" valign="top" style="padding-right: 10px">' , $site_count, '</td>';
        echo '<td valign="top">' , (($site != 'unknown') ? "<a href=\"http://$site\">$site</a>" : $site) , '</td>';
        echo "</tr>\n";
    }
    echo "</table>\n";
} else {
    echo "<i>None</i><br />\n";
}

if ($global) {
    echo '<br />[<a href="',$this->href('referrers_sites'),'">' .
        str_replace('{tag}', $this->GetPageTag(), _t('LINK_TO_REFERRERS_SITES_ONLY_TAG')) .
        '</a> | <a href="',$this->href('referrers'),'">' .
        str_replace('{tag}', $this->GetPageTag(), _t('LINK_TO_REFERRERS_SITES_PAGES_ONLY_TAG')) . '</a>]';
} else {
    echo '<br />[<a href="',$this->href('referrers_sites', '', 'global=1'),'">' . _t('LINK_TO_REFERRERS_ALL_DOMAINS') .
    '</a> | <a href="',$this->href('referrers', '', 'global=1'),'">' . _t('LINK_TO_REFERRERS_ALL_REFS') . '</a>]';
}

?>
</div>
<?php

$content = ob_get_clean();
echo $this->Header();
echo $content;
echo $this->Footer();

?>