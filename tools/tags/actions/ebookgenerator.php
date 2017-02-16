<?php
/*vim: set expandtab tabstop=4 shiftwidth=4: */
// +------------------------------------------------------------------------------------------------------+
// | PHP version 5                                                                                        |
// +------------------------------------------------------------------------------------------------------+
// | Copyright (C) 2012 Florian Schmitt <florian@outils-reseaux.org>                                      |
// +------------------------------------------------------------------------------------------------------+
// | This library is free software; you can redistribute it and/or                                        |
// | modify it under the terms of the GNU Lesser General Public                                           |
// | License as published by the Free Software Foundation; either                                         |
// | version 2.1 of the License, or (at your option) any later version.                                   |
// |                                                                                                      |
// | This library is distributed in the hope that it will be useful,                                      |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of                                       |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU                                    |
// | Lesser General Public License for more details.                                                      |
// |                                                                                                      |
// | You should have received a copy of the GNU Lesser General Public                                     |
// | License along with this library; if not, write to the Free Software                                  |
// | Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA                            |
// +------------------------------------------------------------------------------------------------------+
//
/**
 *
 * Export de toutes les pages en derniere version, pour creer une pageWiki ebook et son pdf
 *
 *
 *@package tags
 *
 *@author        Florian Schmitt <florian@outils-reseaux.org>
 *
 *@copyright     Outils-Reseaux 2012
 *@version       $Revision: 0.1 $ $Date: 2010/03/04 14:19:03 $
 */

if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

include_once 'tools/tags/libs/tags.functions.php';

// ajoute t on des pages pour le debut de l'ebook et la fin
$ebookstart = $this->getParameter('ebookstart');
$ebookend = $this->getParameter('ebookend');

$ebookpagenamestart = $this->getParameter('ebookpagenamestart');
if (empty($ebookpagenamestart)) {
    $ebookpagenamestart = 'Ebook';
}

// ajoute t on les pages installees par defaut dans wiki
$addinstalledpage = $this->getParameter('addinstalledpage');

// couverture de l'ebbok par defaut
$coverimageurl = $this->getParameter('coverimageurl');

// mot cles
$taglist = $this->getParameter('tags');
if (!empty($taglist)) {
    $tabtag = explode(',', $taglist);
    $taglist = '"' . implode('","', $tabtag) . '"';
}

// quels types de pages : fiche bazar, page wiki, ou tout?
$type = $this->getParameter('type');
if ($type != 'bazar' && $type != 'wiki' && $type != 'all') {
    $type = 'all';
}

$output = '';

