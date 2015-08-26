<?php
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

//on inclue Magpie le parser RSS
if (!defined('MAGPIE_OUTPUT_ENCODING')) {
    define('MAGPIE_OUTPUT_ENCODING', TEMPLATES_DEFAULT_CHARSET);
}
if (!defined('MAGPIE_DIR')) {
    define('MAGPIE_DIR', 'tools/syndication/libs/');
}
require_once (MAGPIE_DIR . 'rss_fetch.inc');

//pour cacher les erreurs Warning de Magpie
error_reporting(0);

//function pour troquer une chaine sans casser les balises
if (!function_exists('truncate')) {
    
    /**
     * Truncates text.
     *
     * Cuts a string to the length of $length and replaces the last characters
     * with the ending if the text is longer than length.
     *
     * @param string  $text String to truncate.
     * @param integer $length Length of returned string, including ellipsis.
     * @param string  $ending Ending to be appended to the trimmed string.
     * @param boolean $exact If false, $text will not be cut mid-word
     * @param boolean $considerHtml If true, HTML tags would be handled correctly
     * @return string Trimmed string.
     */
    function truncate($text, $length = 100, $ending = ' [..]', $exact = false, $considerHtml = true)
    {
        if ($considerHtml) {
            
            // if the plain text is shorter than the maximum length, return the whole text
            if (strlen(preg_replace('/<.*?>/', '', $text)) <= $length) {
                return $text;
            }
            
            // splits all html-tags to scanable lines
            preg_match_all('/(<.+?>)?([^<>]*)/s', $text, $lines, PREG_SET_ORDER);
            $total_length = strlen($ending);
            $open_tags = array();
            $truncate = '';
            foreach ($lines as $line_matchings) {
                
                // if there is any html-tag in this line, handle it and add it (uncounted) to the output
                if (!empty($line_matchings[1])) {
                    
                    // if it's an "empty element" with or without xhtml-conform closing slash (f.e. <br/>)
                    if (preg_match(
                        '/^<(\s*.+?\/\s*|\s*(img|br|input|hr|area|base|basefont|col|frame|isindex|link|meta|param)'
                        .'(\s.+?)?)>$/is',
                        $line_matchings[1]
                    )) {
                        
                        // do nothing
                        // if tag is a closing tag (f.e. </b>)
                        
                    } elseif (preg_match('/^<\s*\/([^\s]+?)\s*>$/s', $line_matchings[1], $tag_matchings)) {
                        
                        // delete tag from $open_tags list
                        $pos = array_search($tag_matchings[1], $open_tags);
                        if ($pos !== false) {
                            unset($open_tags[$pos]);
                        }
                        
                        // if tag is an opening tag (f.e. <b>)
                        
                    } elseif (preg_match('/^<\s*([^\s>!]+).*?>$/s', $line_matchings[1], $tag_matchings)) {
                        
                        // add tag to the beginning of $open_tags list
                        array_unshift($open_tags, strtolower($tag_matchings[1]));
                    }
                    
                    // add html-tag to $truncate'd text
                    $truncate.= $line_matchings[1];
                }
                
                // calculate the length of the plain text part of the line; handle entities as one character
                $content_length = strlen(
                    preg_replace(
                        '/&[0-9a-z]{2,8};|&#[0-9]{1,7};|&#x[0-9a-f]{1,6};/i',
                        ' ',
                        $line_matchings[2]
                    )
                );
                if ($total_length + $content_length > $length) {
                    
                    // the number of characters which are left
                    $left = $length - $total_length;
                    $entities_length = 0;
                    
                    // search for html entities
                    if (preg_match_all(
                        '/&[0-9a-z]{2,8};|&#[0-9]{1,7};|&#x[0-9a-f]{1,6};/i',
                        $line_matchings[2],
                        $entities,
                        PREG_OFFSET_CAPTURE
                    )) {
                        
                        // calculate the real length of all entities in the legal range
                        foreach ($entities[0] as $entity) {
                            if ($entity[1] + 1 - $entities_length <= $left) {
                                $left--;
                                $entities_length+= strlen($entity[0]);
                            } else {
                                // no more characters left
                                break;
                            }
                        }
                    }
                    $truncate.= substr($line_matchings[2], 0, $left + $entities_length);
                    
                    // maximum lenght is reached, so get off the loop
                    break;
                } else {
                    $truncate.= $line_matchings[2];
                    $total_length+= $content_length;
                }
                
                // if the maximum length is reached, get off the loop
                if ($total_length >= $length) {
                    break;
                }
            }
        } else {
            if (strlen($text) <= $length) {
                return $text;
            } else {
                $truncate = substr($text, 0, $length - strlen($ending));
            }
        }
        
        // if the words shouldn't be cut in the middle...
        if (!$exact) {
            
            // ...search the last occurance of a space...
            $spacepos = strrpos($truncate, ' ');
            if (isset($spacepos)) {
                
                // ...and cut the text in this position
                $truncate = substr($truncate, 0, $spacepos);
            }
        }
        
        // add the defined ending to the text
        $truncate.= $ending;
        if ($considerHtml) {
            
            // close all unclosed html-tags
            foreach ($open_tags as $tag) {
                $truncate.= '</' . $tag . '>';
            }
        }
        return $truncate;
    }
}

