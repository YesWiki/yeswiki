<?php
/**
 * Action to display a responsive Vimeo video.
 *
 * @param id    the video id, for vimeo it's a series of figures whereas for youtube it's a series of letters
 * @param serveur  the serveur used, only 'peertube', 'vimeo' and 'youtube' are allowed
 * @param peertubeinstance  Instance of the serveur for PeerTube
 * @param ratio  the ratio to display the video. By defaut, it's a 16/9 ration, if '4par3' is specified a 4/3 ration
 * @param largeurmax  the maximum wanted width ; number without "px"
 * @param hauteurmax  the maximum wanted heigth ; number without "px"
 * @param class class add class to the container : use "pull-right" and "pull-left" for position
 * is applied.
 *
 * @category YesWiki
 * @package  attach
 * @author   Adrien Cheype <adrien.cheype@gmail.com>
 * @author   Jérémy Dufraisse <jeremy.dufraisse@orange.fr>
 * @license  https://www.gnu.org/licenses/agpl-3.0.en.html AGPL 3.0
 * @link     https://yeswiki.net
 */

if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

!defined('ALLOWED_SERVERS') && define('ALLOWED_SERVERS', array('vimeo', 'youtube','peertube'));

$id = $this->GetParameter("id");
$serveur = $this->GetParameter("serveur");
if (empty($serveur)) {
    $serveur = $this->config['attach-video-config']['default_video_service'];
}
if ($serveur == 'peertube') {
    $peertubeinstance = $this->GetParameter("peertubeinstance");
    if (empty($peertubeinstance)) {
        $peertubeinstance = $this->config['attach-video-config']['default_peertube_instance'];
    }
}

if (empty($id) || empty($serveur) || !in_array(strtolower($serveur), ALLOWED_SERVERS)) {
    echo '<div class="alert alert-danger">' . _t('ATTACH_ACTION_VIDEO_PARAM_ERROR') . '</div>'."\n";
} else {
    $ratio = $this->GetParameter("ratio");

    if ($ratio == '4par3') {
        $ratioCss = 'embed-responsive-4by3';
    } else {
        $ratioCss = 'embed-responsive-16by9';
    }

    $maxWidth = $this->GetParameter("largeurmax");
    $maxHeight = $this->GetParameter("hauteurmax");
    
    $manageSize = false ;
    if (!empty($maxWidth) && is_numeric($maxWidth)) {
        $manageSize = true ;
        if (empty($maxHeight) || !(is_numeric($maxHeight))) {
            $maxHeight = ($ratio == '4par3') ? ($maxWidth * 3 /4) : ($maxWidth * 9 /16) ;
        } else {
            // calculte the minimum between width and height
            $newMaxHeight = min(($ratio == '4par3') ? ($maxWidth * 3 /4) : ($maxWidth * 9 /16), $maxHeight) ;
            $newMaxWidth = min(($ratio == '4par3') ? ($maxHeight * 4 /3) : ($maxHeight * 16 /9), $maxWidth) ;
            $maxHeight = $newMaxHeight ;
            $maxWidth = $newMaxWidth ;
        }
    } elseif (!empty($maxHeight) && is_numeric($maxHeight)) {
        $manageSize = true ;
        if (empty($maxWidth) || !(is_numeric($maxWidth))) {
            $maxWidth = ($ratio == '4par3') ? ($maxHeight * 4 /3) : ($maxHeight * 16 /9) ;
        }
    }
    
    $baseObject = $this ; // for compatibility with php actions
    include dirname(dirname(__FILE__)). DIRECTORY_SEPARATOR . 'libs' . DIRECTORY_SEPARATOR . 'commons-video-pdf.php' ;
    
    echo '<div class="' . ($class_for_embed ? $class_for_embed . ' ' : '') . 'embed-responsive ' . $ratioCss . '"'. $styleForSize . '>' ;
    if ($serveur == 'vimeo') {
        echo '<iframe src="https://player.vimeo.com/video/' . $id
            . '?color=ffffff&title=0&byline=0&portrait=0" class="embed-responsive-item" frameborder="0"'
            . 'allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture; fullscreen" '
            . 'allowfullscreen></iframe>';
    } elseif ($serveur == 'peertube') {
        echo '<iframe src="'.$peertubeinstance.'videos/embed/' . $id
            . '" class="embed-responsive-item" sandbox="allow-same-origin allow-scripts" frameborder="0"'
            . 'allowfullscreen></iframe>';
    } else {
        echo '<iframe src="https://www.youtube-nocookie.com/embed/' . $id
            . '?cc_load_policy=1&iv_load_policy=3&modestbranding=1" class="embed-responsive-item" frameborder="0"'
            . 'allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture; fullscreen" '
            . 'allowfullscreen></iframe>';
    }
    echo '</div>' ;
    if ($manageSize) {
        echo '</div>' ;
    }
    if ($managePosition) {
        echo '</div>' ;
    }
}
