<?php

namespace YesWiki\Tableau;

use YesWiki\Core\YesWikiFormatter;

class WakkaFormatter__ extends YesWikiFormatter
{
    public function formatArguments($args)
    {
        return [];
    }

    public function run()
    {
        if (preg_match("/(\[\|.*?\|\])/msu", $this->output)) {
            $outputSplittedByPre = explode('<pre>', $this->output);
            $outputWithoutPre = $this->output;
            foreach ($outputSplittedByPre as $preContent) {
                $extract = explode('</pre>', $preContent);
                $outputWithoutPre = str_replace("<pre>{$extract[0]}</pre>", '<pre></pre>', $outputWithoutPre);
            }
            if (preg_match_all("/(\[\|.*?\|\])/msu", $outputWithoutPre, $matches)) {
                $newOutput = $this->output;
                foreach ($matches[0] as $key => $value) {
                    $replacement = $this->wakka2callbacktableaux([$value, $matches[1][$key]]);
                    $newOutput = str_replace($matches[1][$key], $replacement, $newOutput);
                }
                $this->output = $newOutput;
            }
        }
    }

    private function parsetable($thing)
    {
        $tableclass = '';

        // recuperation des attributs
        if (preg_match("/^\[\|(.*)$/m", $thing, $match)) {
            $tableclass = $match[1];
        }
        $this->wiki->addJavascriptFile('javascripts/vendor/datatables-full/jquery.dataTables.min.js');
        $this->wiki->addCSSFile('styles/vendor/datatables-full/dataTables.bootstrap.min.css');
        // suppression de [|xxxx et de |]
        $thing = preg_replace("/^\[\|(.*)$/m", '', $thing);
        $thing = trim(preg_replace("/\|\]/m", '', $thing));

        // recuperation de chaque portion commencant par | et finissant par |
        preg_match_all('/(^(?:!([^\|]*)!)?\|.*\|$)/Ums', $thing, $rows);

        //analyse de chaque ligne
        $tablecontent = '';
        foreach ($rows[0] as $row) {
            $tablecontent .= $this->parsetablerow($row);
        }
        $table = '<table class="yeswiki-table prevent-auto-init ' . (!empty($tableclass) ? ' ' . $tableclass : 'table table-condensed table-striped') . '">' . "\n";
        $table .= $tablecontent . "\n";
        $table .= '</table>' . "\n";

        return $table;
    }

    // parse la definition d'une ligne
    private function parsetablerow($row)
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
        $row = preg_replace("/^\|/", '', trim($row));
        $row = preg_replace("/\|$/", '', $row);

        //recuperation de chaque cellule
        $cells = explode('|', $row);    //nb : seule les indices impaire sont significatif
        $i = 0;
        foreach ($cells as $cell) {
            $result .= $this->parsetablecell($cell);
            $i++;
        }
        $result .= "   </tr>\n";

        return $result;
    }

    //parse la definition d'une cellule
    private function parsetablecell($cell)
    {
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

        return "      <td $cellattr>" . $cell . "</td>\n";
    }

    private function wakka2callbacktableaux($things)
    {
        $thing = $things[1];

        if (preg_match("/^\[\|(.*)\|\]/s", $thing)) {
            $thing = preg_replace("/^\[\|(.*)<br \/>/", '[|$1', $thing);
            $thing = preg_replace("/\|<br \/>/", '|', $thing);

            return $this->parsetable($thing);
        }

        // if we reach this point, it must have been an accident.
        return $thing;
    }
}
