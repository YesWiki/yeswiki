<?php

use YesWiki\Security\Controller\SecurityController;

function getConfigValue($key, $default = false, $cfg = '')
{
    if (isset($cfg[$key]) and !empty($cfg[$key])) {
        return $cfg[$key];
    } else {
        return $default;
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
    $method = 'fit',
    $show_vignette = true
) {
    // l'image initiale existe t'elle et est bien avec une extension jpg ou png et bien formatee
    $destimg = sanitizeFilename($nom_image);
    $url_base = $GLOBALS['wiki']->GetBaseUrl().'/';
    // If we have a full URL, remove the base URL first
    $nom_image = str_replace($url_base . BAZ_CHEMIN_UPLOAD, '', $nom_image);
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
                $baseUrl = $show_vignette ? 'cache/vignette_' : 'cache/image_';
                //on renvoit l'image en vignette, avec quand on clique, l'image redimensionnee
                return '<a data-id="' . $champ . '" class="modalbox ' . $class
                    .'" href="' . $url_base . 'cache/image_' . $destimg . '" title="' . htmlentities($nom_image) . '">' . "\n"
                    .'<img src="' . $url_base . $baseUrl . $destimg . '" alt="' . $destimg . '"'.' />'."\n"
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
            return '<img src="' . $url_base . 'cache/image_' . $destimg . '" class="img-responsive ' . $class
                . '" alt="' . $destimg . '"' . ' />' . "\n";
        } else {
            //on affiche l'image originale sinon
            return '<img src="'. $url_base . BAZ_CHEMIN_UPLOAD . $destimg . '" class="img-responsive ' . $class
                . '" alt="' . $destimg . '"' . ' />' . "\n";
        }
    }
}

function redimensionner_image($image_src, $image_dest, $largeur, $hauteur, $method = 'fit')
{
    $wiki = $GLOBALS['wiki'];
    if (file_exists($image_src)) {
        if (!class_exists('attach')) {
            include('tools/attach/libs/attach.lib.php');
        }
        $attach = new attach($wiki);

        // force new name
        $image_dest = $attach->getResizedFilename($image_src,$largeur, $hauteur, $method);
        
        if (!$wiki->services->get(SecurityController::class)->isWikiHibernated()
            && file_exists($image_dest)
            && isset($_GET['refresh'])
            && $_GET['refresh']== 1
            && $wiki->UserIsAdmin()) {
            unlink($image_dest);
        }
        if (!file_exists($image_dest)) {

            $result = $attach->redimensionner_image($image_src, $image_dest, $largeur, $hauteur, $method);
            if ($result != $image_dest) {
                // do nothing : error
                return $image_src;
            }
            return $image_dest;
        } else {
            return $image_dest;
        }
    }
}

function renameUrlToSanitizedFilename($url)
{
    $str = preg_replace('/[\r\n\t ]+/', ' ', basename($url));
    $str = preg_replace('/[\"\*\/\:\<\>\?\'\|]+/', ' ', $str);
    $str = str_replace(' ', '-', $str);
    return preg_replace('/-+/', '-', $str);
}

function copyUrlToLocalFile($url, $localPath)
{
    if (file_exists($localPath)) {
        return true;
    } elseif ($ch = curl_init($url)) { // teste l'existance du fichier a distance
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $imgcontent = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        $file = fopen($localPath, "w+");
        fputs($file, $imgcontent);
        fclose($file);
        if ($error) {
            echo $error;
            return false;
        } else {
            return true;
        }
    } else {
        echo _t('BAZ_IMAGE_FILE_NOT_FOUND').' : '.$url;
        return false;
    }
}
