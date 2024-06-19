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
    $dangerous_characters = [' ', '"', "'", '&', '/', '\\', '?', '#', '(', ')', '+'];
    // every forbidden character is replace by an underscore
    $string = str_replace($dangerous_characters, '-', removeAccents($string));
    // Only allow one dash separator at a time (and make string lowercase)
    return mb_strtolower(preg_replace('/--+/u', '-', $string), YW_CHARSET);
}

function redimensionner_image($image_src, $image_dest, $largeur, $hauteur, $method = 'fit')
{
    $wiki = $GLOBALS['wiki'];
    if (file_exists($image_src)) {
        if (!class_exists('attach')) {
            include 'tools/attach/libs/attach.lib.php';
        }
        $attach = new attach($wiki);

        // force new name
        $image_dest = $attach->getResizedFilename($image_src, $largeur, $hauteur, $method);

        if (!$wiki->services->get(SecurityController::class)->isWikiHibernated()
            && file_exists($image_dest)
            && isset($_GET['refresh'])
            && $_GET['refresh'] == 1
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
        $file = fopen($localPath, 'w+');
        fputs($file, $imgcontent);
        fclose($file);
        if ($error) {
            echo $error;

            return false;
        } else {
            return true;
        }
    } else {
        echo _t('BAZ_IMAGE_FILE_NOT_FOUND') . ' : ' . $url;

        return false;
    }
}

/* ~~~~~~~~~~~~ DEPRECATED ~~~~~~~~~~~~~~ */

/** afficher_image() - genere une image en cache (gestion taille et vignettes) et l'affiche comme il faut.
 *
 * @param    string champ de la base
 * @param    string nom du fichier image
 * @param    string  label pour l'image
 * @param    string classes html supplementaires
 * @param    int        largeur en pixel de la vignette
 * @param    int        hauteur en pixel de la vignette
 * @param    int        largeur en pixel de l'image redimensionnee
 * @param    int        hauteur en pixel de l'image redimensionnee
 *
 * @return void
 *
 * @deprecated use $wiki->render('@attach/display-image.twig') instead
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
    $wiki = $GLOBALS['wiki'];
    $authorizedExts = $wiki->config['authorized-extensions'];
    $url_base = $wiki->GetBaseUrl() . '/';
    // If we have a full URL, remove the base URL first
    $nom_image = str_replace($url_base . BAZ_CHEMIN_UPLOAD, '', $nom_image);
    $ext = pathinfo($nom_image)['extension'];

    if (!class_exists('attach')) {
        include 'tools/attach/libs/attach.lib.php';
    }
    $attach = new attach($wiki);
    $imagePath = $attach->GetUploadPath() . '/' . $nom_image;
    $attach->file = $imagePath;

    if (file_exists($imagePath)
       && $attach->isPicture()) {
        return $wiki->render('@attach/display-image.twig', [
            'baseUrl' => $url_base,
            'imageFullPath' => $imagePath,
            'fieldName' => $champ,
            'thumbnailHeight' => $hauteur_vignette,
            'thumbnailWidth' => $largeur_vignette,
            'imageHeight' => $hauteur_image,
            'imageWidth' => $largeur_image,
            'class' => $class,
            'mode' => $method,
            'showThumbnail' => $show_vignette,
        ]);
    }
}
