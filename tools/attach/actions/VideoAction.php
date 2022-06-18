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

namespace YesWiki\Attach;

use YesWiki\Core\YesWikiAction;

class VideoAction extends YesWikiAction
{
    public const ALLOWED_SERVERS =['vimeo', 'youtube','peertube'];

    public function formatArguments($arg)
    {
        $serveur = $arg['serveur'] ?? "";
        $attachVideoConfig = $this->params->get('attach-video-config');
        if (empty($serveur)) {
            $serveur = $attachVideoConfig['default_video_service'];
        }
        $peertubeinstance = $arg['peertubeinstance'] ?? "";
        if ($serveur == 'peertube') {
            if (empty($peertubeinstance)) {
                $peertubeinstance = $attachVideoConfig['default_peertube_instance'];
            }
            if (substr($peertubeinstance,-1) != '/'){
                $peertubeinstance .= '/';
            }
        }
        return [
            'id' => $arg['id'] ?? "",
            'serveur' => $serveur,
            'peertubeinstance' => $peertubeinstance,
            'ratio' => $arg['ratio'] ?? "",
            'largeurmax' => $arg['largeurmax'] ?? "",
            'hauteurmax' => $arg['hauteurmax'] ?? "",
            'class' => str_replace('attached_file', '', ($arg['class'] ?? "")), // to prevent errors
        ];
    }

    public function run()
    {
        if (empty($this->arguments['id']) || 
            empty($this->arguments['serveur']) || 
            !in_array(strtolower($this->arguments['serveur']), self::ALLOWED_SERVERS)) {
                return $this->render("@templates/alert-message.twig",[
                    'type' => 'danger',
                    'message' => _t('ATTACH_ACTION_VIDEO_PARAM_ERROR')
                ]);
        } else {
            if ($this->arguments['ratio'] == '4par3') {
                $shape = 'embed-responsive-4by3';
            } else {
                $shape = 'embed-responsive-16by9';
            }
            
            $maxWidth = $this->arguments['largeurmax'];
            $maxHeight = $this->arguments['hauteurmax'];
            $manageSize = false ;
            if (!empty($maxWidth) && is_numeric($maxWidth)) {
                $manageSize = true ;
                if (empty($maxHeight) || !(is_numeric($maxHeight))) {
                    $maxHeight = ($this->arguments['ratio'] == '4par3') ? ($maxWidth * 3 /4) : ($maxWidth * 9 /16) ;
                } else {
                    // calculte the minimum between width and height
                    $newMaxHeight = min(($this->arguments['ratio'] == '4par3') ? ($maxWidth * 3 /4) : ($maxWidth * 9 /16), $maxHeight) ;
                    $newMaxWidth = min(($this->arguments['ratio'] == '4par3') ? ($maxHeight * 4 /3) : ($maxHeight * 16 /9), $maxWidth) ;
                    $maxHeight = $newMaxHeight ;
                    $maxWidth = $newMaxWidth ;
                }
            } elseif (!empty($maxHeight) && is_numeric($maxHeight)) {
                $manageSize = true ;
                if (empty($maxWidth) || !(is_numeric($maxWidth))) {
                    $maxWidth = ($this->arguments['ratio'] == '4par3') ? ($maxHeight * 4 /3) : ($maxHeight * 16 /9) ;
                }
            }
            return $this->render("@attach/actions/video.twig",[
                    'class' => $this->arguments['class'],
                    'serveur' => $this->arguments['serveur'],
                    'id' => $this->arguments['id'],
                    'peertubeinstance' => $this->arguments['peertubeinstance'],
                    'manageSize' => $manageSize,
                    'maxWidth' => $maxWidth,
                    'maxHeight' => $maxHeight,
                    'shape' => $shape,
            ]);
        }
    }
}
