<?php

if (!function_exists("rssdiff")) {
    function rssdiff($tag, $idfirst, $idlast)
    {

        require_once 'includes/diff/diff.class.php';
        require_once 'includes/diff/diffformatter.class.php';

        $output='';
        global $wiki;
        // TODO : cache ?

        if ($idfirst == $idlast) {
            $previousdiff = $wiki->LoadSingle(
                "select id from "
                . $wiki->config["table_prefix"]
                . "pages where tag = '"
                . mysqli_real_escape_string($wiki->dblink, $tag)
                . "' and id < $idfirst order by time desc limit 1"
            );
            if ($previousdiff) {
                $idlast = $previousdiff['id'];
            } else {
                return;
            }

        }

        $pageA = $wiki->LoadPageById($idfirst);
        $pageB = $wiki->LoadPageById($idlast);


        $bodyA = explode("\n", $pageA["body"]);
        $bodyB = explode("\n", $pageB["body"]);

        $added = array_diff($bodyA, $bodyB);
        $deleted = array_diff($bodyB, $bodyA);

        if (!isset($output)) {
             $output = '';
        }

        $output .= "<br />\n";
        $output .= "<br />\n";
        $output .= "<b>Comparaison de <a href=\""
            . $wiki->href("", "", "time="
            . urlencode($pageA["time"]))
            . "\">".$pageA["time"]
            . "</a> &agrave; <a href=\""
            . $wiki->href("", "", "time=".urlencode($pageB["time"]))
            . "\">"
            . $pageB["time"]
            . "</a></b><br />\n";

        $wiki->RegisterInclusion($tag);
        if ($added) {
            // remove blank lines
            $output .= "<br />\n<b>Ajouts:</b><br />\n";
            $output .= "<div class=\"additions\">".(implode("\n", $added))."</div>";
        }

        if ($deleted) {
            $output .= "<br />\n<b>Suppressions:</b><br />\n";
            $output .= "<div class=\"deletions\">".(implode("\n", $deleted))."</div>";
        }

        $wiki->UnregisterLastInclusion();

        if (!$added && !$deleted) {
            $output .= "<br />\nPas de diff&eacute;rences.";
        }
        return $output;
    }
}
