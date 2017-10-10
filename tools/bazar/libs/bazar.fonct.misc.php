<?php

function getConfigValue($key, $default = false, $cfg = '')
{
    if (isset($cfg[$key]) and !empty($cfg[$key])) {
        return $cfg[$key];
    } else {
        return $default;
    }
}

// Drupal code under GPL2 cf. http://stackoverflow.com/questions/13076480/php-get-actual-maximum-upload-size#25370978
// Returns a file size limit in bytes based on the PHP upload_max_filesize
// and post_max_size
function file_upload_max_size()
{
    static $max_size = -1;

    if ($max_size < 0) {
        // Start with post_max_size.
        $max_size = parse_size(ini_get('post_max_size'));

        // If upload_max_size is less, then reduce. Except if upload_max_size is
        // zero, which indicates no limit.
        $upload_max = parse_size(ini_get('upload_max_filesize'));
        if ($upload_max > 0 && $upload_max < $max_size) {
            $max_size = $upload_max;
        }
    }
    return $max_size;
}

function parse_size($size)
{
    $unit = preg_replace('/[^bkmgtpezy]/i', '', $size); // Remove the non-unit characters from the size.
    $size = preg_replace('/[^0-9\.]/', '', $size); // Remove the non-numeric characters from the size.
    if ($unit) {
        // Find the position of the unit in the ordered string which is the power of magnitude to multiply a kilobyte by.
        return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
    } else {
        return round($size);
    }
}

function sanitizeFilename($string = '')
{
    // our list of "dangerous characters", add/remove characters if necessary
    $dangerous_characters = array(" ", '"', "'", "&", "/", "\\", "?", "#", "(", ")", "+");
    // every forbidden character is replace by an underscore
    $string = str_replace($dangerous_characters, '-', removeAccents($string));
    // Only allow one dash separator at a time (and make string lowercase)
    return mb_strtolower(preg_replace('/--+/u', '-', $string), YW_CHARSET);
}

/** afficher_image() - genere une image en cache (gestion taille et vignettes) et l'affiche comme il faut
 *
 * @param    string champ de la base
 * @param    string nom du fichier image
 * @param    string  label pour l'image
 * @param    string classes html supplementaires
 * @param    int        largeur en pixel de la vignette
 * @param    int        hauteur en pixel de la vignette
 * @param    int        largeur en pixel de l'image redimensionnee
 * @param    int        hauteur en pixel de l'image redimensionnee
 * @return   void
 */
