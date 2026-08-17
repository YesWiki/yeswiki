<?php

function sanitizeEntity($string)
{
    return preg_replace('~&([a-z]{1,2})(?:acute|cedil|circ|grave|lig|orn|ring|slash|th|tilde|uml|caron);~i', '$1', htmlentities(html_entity_decode($string), ENT_QUOTES, YW_CHARSET));
}

function tokenTruncate($string, $your_desired_width)
{
    $parts = preg_split('/([\s\n\r]+)/', $string, 0, PREG_SPLIT_DELIM_CAPTURE);
    $parts_count = count($parts);

    $length = 0;
    $last_part = 0;
    // preg_split() returns false on a pattern error; an empty list simply ends the loop at once
    $parts = $parts === false ? [] : $parts;
    for (; $last_part < $parts_count; $last_part++) {
        $length += strlen($parts[$last_part]);
        if ($length > $your_desired_width) {
            break;
        }
    }

    return implode(array_slice($parts, 0, $last_part));
}

function get_filtertags_parameters_recursive($nb = 1, $tab = [])
{
    $filter = $GLOBALS['yeswikiServices']->get(YesWiki\Kernel\Service\PerformableArguments::class)->get('filter' . $nb);

    if (empty($filter) && $nb == 1) {
        return '<div class="alert alert-danger"><strong>' . _t('TAGS_ACTION_FILTERTAGS') . '</strong> : ' . _t('TAGS_NO_FILTERS') . '</div>' . "\n";
    } elseif (empty($filter)) {
        return $tab;
    }
    // A LIST of tag names, not a pre-quoted SQL string (ticket 31): the caller binds them, so
    // building `'a','b'` here meant this function decided the quoting for a statement it never
    // sees -- and got it wrong, since PDO::quote() only protects the single-quoted literal it
    // wraps its own output in.
    if (!isset($tab['tags'])) {
        $tab['tags'] = [];
    }
    $explodelabel = explode(':', $filter);

    // on decoupe le choix pour recuperer le titre
    if (count($explodelabel) > 2) {
        return '<div class="alert alert-danger"><strong>' . _t('TAGS_ACTION_FILTERTAGS') . '</strong> : ' . _t('TAGS_ONLY_ONE_DOUBLEPOINT') . '</div>' . "\n";
    } elseif (count($explodelabel) == 2) {
        $tab[$nb]['title'] = '<strong>' . $explodelabel[0] . ' : </strong>' . "\n";
        $tab[$nb]['arraytags'] = explode(',', $explodelabel[1]);
    } else {
        $tab[$nb]['title'] = '';
        $tab[$nb]['arraytags'] = explode(',', $explodelabel[0]);
    }
    $toggle = $GLOBALS['yeswikiServices']->get(YesWiki\Kernel\Service\PerformableArguments::class)->get('select' . $nb);
    if (!empty($toggle) && $toggle == 'checkbox') {
        $tab[$nb]['toggle'] = $toggle;
    } else {
        $tab[$nb]['toggle'] = 'radio';
    }
    $class = $GLOBALS['yeswikiServices']->get(YesWiki\Kernel\Service\PerformableArguments::class)->get('class' . $nb);
    if (!empty($class)) {
        $tab[$nb]['class'] = $class;
    } else {
        $tab[$nb]['class'] = 'filter-inline';
    }
    $tab['tags'] = [...$tab['tags'], ...array_values($tab[$nb]['arraytags'])];
    $nb++;
    $tab = get_filtertags_parameters_recursive($nb, $tab);

    return $tab;
}

function utf8_special_decode($matches)
{
    return iconv('UCS-4LE', 'UTF-8', pack('V', hexdec($matches[1])));
}

function get_title_from_body($page)
{
    // the body is a decoded array (ticket 09) : an entry carries its title as a key
    // ('bf_titre' on legacy bodies), a page its markup under 'content'
    $body = $page['body'] ?? [];
    $content = YesWiki\Content\Entity\PageBody::content($body);
    // on recupere les bf_titre ou les titres de niveau 1 et de niveau 2, on met la PageWiki sinon
    $entryTitle = $body[YesWiki\Content\Entity\PageBody::TITLE] ?? $body['bf_titre'] ?? '';
    if ($entryTitle != '') {
        $title = $entryTitle;
    } else {
        preg_match_all("/\={6}(.*)\={6}/U", $content, $titles);
        if (isset($titles[1][0]) && $titles[1][0] != '') {
            $title = $GLOBALS['yeswikiServices']->get(YesWiki\Render\Service\MarkdownFormatterService::class)->format(trim($titles[1][0]));
        } else {
            preg_match_all('/={5}(.*)={5}/U', $content, $titles);
            if (isset($titles[1][0]) && $titles[1][0] != '') {
                $title = $GLOBALS['yeswikiServices']->get(YesWiki\Render\Service\MarkdownFormatterService::class)->format(trim($titles[1][0]));
            } else {
                $title = $page['tag'];
            }
        }
    }

    return strip_tags($title);
}

