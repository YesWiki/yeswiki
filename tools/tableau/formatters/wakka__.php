<?php

if (!defined('WIKINI_VERSION')) {
    die('acc&egrave;s direct interdit');
}

if (!function_exists('wakka2callbacktableaux')) {
    function parsetable($thing)
    {
        $tableclass = '';

        // recuperation des attributs
        preg_match("/^\[\|(.*)$/m", $thing, $match);
        if ($match[1]) {
            $tableclass = $match[1];
        }
        $table = "<table class=\"table".(!empty($tableclass) ? ' '.$tableclass: ' table-striped table-bordered')."\" $tableclass >\n";
        // suppression de [|xxxx et de |]
        $thing = preg_replace("/^\[\|(.*)$/m", '', $thing);
        $thing = trim(preg_replace("/\|\]/m", '', $thing));

        // recuperation de chaque portion commencant par | et finissant par |
        preg_match_all('/(^\|.*\|$)/Ums', $thing, $rows);

        //analyse de chaque ligne
        foreach ($rows[0] as $row) {
            $table .= parsetablerow($row);
        }
        $table .= '</table>';

        return $table;
    }
    // parse la definition d'une ligne
    function parsetablerow($row)
    {
        $result = '';
        $rowattr = '';

        $row = trim($row);

        // detection des attributs de ligne => si la ligne ne commence pas par | alors attribut
        if (!preg_match("/^\|/", $row, $match)) {
            preg_match("/^!([^\|]*)!\|/", $row, $match);
            $rowattr = $match[1];
            $row = trim(preg_replace("/^!([^\|]*)!/", '', $row));
        }
        $result .= "   <tr $rowattr>\n";
        $row = trim(preg_replace("/^\|/", '', trim($row)));
        $row = trim(preg_replace("/\|$/", '', trim($row)));

        //recuperation de chaque cellule
        $cells = explode('|', $row);    //nb : seule les indices impaire sont significatif
        $i = 0;
        foreach ($cells as $cell) {
            $result .= parsetablecell($cell);
            ++$i;
        }
        $result .= "   </tr>\n";

        return $result;
    }
    //parse la definition d'une cellule
    function parsetablecell($cell)
    {
        global $wiki;
        $cellattr = '';

        if (preg_match('/^!(.*)!/', $cell, $match)) {
            $cellattr = $match[1];
        }
        $cell = preg_replace('/^!(.*)!/', '', $cell);
        //si espace au debut => align=right
        //si espace a la fin => align=left
        //si espace debut et fin => align=center
        if (preg_match("/^\s(.*)/", $cell)) {
            $align = 'right';
        }
        if (preg_match("/^(.*)\s$/", $cell)) {
            $align = 'left';
        }
        if (preg_match("/^\s(.*)\s$/", $cell)) {
            $align = 'center';
        }
        if (isset($align) && $align) {
            $cellattr .= " align=\"$align\"";
        }

        return "      <td $cellattr>".$cell."</td>\n";
    }

    function wakka2callbacktableaux($things)
    {
        $thing = $things[1];

        global $wiki;

        if (preg_match("/^\[\|(.*)\|\]/s", $thing)) {
            $thing = preg_replace("/^\[\|(.*)<br \/>/", '[|$1', $thing);
            $thing = preg_replace("/\|<br \/>/", '|', $thing);
            return parsetable($thing);
        }

        // if we reach this point, it must have been an accident.
        return $thing;
    }
}

$plugin_output_new = preg_replace_callback("/(^\[\|.*?\|\])/ms", 'wakka2callbacktableaux', $plugin_output_new);