function afficher_image(
    $champ,
    $nom_image,
    $label,
    $class,
    $largeur_vignette,
    $hauteur_vignette,
    $largeur_image,
    $hauteur_image,
    $method = 'fit'
) {
    // l'image initiale existe t'elle et est bien avec une extension jpg ou png et bien formatee
    $destimg = sanitizeFilename($nom_image);
    if (file_exists(BAZ_CHEMIN_UPLOAD . $nom_image)
      && preg_match('/^.*\.(jpg|jpe?g|png|gif)$/i', strtolower($nom_image))) {
        // faut il creer la vignette?
        if ($hauteur_vignette != '' && $largeur_vignette != '') {
            //la vignette n'existe pas, on la genere
            if (!file_exists('cache/vignette_' . $destimg)) {
                $adr_img = redimensionner_image(
                    BAZ_CHEMIN_UPLOAD . $nom_image,
                    'cache/vignette_' . $destimg,
                    $largeur_vignette,
                    $hauteur_vignette,
                    $method
                );
            } else {
                list($width, $height, $type, $attr) = getimagesize('cache/vignette_' . $destimg);
            }

            $url_base = str_replace('wakka.php?wiki=', '', $GLOBALS['wiki']->config['base_url']);

            //faut il redimensionner l'image?
            if ($hauteur_image != '' && $largeur_image != '') {
                //l'image redimensionnee n'existe pas, on la genere
                if (!file_exists('cache/image_' . $destimg)
                    || (isset($_GET['refresh']) && $_GET['refresh'] == 1)) {
                    $adr_img = redimensionner_image(
                        BAZ_CHEMIN_UPLOAD . $nom_image,
                        'cache/image_' . $destimg,
                        $largeur_image,
                        $hauteur_image,
                        $method
                    );
                }

                //on renvoit l'image en vignette, avec quand on clique, l'image redimensionnee
                return '<a data-id="' . $champ . '" class="modalbox ' . $class
                    .'" href="' . $url_base . 'cache/image_' . $destimg . '" title="' . htmlentities($nom_image) . '">' . "\n"
                    .'<img src="' . $url_base . 'cache/vignette_' . $destimg . '" alt="' . $destimg . '"'.' />'."\n"
                    .'</a> <!-- ' . $nom_image . ' -->' . "\n";
            } else {
                //on renvoit l'image en vignette, avec quand on clique, l'image originale
                return '<a data-id="' . $champ . '" class="modalbox ' . $class
                    . '" href="' . $url_base . BAZ_CHEMIN_UPLOAD . $nom_image . '" title="' . htmlentities($nom_image) . '">' . "\n"
                    . '<img class="img-responsive" src="' . $url_base . 'cache/vignette_' . $destimg
                    . '" alt="' . $nom_image . '"' . ' rel="' . $url_base . 'cache/image_' . $destimg . '" />' . "\n"
                    . '</a> <!-- ' . $nom_image . ' -->' . "\n";
            }
        } elseif ($hauteur_image != '' && $largeur_image != '') {
            //pas de vignette, mais faut il redimensionner l'image?
            if (!file_exists('cache/image_' . $destimg)) {
                $adr_img = redimensionner_image(
                    BAZ_CHEMIN_UPLOAD . $nom_image,
                    'cache/image_' . $destimg,
                    $largeur_image,
                    $hauteur_image,
                    $method
                );
            }
            return '<img src="cache/image_' . $destimg . '" class="img-responsive ' . $class
                . '" alt="' . $destimg . '"' . ' />' . "\n";
        } else {
            //on affiche l'image originale sinon
            return '<img src="' . BAZ_CHEMIN_UPLOAD . $destimg . '" class="img-responsive ' . $class
                . '" alt="' . $destimg . '"' . ' />' . "\n";
        }
    }
}

function redimensionner_image($image_src, $image_dest, $largeur, $hauteur, $method = 'fit')
{
    if (file_exists($image_src)) {
        if (!file_exists($image_dest) || (isset($_GET['refresh']) && $_GET['refresh']==1)) {
            if (file_exists($image_dest)) {
                unlink($image_dest);
            }
            if (!class_exists('Image')) {
                include_once('tools/bazar/libs/vendor/class.Images.php');
            }

            try {
                $image = new Image($image_src);
                $image->resize($largeur, $hauteur, $method);
                // Fix Orientation
                $exif = @exif_read_data($image_src);
                if (isset($exif['Orientation'])) {
                    $orientation = $exif['Orientation'];
                    switch ($orientation) {
                        case 3:
                            $image->rotate(180);
                            break;
                        case 6:
                            $image->rotate(-90);
                            break;
                        case 8:
                            $image->rotate(90);
                            break;
                    }
                }
                $ext = explode('.', $image_dest);
                $ext = end($ext);
                $image_dest = str_replace(array('cache/', '.'.$ext), '', $image_dest);
                $image->save($image_dest, "cache", $ext);
                return 'cache/'.$image_dest.'.'.$ext;
            } catch (Exception $e) {
                echo '<div class="alert alert-danger">Erreur Image :<br>';
                echo $e->getMessage();
                echo '</div>';
                return;
            }
        } else {
            return $image_dest;
        }
    }
}

