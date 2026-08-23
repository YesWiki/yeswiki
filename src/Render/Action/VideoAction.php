<?php

namespace YesWiki\Render\Action;

/*
 * Action to display a responsive Vimeo video.
 *
 * @param id    the video id, for vimeo it's a series of figures whereas for youtube it's a series of letters
 * @param server  the video service used, only 'peertube', 'vimeo' and 'youtube' are allowed
 * @param peertubeinstance  Instance of the server for PeerTube
 * @param ratio  the ratio to display the video. By default, it's a 16/9 ratio, if '4par3' is specified a 4/3 ration
 * @param maxwidth  the maximum wanted width ; number without "px"
 * @param maxheight  the maximum wanted heigth ; number without "px"
 * @param class class add class to the container : use "pull-right" and "pull-left" for position
 * is applied
 *
 * @category YesWiki
 *
 * @author   Adrien Cheype <adrien.cheype@gmail.com>
 * @author   Jérémy Dufraisse <jeremy.dufraisse@orange.fr>
 * @license  https://www.gnu.org/licenses/agpl-3.0.en.html AGPL 3.0
 *
 * @see     https://yeswiki.net
 */

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Component\Setting;
use YesWiki\Kernel\Performable\RegisteredAction;

class VideoAction extends YesWikiAction implements RegisteredAction, ProvidesComponents
{
    /** `{{video}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'video';
    }

    public function components(): array
    {
        return [
            Component::for('video')
                ->category(Category::Media)
                ->label(_t('AB_attach_video_label'))
                ->icon('player-play')
                ->description(_t('AB_attach_video_description'))
                ->hint(_t('AB_attach_video_hint'))
                ->previewHeight('250px')
                ->settings(
                    Setting::url('url')
                        ->label('Url')
                        ->hint(_t('AB_attach_video_url_hint')),
                    Setting::choice('server', [
                        'peertube' => 'PeerTube',
                        'youtube' => 'Youtube',
                        'vimeo' => 'Vimeo',
                    ])
                        ->label(_t('AB_attach_video_serveur_label'))
                        ->suggests('peertube')
                        ->required(),
                    Setting::url('peertubeinstance')
                        ->label(_t('AB_attach_video_peertubeinstance_label'))
                        ->showIf([
                            'server' => 'peertube',
                        ]),
                    Setting::text('id')
                        ->label(_t('AB_attach_video_id_label')),
                    Setting::choice('ratio', [
                        '' => '16/9',
                        '4par3' => '4/3',
                    ])
                        ->label(_t('AB_attach_video_ratio_label'))
                        ->default(''),
                    Setting::number('maxwidth')
                        ->label(_t('AB_attach_video_largeur_max_label')),
                    Setting::number('maxheight')
                        ->label(_t('AB_attach_video_hauteur_max_label')),
                    Setting::cssClass('class')
                        ->label('Classe')
                        ->subSettings(
                            Setting::choice('position', [
                                '' => 'standard',
                                'pull-left' => _t('AB_LEFT'),
                                'pull-right' => _t('AB_RIGHT'),
                            ])
                            ->label(_t('AB_attach_video_position_label'))
                            ->default(''),
                        ),
                ),
        ];
    }

    public const ALLOWED_SERVERS = ['vimeo', 'youtube', 'peertube', 'dailymotion'];

    public function formatArguments($arg)
    {
        $server = $arg['server'] ?? '';
        $attachVideoConfig = $this->params->get('attach-video-config');
        if (!is_array($attachVideoConfig)) {
            $attachVideoConfig = [];
        }
        if (empty($server)) {
            $server = $attachVideoConfig['default_video_service'] ?? '';
        }

        $url = (!empty($arg['url']) && is_string($arg['url'])) ? $arg['url'] : '';
        $matches = [];
        $id = $arg['id'] ?? '1f5bfc59-998b-41b3-9be3-e8084ad1a2a1';
        $peertubeinstance = $arg['peertubeinstance'] ?? '';
        if (empty($peertubeinstance)) {
            $peertubeinstance = $attachVideoConfig['default_peertube_instance'] ?? '';
        }
        if (substr($peertubeinstance, -1) != '/') {
            $peertubeinstance .= '/';
        }
        if (preg_match('/^'
            . '(https?:\\/\\/.*)'
            . '(?:'
                . 'youtu\.be\/(.+)|youtube.*watch\?v=([^&]+)'
                . '|vimeo\.com\/(.+)'
                . '|(?:dai\.?ly.*\/video\/|dai\.ly\/)(.+)'
                . '|(?:\/videos\/embed\/|\/w\/)(.+)'
            . ')/', $url, $matches)) {
            if (!empty($matches[2])) {
                $server = 'youtube';
                $id = $matches[2];
            } elseif (!empty($matches[3])) {
                $server = 'youtube';
                $id = $matches[3];
            } elseif (!empty($matches[4])) {
                $server = 'vimeo';
                $id = $matches[4];
            } elseif (!empty($matches[5])) {
                $server = 'dailymotion';
                $id = $matches[5];
            } elseif (!empty($matches[6])) {
                $server = 'peertube';
                $id = $matches[6];
                $peertubeinstance = $matches[1] . '/';
            }
        }

        return [
            'id' => $id,
            'server' => $server,
            'peertubeinstance' => $peertubeinstance,
            'ratio' => $arg['ratio'] ?? '',
            'maxwidth' => $arg['maxwidth'] ?? '',
            'maxheight' => $arg['maxheight'] ?? '',
            'class' => str_replace('attached_file', '', $arg['class'] ?? ''),
        ];
    }

    /**
     * @return string the rendered video embed, or an error message for a missing/unknown server
     */
    public function run()
    {
        if (empty($this->arguments['id'])
            || empty($this->arguments['server'])
            || !in_array(strtolower($this->arguments['server']), self::ALLOWED_SERVERS)) {
            return $this->render('@core/alert-message.twig', [
                'type' => 'danger',
                'message' => _t('ATTACH_ACTION_VIDEO_PARAM_ERROR'),
            ]);
        }
        if ($this->arguments['ratio'] == '4par3') {
            $shape = 'embed-responsive-4by3 ratio ratio-4x3';
        } else {
            $shape = 'embed-responsive-16by9 ratio ratio-16x9';
        }

        $maxWidth = $this->arguments['maxwidth'];
        $maxHeight = $this->arguments['maxheight'];
        $manageSize = false;
        if (!empty($maxWidth) && is_numeric($maxWidth)) {
            $manageSize = true;
            if (empty($maxHeight) || !is_numeric($maxHeight)) {
                $maxHeight = ($this->arguments['ratio'] == '4par3') ? ($maxWidth * 3 / 4) : ($maxWidth * 9 / 16);
            } else {
                $newMaxHeight = min(($this->arguments['ratio'] == '4par3') ? ($maxWidth * 3 / 4) : ($maxWidth * 9 / 16), $maxHeight);
                $newMaxWidth = min(($this->arguments['ratio'] == '4par3') ? ($maxHeight * 4 / 3) : ($maxHeight * 16 / 9), $maxWidth);
                $maxHeight = $newMaxHeight;
                $maxWidth = $newMaxWidth;
            }
        } elseif (!empty($maxHeight) && is_numeric($maxHeight)) {
            $manageSize = true;
            if (empty($maxWidth) || !is_numeric($maxWidth)) {
                $maxWidth = ($this->arguments['ratio'] == '4par3') ? ($maxHeight * 4 / 3) : ($maxHeight * 16 / 9);
            }
        }

        return $this->render('@core/actions/video.twig', [
            'class' => $this->arguments['class'],
            'server' => $this->arguments['server'],
            'id' => $this->arguments['id'],
            'peertubeinstance' => $this->arguments['peertubeinstance'],
            'manageSize' => $manageSize,
            'maxWidth' => $maxWidth,
            'maxHeight' => $maxHeight,
            'shape' => $shape,
        ]);
    }
}