// on verifie si il existe un dossier pour le cache et si on a les droits d'ecriture dessus
if (file_exists('cache')) {
    if (!is_writable('cache')) {
        echo '<p class="alert alert-error alert-danger">' . _t('SYNDICATION_ACTION_SYNDICATION') . ' : '
             ._t('SYNDICATION_WRITE_ACCESS_TO_CACHE_FOLDER') . '.</p>' . "\n";
    }
} else {
    echo '<p class="alert alert-error alert-danger">' . _t('SYNDICATION_ACTION_SYNDICATION') . ' : '
         ._t('SYNDICATION_CREATE_CACHE_FOLDER') . '.</p>' . "\n";
}

// recuperation des parametres
$titre = $this->GetParameter("titre");

$nb = $this->GetParameter("nb");

$pagination = $this->GetParameter("pagination");
if (empty($pagination)) {
    $pagination = 5;
}

$nbchar = $this->GetParameter("nbchar");

$class = $this->GetParameter("class");

$nouvellefenetre = $this->GetParameter("nouvellefenetre");

$formatdate = $this->GetParameter("formatdate");

$template = $this->GetParameter("template");
if (empty($template)) {
    $template = 'tools/syndication/templates/liste.tpl.html';
} else {
    $template = 'tools/syndication/templates/' . $this->GetParameter("template");
    if (!file_exists($template)) {
        echo '<p class="alert alert-error alert-danger">' . _t('SYNDICATION_ACTION_SYNDICATION') . ' : '
             . $template . ' ' . _t('SYNDICATION_TEMPLATE_NOT_FOUND') . '.</p>' . "\n";
        $template = 'tools/syndication/templates/liste.tpl.html';
    }
}