function encodingFromUTF8($matches)
{
    return iconv('UCS-4LE', 'UTF-8', pack('V', hexdec($matches[1])));
}

/**
 * The first image a page shows, as an `<img>` for a card or a tile.
 *
 * The two branches that matter used to call `afficher_image_attach()` and `afficher_image()`,
 * which the rewrite deleted -- so `{{includepages}}`, `{{listpagestag}}` and `{{filtertags}}`
 * fataled with "Call to undefined function" on any page whose body held an attached image or a
 * bazar `imagebf_image`. Two `function.notFound` entries in the PHPStan baseline had been
 * saying so (ticket 40).
 *
 * Both now go through `ImageResizer`, which is what every other thumbnail in the wiki uses.
 *
 * @param array<string, mixed> $page
 */
function get_image_from_body($page): string
{
    // decoded body (ticket 09) : markup under 'content', an entry's image field as a key
    $body = $page['body'] ?? [];
    $content = YesWiki\Content\Entity\PageBody::content($body);
    preg_match_all("/\{\{attach.*file=\".*\.(?i)(jpg|png|gif|bmp).*\}\}/U", $content, $images);
    if (isset($images[0][0]) && $images[0][0] != '') {
        preg_match_all("/.*file=\"(.*\.(?i)(jpg|png|gif|bmp))\".*desc=\"(.*)\".*\}\}/U", $images[0][0], $attachimg);
        $image = yeswiki_thumbnail_tag(
            $GLOBALS['yeswikiServices']->get(YesWiki\Files\Service\AttachedFilePaths::class)->uploadPath()
                . '/' . $attachimg[1][0],
            $attachimg[3][0] ?? '',
            'filtered-image'
        );
    } else {
        $imagefile = $body['imagebf_image'] ?? '';
        if ($imagefile != '') {
            $image = yeswiki_thumbnail_tag('files/' . $imagefile, '', 'filtered-image img-responsive');
        } else {
            preg_match_all("/\[\[(http.*\.(?i)(jpg|png|gif|bmp)) .*\]\]/U", $content, $image);
            if (isset($image[1][0]) && $image[1][0] != '') {
                $image = $GLOBALS['yeswikiServices']->get(YesWiki\Render\Service\MarkdownFormatterService::class)->format('""<img loading="lazy" alt=\'\' class="img-responsive" src="' . trim(str_replace('\\', '', $image[1][0])) . '" />""');
            } else {
                preg_match_all("/\<img.*src=\"(.*)\"/U", $content, $image);
                if (isset($image[1][0]) && $image[1][0] != '') {
                    $image = $GLOBALS['yeswikiServices']->get(YesWiki\Render\Service\MarkdownFormatterService::class)->format('""<img loading="lazy" alt=\'\' class="img-responsive" src="' . trim($image[1][0]) . '" />""');
                } else {
                    $image = '';
                }
            }
        }
    }

    return $image;
}

/**
 * A resized `<img>` for a file already on disk, or '' when it cannot be produced.
 *
 * Replaces the deleted `afficher_image()` / `afficher_image_attach()` pair for the one caller
 * that still needed them. 300x225 is the size those two were always called with here.
 */
function yeswiki_thumbnail_tag(string $fileName, string $description, string $class): string
{
    if ($fileName === '' || !file_exists($fileName)) {
        return '';
    }

    $resizer = $GLOBALS['yeswikiServices']->get(YesWiki\Files\Service\ImageResizer::class);
    $thumbnail = $resizer->resizedFilename($fileName, '300', '225', 'fit');
    if (!file_exists($thumbnail) && $resizer->resize($fileName, $thumbnail, 300, 225) !== $thumbnail) {
        // the resize failed; the original still displays, just unscaled
        $thumbnail = $fileName;
    }

    return '<img loading="lazy" class="' . htmlspecialchars($class, ENT_COMPAT, YW_CHARSET) . '"'
        . ' src="' . htmlspecialchars($thumbnail, ENT_COMPAT, YW_CHARSET) . '"'
        . ' alt="' . htmlspecialchars($description, ENT_COMPAT, YW_CHARSET) . '" />';
}