// Code provenant de spip :
function extension_autorisee($ext)
{
    $tables_images = array(

    // Images reconnues par PHP
    'jpg' => 'JPEG', 'png' => 'PNG', 'gif' => 'GIF', 'jpeg' => 'JPEG',

    // Autres images (peuvent utiliser le tag <img>)
    'bmp' => 'BMP', 'tif' => 'TIFF', 'svg' => 'SVG');

    $tables_sequences = array('aiff' => 'AIFF', 'anx' => 'Annodex', 'axa' => 'Annodex Audio', 'axv' => 'Annodex Video', 'asf' => 'Windows Media', 'avi' => 'AVI', 'flac' => 'Free Lossless Audio Codec', 'flv' => 'Flash Video', 'mid' => 'Midi', 'mng' => 'MNG', 'mka' => 'Matroska Audio', 'mkv' => 'Matroska Video', 'mov' => 'QuickTime', 'mp3' => 'MP3', 'mp4' => 'MPEG4', 'mpg' => 'MPEG', 'oga' => 'Ogg Audio', 'ogg' => 'Ogg Vorbis', 'ogv' => 'Ogg Video', 'ogx' => 'Ogg Multiplex', 'qt' => 'QuickTime', 'ra' => 'RealAudio', 'ram' => 'RealAudio', 'rm' => 'RealAudio', 'spx' => 'Ogg Speex', 'svg' => 'Scalable Vector Graphics', 'swf' => 'Flash', 'wav' => 'WAV', 'wmv' => 'Windows Media', '3gp' => '3rd Generation Partnership Project');

    $tables_documents = array('abw' => 'Abiword', 'ai' => 'Adobe Illustrator', 'bz2' => 'BZip', 'bin' => 'Binary Data', 'blend' => 'Blender', 'c' => 'C source', 'cls' => 'LaTeX Class', 'css' => 'Cascading Style Sheet', 'csv' => 'Comma Separated Values', 'deb' => 'Debian', 'doc' => 'Word', 'docx' => 'Word', 'djvu' => 'DjVu', 'dvi' => 'LaTeX DVI', 'eps' => 'PostScript', 'gz' => 'GZ', 'h' => 'C header', 'html' => 'HTML', 'kml' => 'Keyhole Markup Language', 'kmz' => 'Google Earth Placemark File', 'pas' => 'Pascal', 'pdf' => 'PDF', 'pgn' => 'Portable Game Notation', 'ppt' => 'PowerPoint', 'pptx' => 'PowerPoint', 'ps' => 'PostScript', 'psd' => 'Photoshop', 'pub' => 'Microsoft Publisher', 'rpm' => 'RedHat/Mandrake/SuSE', 'rtf' => 'RTF', 'sdd' => 'StarOffice', 'sdw' => 'StarOffice', 'sit' => 'Stuffit', 'sty' => 'LaTeX Style Sheet', 'sxc' => 'OpenOffice.org Calc', 'sxi' => 'OpenOffice.org Impress', 'sxw' => 'OpenOffice.org', 'tex' => 'LaTeX', 'tgz' => 'TGZ', 'torrent' => 'BitTorrent', 'ttf' => 'TTF Font', 'txt' => 'texte', 'xcf' => 'GIMP multi-layer', 'xspf' => 'XSPF', 'xls' => 'Excel', 'xlsx' => 'Excel', 'xml' => 'XML', 'zip' => 'Zip',

    // open document format
    'odt' => 'opendocument text', 'ods' => 'opendocument spreadsheet', 'odp' => 'opendocument presentation', 'odg' => 'opendocument graphics', 'odc' => 'opendocument chart', 'odf' => 'opendocument formula', 'odb' => 'opendocument database', 'odi' => 'opendocument image', 'odm' => 'opendocument text-master', 'ott' => 'opendocument text-template', 'ots' => 'opendocument spreadsheet-template', 'otp' => 'opendocument presentation-template', 'otg' => 'opendocument graphics-template',);

    if (array_key_exists($ext, $tables_images)) {
        return true;
    } else {
        if (array_key_exists($ext, $tables_sequences)) {
            return true;
        } else {
            if (array_key_exists($ext, $tables_documents)) {
                return true;
            } else {
                return false;
            }
        }
    }
}

function obtenir_extension($filename)
{
    $pos = strrpos($filename, '.');
    if ($pos === false) {
         // dot is not found in the filename
        return ''; // no extension
    } else {
        $extension = substr($filename, $pos + 1);
        return $extension;
    }
}
