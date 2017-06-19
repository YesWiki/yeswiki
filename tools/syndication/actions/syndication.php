<?php
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

include_once('tools/syndication/libs/syndication.lib.php');
include_once('tools/syndication/libs/simplepie_1.3.1.compiled.php');

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
$titre = $this->GetParameter("title");

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

$tabsrc = '';
//recuperation du parametre obligatoire des urls
$sources = $this->GetParameter('source');
if (!empty($sources)) {
    $tabsrc = array_map('trim', explode(',', $sources));
}

//recuperation du parametre obligatoire des urls
$urls = $this->GetParameter("url");
if (!empty($urls)) {
    $tab_url = array_map('trim', explode(',', $urls));
    $nburl = 0;
    foreach ($tab_url as $cle => $url) {
        if ($url != '') {
            // Parse it
            $feed = new SimplePie();
            $feed->set_feed_url($url);
            $feed->enable_cache(true);
            $feed->init();
            $feed->handle_content_type();
            if ($feed) {
                // Gestion du nombre de pages syndiquees
                $i = 0;

                $nb_item = count($feed->get_items());
                foreach ($feed->get_items() as $item) {
                    if ($nb != 0 && $nb_item >= $nb && $i >= $nb) {
                        break;
                    }
                    $i++;
                    $aso_page = array();

                    // Gestion du titre
                    if (empty($titre)) {
                        $aso_page['titre_site'] = '';
                    }
                    elseif ($titre == 'rss') {
                        $aso_page['titre_site'] = $feed->get_title();
                    } else {
                        $aso_page['titre_site'] = $titre;
                    }

                    // Gestion de l'url du site
                    $aso_page['url_site'] = $feed->get_link();
                    if (is_array($tabsrc)) {
                        $aso_page['source'] = $tabsrc[$nburl];
                    } else {
                        $aso_page['source'] = '';
                    }

                    // Ouverture du lien dans une nouvelle fenetre
                    $aso_page['ext'] = $nouvellefenetre;

                    //url de l'article
                    $aso_page['url'] = $item->get_permalink();

                    //titre de l'article
                    $aso_page['titre'] = $item->get_title();

                    //description de la description : soit tronquee, soit en entier
                    $aso_page['description'] = $item->get_content();
                    if (!empty($nbchar)) {
                        //On verifie si le texte est plus grand que le nombre de caracteres specifies
                        if (strlen($aso_page['description']) > 0 && strlen($aso_page['description']) > $nbchar) {

                            //on decoupe avec une bibliotheque qui respecte le DOM html
                            $aso_page['description'] = truncate(
                                $aso_page['description'],
                                $nbchar,
                                '... <a class="lien_lire_suite" href="' . $aso_page['url']
                                .'" '. ($nouvellefenetre ? 'target="_blank" ' : '')
                                .'title="' . _t('SYNDICATION_READ_MORE') . '">' . _t('SYNDICATION_READ_MORE') . '</a>'
                            );
                        }
                    }

                    //gestion de la date de publication, selon le flux, elle se trouve parsee ? des endroits differents
                    $aso_page['datestamp'] = strtotime($item->get_date('j M Y, g:i a'));
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
                    $syndication['pagination'] = $pagination;
                    $syndication['pages'][$aso_page['datestamp']] = $aso_page;
                }
            } else {
                echo '<p class="alert alert-danger">' . _t('ERROR') . ' ' . magpie_error() . '</p>' . "\n";
            }
        }
        $nburl = $nburl+1;
    }

    // Trie des pages par date
    krsort($syndication['pages']);
    echo '<div class="feed_syndication' . ($class ? ' ' . $class : '') . '">' . "\n";

    // Gestion des squelettes
    include($template);
    echo '</div>' . "\n";
} else {
    echo '<div class="alert alert-danger"><strong>' . _t('SYNDICATION_ACTION_SYNDICATION') . '</strong> : '
         ._t('SYNDICATION_PARAM_URL_REQUIRED') . '.</div>' . "\n";
}
