<?php

/*
tagrss.php

Copyright 2011  Florian SCHMITT <florian@outils-reseaux.org>
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

// Vérification de sécurité
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}
$oldpagetag = $this->GetPageTag();
$oldpage = $this->LoadPage($oldpagetag);
$tags = trim((isset($_GET['tags'])) ? $_GET['tags'] : '');
$type = (isset($_GET['type'])) ? $_GET['type'] : '';
$req = '';
$req_from = '';
$req_group = '';
$textetitre = _t('LATEST_CHANGES_ON') . ' ' . $this->config["wakka_name"];

//on fait les tableaux pour les tags, puis on met des virgules et des guillemets
if (!empty($tags)) {
    
    //texte utilisé pour la description du flux RSS
    $textetitre.= ', contenant les tags ' . $tags;
    
    $results = $this->PageList($tags, $type, 20, 'date');
    if ($results) {
        header('Content-type: text/xml; charset=UTF-8');
        $output = '<?xml version="1.0" encoding="UTF-8"?>';
        if (!($link = $this->GetParameter("link"))) {
            $link = $this->config["root_page"];
        }
        $output.= "<rss version=\"2.0\" xmlns:dc=\"http://purl.org/dc/elements/1.1/\"".
                  " xmlns:atom=\"http://www.w3.org/2005/Atom\">\n";
        $output.= "<channel>\n<title>";
        if (empty($titrerss)) {
            $output .= $textetitre;
        } else {
            $output .= $titrerss;
        }
        $output .= "</title>\n";
        $output .= "<link>" . $this->config["base_url"] . $link . "</link>\n";
        $output .= "<description>" . $textetitre . "</description>\n";
        $output .= "<atom:link href=\"" . $this->Href('xml') . "\" rel=\"self\" type=\"application/rss+xml\" />\n";
        $items = '';
        foreach ($results as $page) {
            $this->tag = $page['tag'];
            $this->page = $page;
            $items.= "<item>\r\n";
            $items.= "<title>" . $page['tag'] . "</title>\r\n";
            $items.= "<link>" . $this->config["base_url"] . $page["tag"] . "</link>\r\n";
            $items.= "<description><![CDATA[";
            
            //on enleve les actions recentchangesrssplus pour eviter les boucles infinies
            $page["body"] = preg_replace("/\{\{recentchangesrss(.*?)\}\}/s", '', $page["body"]);
            $page["body"] = preg_replace("/\{\{rss(.*?)\}\}/s", '', $page["body"]);
            if (strstr($page["body"], "bf_titre")) {
                $tab_valeurs = json_decode($page["body"], true);
                $tab_valeurs = _convert($tab_valeurs, 'UTF-8');
                $page["body"] = '""' . baz_voir_fiche(0, $tab_valeurs) . '""';
            } else {
                $page["body"] = _convert($page["body"], 'ISO-8859-1');
            }
            $texteformat = htmlspecialchars($this->Format($page['body']), ENT_COMPAT, YW_CHARSET);
            
            $items.= $texteformat . "]]></description>\r\n";
            $items.= "<dc:creator>by ".htmlspecialchars($page["user"], ENT_COMPAT, YW_CHARSET).
                     "</dc:creator>\r\n";
            $items.= "<pubDate>" . gmdate('D, d M Y H:i:s \G\M\T', strtotime($page['time'])) . "</pubDate>\r\n";
            $itemurl = $this->href(false, $page["tag"]);
            $items.= '<guid>' . $itemurl . "</guid>\n";
            $items.= "</item>\r\n";
        }
        $this->tag = $oldpagetag;
        $this->page = $oldpage;
        $oldpage = $this->LoadPage($oldpagetag);
        $output.= $items;
        $output.= "</channel>\n";
        $output.= "</rss>\n";
        echo $output;
    }
}