if (isset($_POST["page"])) {
    if (isset($_POST['antispam']) && $_POST['antispam'] == 1) {
        if (isset($_POST["ebook-title"]) && $_POST["ebook-title"] != '') {
            if (isset($_POST["ebook-description"]) && $_POST["ebook-description"] != '') {
                if (isset($_POST["ebook-author"]) && $_POST["ebook-author"] != '') {
                    if (isset($_POST["ebook-biblio-author"]) && $_POST["ebook-biblio-author"] != '') {
                        if (isset($_POST["ebook-cover-image"]) && $_POST["ebook-biblio-author"] != '') {
                            if (preg_match("/.(jpg)$/i", $_POST["ebook-cover-image"]) == 1) {
                                if (isset($ebookpagename) && !empty($ebookpagename)) {
                                    $pagename = $ebookpagename;
                                } else {
                                    $pagename = generatePageName($ebookpagenamestart . ' ' . $_POST["ebook-title"]);
                                }
                                foreach ($_POST["page"] as $page) {
                                    $output .= '{{include page="' . $page . '" class=""}}' . "\n";
                                }
                                $output .= '//' . _t('TAGS_CONTENT_VISIBLE_ONLINE_FROM_PAGE') . ' : ' . $this->href('', $pagename) . ' // {{button link="' . $this->href('pdf', $pagename) . '" text="' . _t('TAGS_DOWNLOAD_PDF') . '" class="btn-primary pull-right" icon="book"}}' . "\n";
                              
                                unset($_POST['page']);
                                unset($_POST['antispam']);
                                $this->SaveMetaDatas($pagename, $_POST);
                                $this->SavePage($pagename, $output);
                                $output = $this->Format('""<div class="alert alert-success">' . _t('TAGS_EBOOK_PAGE_CREATED') . ' !""' . "\n" . '{{button class="btn-primary" link="' . $pagename . '" text="' . _t('TAGS_GOTO_EBOOK_PAGE') . ' ' . $pagename . '"}}""</div>""' . "\n");
                            } else {
                                $output = '<div class="alert alert-danger">' . _t('TAGS_NOT_IMAGE_FILE') . '</div>' . "\n";
                            }
                        } else {
                            $output = '<div class="alert alert-danger">' . _t('TAGS_NO_IMAGE_FOUND') . '</div>' . "\n";
                        }
                    } else {
                        $output = '<div class="alert alert-danger">' . _t('TAGS_NO_BIBLIO_AUTHOR_FOUND') . '</div>' . "\n";
                    }
                } else {
                    $output = '<div class="alert alert-danger">' . _t('TAGS_NO_AUTHOR_FOUND') . '</div>' . "\n";
                }
            } else {
                $output = '<div class="alert alert-danger">' . _t('TAGS_NO_DESC_FOUND') . '</div>' . "\n";
            }
        } else {
            $output = '<div class="alert alert-danger">' . _t('TAGS_NO_TITLE_FOUND') . '</div>' . "\n";
        }
    } else {
        $output = '<div class="alert alert-danger">' . _t('TAGS_SPAM_RISK') . '</div>' . "\n";
    }
} else {
    // recuperation des pages creees a l'installation
    $d = dir("setup/doc/");
    while ($doc = $d->read()) {
        if (is_dir($doc) || substr($doc, -4) != '.txt') {
            continue;
        }

        if ($doc == '_root_page.txt') {
            $installpagename[$this->GetConfigValue("root_page")] = $this->GetConfigValue("root_page");
        } else {
            $pagename = substr($doc, 0, strpos($doc, '.txt'));
            $installpagename[$pagename] = $pagename;
        }
    }

    // recuperation des pages wikis
    $sql = 'SELECT DISTINCT tag,body FROM ' . $this->GetConfigValue('table_prefix') . 'pages';
    if (!empty($taglist)) {
        $sql .= ', ' . $this->config['table_prefix'] . 'triples tags';
    }
    $sql .= ' WHERE latest="Y"
				AND comment_on="" AND tag NOT LIKE "LogDesActionsAdministratives%" ';

    if ($type == 'wiki') {
        $sql .= ' AND tag NOT IN (SELECT resource FROM ' . $this->GetConfigValue('table_prefix') . 'triples WHERE property="http://outils-reseaux.org/_vocabulary/type") ';
    } elseif ($type == 'bazar') {
        $sql .= ' AND tag IN (SELECT resource FROM ' . $this->GetConfigValue('table_prefix') . 'triples WHERE property="http://outils-reseaux.org/_vocabulary/type" AND value="fiche_bazar")';
    }

    if (!empty($taglist)) {
        $sql .= ' AND tags.value IN (' . $taglist . ') AND tags.property = "http://outils-reseaux.org/_vocabulary/tag" AND tags.resource = tag';
    }

    $sql .= ' ORDER BY tag ASC';

    $pages = $this->LoadAll($sql);

    // on prend tous les tags
    //$sql = 'SELECT DISTINCT value FROM '.$this->config['table_prefix'].'triples WHERE property="http://outils-reseaux.org/_vocabulary/tag"';
    //$tags = $this->LoadAll($sql);

    if (isset($this->page["metadatas"]["ebook-title"])) {
        $ebookpagename = $this->GetPageTag();
        preg_match_all('/{{include page="(.*)".*}}/Ui', $this->page['body'], $matches);
        $ebookstart = $matches[1][0];
        $last = count($matches[1]) - 1;
        $ebookend = $matches[1][$last];
        unset($matches[1][0]);
        unset($matches[1][$last]);
        foreach ($matches[1] as $key => $value) {
            $pagesfiltre = filter_by_value($pages, 'tag', $value);
            $selectedpages[] = array_shift($pagesfiltre);
            $key = array_keys($pagesfiltre);
            if ($key && isset($pages[$key[0]])) {
                unset($pages[$key[0]]);
            }

        }
    } else {
        $ebookpagename = '';
        $selectedpages = array();
    }

    include_once 'tools/libs/squelettephp.class.php';
    $template_export = new SquelettePhp('tools/tags/presentation/templates/exportpages_table.tpl.html');
    // charge le templates
    $template_export->set(
    	array('pages' => $pages, 'ebookstart' => $ebookstart, 'ebookend' => $ebookend, 'addinstalledpage' => $addinstalledpage, 'installedpages' => $installpagename, 'coverimageurl' => $coverimageurl, 'ebookpagename' => $ebookpagename, 'metadatas' => $this->page["metadatas"], 'selectedpages' => $selectedpages, 'url' => $this->href('', $this->GetPageTag()))
    	); // on passe le tableau de pages en parametres
    $output .= $template_export->analyser(); // affiche les resultats
}

echo $output . "\n";