//recuperation du parametre obligatoire des urls
$urls = $this->GetParameter("url");
if (!empty($urls)) {
    $tab_url = array_map('trim', explode(',', $urls));
    foreach ($tab_url as $cle => $url) {
        if ($url != '') {
            
            // On parse l'url avec magpierss
            $feed = fetch_rss($url);
            if ($feed) {
                
                // Gestion du nombre de pages syndiquees
                $i = 0;
                $nb_item = count($feed->items);
                foreach ($feed->items as $item) {
                    if ($nb != 0 && $nb_item >= $nb && $i >= $nb) {
                        break;
                    }
                    $i++;
                    $aso_page = array();
                    
                    // Gestion du titre
                    if ($titre == 'rss') {
                        $aso_page['titre_site'] = $feed->channel['title'];
                    } else {
                        $aso_page['titre_site'] = $titre;
                    }
                    
                    // Gestion de l'url du site
                    $aso_page['url_site'] = $feed->channel['link'];
                    
                    // Ouverture du lien dans une nouvelle fenetre
                    $aso_page['ext'] = $nouvellefenetre;
                    
                    //url de l'article
                    $aso_page['url'] = $item['link'];
                    
                    //titre de l'article
                    $aso_page['titre'] = $item['title'];
                    
                    //description de la description : soit tronquee, soit en entier
                    if (!empty($nbchar)) {
                        
                        //On verifie si le texte est plus grand que le nombre de caracteres specifies
                        if (strlen($item['description']) > 0 && strlen($item['description']) > $nbchar) {
                            
                            //on decoupe avec une bibliotheque qui respecte le DOM html
                            $item['description'] = truncate(
                                $item['description'],
                                $nbchar,
                                ' [...] <a class="lien_lire_suite" href="' . $aso_page['url']
                                .'" '. ($nouvellefenetre ? 'onclick="window.open(this.href); return false;" ' : '')
                                .'title="' . _t('SYNDICATION_READ_MORE') . '">' . _t('SYNDICATION_READ_MORE') . '</a>'
                            );
                        }
                        $aso_page['description'] = $item['description'];
                    } else {
                        $aso_page['description'] = $item['description'];
                    }
                    
                    //gestion de la date de publication, selon le flux, elle se trouve parsee ? des endroits differents
                    if ($item['pubdate']) {
                        $aso_page['datestamp'] = strtotime($item['pubdate']);
                    } elseif ($item['dc']['date']) {
                        
                        //en php5 on peut convertir les formats de dates exotiques plus facilement
                        if (PHP_VERSION >= 5) {
                            $aso_page['datestamp'] = strtotime($item['dc']['date']);
                        } else {
                            $aso_page['datestamp'] = parse_w3cdtf($item['dc']['date']);
                        }
                    } elseif ($item['issued']) {
                        
                        //en php5 on peut convertir les formats de dates exotiques plus facilement
                        if (PHP_VERSION >= 5) {
                            $aso_page['datestamp'] = strtotime($item['issued']);
                        } else {
                            $aso_page['datestamp'] = parse_w3cdtf($item['issued']);
                        }
                    } else {
                        $aso_page['datestamp'] = time();
                    }
                    if ($formatdate != '') {
                        switch ($formatdate) {
                            case 'jm':
                                $aso_page['date'] = strftime('%d.%m', $aso_page['datestamp']);
                                break;
                            case 'jma':
                                $aso_page['date'] = strftime('%d.%m.%Y', $aso_page['datestamp']);
                                break;
                            case 'jmh':
                                $aso_page['date'] = strftime('%d.%m %H:%M', $aso_page['datestamp']);
                                break;
                            case 'jmah':
                                $aso_page['date'] = strftime('%d.%m.%Y %H:%M', $aso_page['datestamp']);
                                break;
                            default:
                                $aso_page['date'] = '';
                        }
                    }
                    $syndication['pagination'] = $pagination;
                    $syndication['pages'][$aso_page['datestamp']] = $aso_page;
                }
            } else {
                echo '<p class="alert alert-danger">' . _t('ERROR') . ' ' . magpie_error() . '</p>' . "\n";
            }
        }
    }
    
    // Trie des pages par date
    krsort($syndication['pages']);
    echo '<div class="feed_syndication' . ($class ? ' ' . $class : '') . '">' . "\n";
    
    // Gestion des squelettes
    include ($template);
    echo '</div>' . "\n";
} else {
    echo '<div class="alert alert-danger"><strong>' . _t('SYNDICATION_ACTION_SYNDICATION') . '</strong> : '
         ._t('SYNDICATION_PARAM_URL_REQUIRED') . '.</div>' . "\n";
}

//ajout du javascript
$this->AddJavascriptFile('tools/syndication/presentation/javascripts/syndication.js');
