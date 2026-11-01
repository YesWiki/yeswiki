<?php

$url = $this->GetParameter('url');
$type = $this->getParameter('type');

$scheme = strtolower((string)parse_url((string)preg_replace('/[\x00-\x20\x7f]+/', '', (string)$url), PHP_URL_SCHEME));
if (!empty($url) && !in_array($scheme, ['', 'http', 'https'], true)) {
    $url = '';
}
$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;

if (!empty($url)) {
    $height = $this->GetParameter('height');
    if (empty($height)) {
        $height = '300px';
    }

    $width = $this->GetParameter('width');
    if (empty($width)) {
        $width = '400px';
    }

    $height = htmlspecialchars($height, ENT_QUOTES);
    $width = htmlspecialchars($width, ENT_QUOTES);
    $extension = strtolower(substr(strrchr($url, '.'), 1));
    $htmlUrl = htmlspecialchars($url, ENT_QUOTES);
    if ($type == 'audio' || $extension == 'mp3' || $extension == 'm4a') {
        if (!isset($GLOBALS['jplayer'])) {
            $GLOBALS['jplayer'] = 1;
            $this->AddJavascriptFile('tools/attach/libs/vendor/jplayer/jquery.jplayer.min.js');
        } else {
            $GLOBALS['jplayer']++;
        }
        $script = '$(document).ready(function(){
    // Local copy of jQuery selectors, for performance.
    var	my_jPlayer = $("#jquery_jplayer_' . $GLOBALS['jplayer'] . '"),
        my_playbtn = $("#jp_container_' . $GLOBALS['jplayer'] . ' .jp-play"),
        my_pausebtn = $("#jp_container_' . $GLOBALS['jplayer'] . ' .jp-pause"),
        my_extraPlayInfo = $("#jp_container_' . $GLOBALS['jplayer'] . ' .extra-play-info");

    // Change the time format
    $.jPlayer.timeFormat.padMin = true;
    $.jPlayer.timeFormat.padSec = true;
    $.jPlayer.timeFormat.sepMin = ":";
    $.jPlayer.timeFormat.sepSec = "";

    $("#jquery_jplayer_' . $GLOBALS['jplayer'] . '").jPlayer({
        ready: function () {
            $(this).jPlayer("setMedia", {
                mp3:' . json_encode($url, $jsonFlags) . '
            });
        },
        cssSelectorAncestor: "#jp_container_' . $GLOBALS['jplayer'] . '",
        swfPath: "tools/attach/libs/vendor/jplayer",
        timeupdate: function(event) {
            my_extraPlayInfo.css({width : parseInt(event.jPlayer.status.currentPercentAbsolute, 10) + "%"});
        },
        play: function(event) {
            my_playbtn.before(my_pausebtn);
            my_pausebtn.show();
        },
        pause: function(event) {	
            my_pausebtn.before(my_playbtn);
            my_playbtn.show();
        },
        ended: function(event) {
            my_pausebtn.before(my_playbtn);
            my_playbtn.show();
        },
        supplied: "mp3",
        wmode: "window"
    });
});' . "\n";
        $this->AddJavascript($script);

        $output = '<div id="jp_wrapper_' . $GLOBALS['jplayer'] . '" class="no-dblclick jp-wrapper" role="application" aria-label="audio player">
            <div id="jquery_jplayer_' . $GLOBALS['jplayer'] . '" class="jp-jplayer"></div>
            <div id="jp_container_' . $GLOBALS['jplayer'] . '" class="jp-audio">
                <div class="btn-group btn-group-sm no-dblclick">
                    <a href="#" class="jp-play btn btn-default btn-primary"><i class="fa fa-play icon-play icon-white"></i></a>
                    <a href="#" class="jp-pause btn btn-default btn-primary" style="display:none"><i class="fa fa-pause icon-pause icon-white"></i></a>
                    <a href="#" class="jp-stop btn btn-default"><i class="fa fa-stop icon-stop"></i></a>
                    <span class="btn btn-default" style="width:140px; position:relative;">
                        <span style="width:100%; text-align:center; z-index:2; position:absolute; left:0;">
                            <span class="jp-current-time">00:00</span> / <span class="jp-duration">00:00</span>
                        </span>
                        <div class="progress" style="margin-top:-1px;margin-bottom:-1px;">
                            <div class="bar extra-play-info" style="width: 0%;background: rgba(0, 0, 0, .2);height: 100%;"></div>
                        </div>
                    </span>
                    <a href="#" class="jp-unmute btn btn-default"><i class="fa fa-volume-off icon-volume-off"></i></a>
                    <a href="#" class="jp-mute btn btn-default" style="display: none;"><i class="fa fa-volume-up icon-volume-up"></i></a>
                    <a href="' . $htmlUrl . '" download rel="download" title="' . _t('ATTACH_DOWNLOAD_THE_FILE') . ' : ' . $htmlUrl . '" class="btn btn-default"><i class="fas fa-download"></i></a>
                </div>
            </div>
          </div>';
        echo $output;
    } elseif ($type == 'video' || $extension == 'webm' || $extension == 'mp4' || $extension == 'ogg' || $extension == 'flv') {
        if (!isset($GLOBALS['jplayer'])) {
            $GLOBALS['jplayer'] = 1;
            $this->AddJavascriptFile('tools/attach/libs/vendor/jplayer/jquery.jplayer.min.js');
        } else {
            $GLOBALS['jplayer']++;
        }
        switch ($extension) {
            case 'flv':
                $playbackFormat = 'flv';
                break;
            case 'ogg':
                $playbackFormat = 'ogv';
                break;
            case 'webmv':
                $playbackFormat = 'webmv';
                break;
            case 'mp4':
            default:
                $playbackFormat = 'm4v';
                break;
        }

        $script = '$(document).ready(function(){
    // Local copy of jQuery selectors, for performance.
    var	my_jPlayer = $("#jquery_jplayer_' . $GLOBALS['jplayer'] . '"),
        my_playbtn = $("#jp_container_' . $GLOBALS['jplayer'] . ' .jp-play"),
        my_pausebtn = $("#jp_container_' . $GLOBALS['jplayer'] . ' .jp-pause"),
        my_extraPlayInfo = $("#jp_container_' . $GLOBALS['jplayer'] . ' .extra-play-info");

    // Change the time format
    $.jPlayer.timeFormat.padMin = true;
    $.jPlayer.timeFormat.padSec = true;
    $.jPlayer.timeFormat.sepMin = ":";
    $.jPlayer.timeFormat.sepSec = "";

    $("#jquery_jplayer_' . $GLOBALS['jplayer'] . '").jPlayer({
        ready: function () {
            $(this).jPlayer("setMedia", {
                ' . $playbackFormat . ':' . json_encode($url . '#t=0.1', $jsonFlags) . '
            });
        },
        cssSelectorAncestor: "#jp_container_' . $GLOBALS['jplayer'] . '",
        swfPath: "tools/attach/libs/vendor/jplayer",
        timeupdate: function(event) {
            my_extraPlayInfo.css({width : parseInt(event.jPlayer.status.currentPercentAbsolute, 10) + "%"});
        },
        play: function(event) {
            my_playbtn.before(my_pausebtn);
            my_pausebtn.show();
        },
        pause: function(event) {
            my_pausebtn.before(my_playbtn);
            my_playbtn.show();
        },
        ended: function(event) {
            my_pausebtn.before(my_playbtn);
            my_playbtn.show();
        },
        supplied: "' . $playbackFormat . '",
        preload: "auto",
        smoothPlayBar: true,
		keyEnabled: true,
		remainingDuration: false,
		toggleDuration: true,
        wmode: "window"
    });
});' . "\n";
        $this->AddJavascript($script);

        $output = '<div id="jp_wrapper_' . $GLOBALS['jplayer'] . '" class="no-dblclick jp-wrapper" role="application" aria-label="media player">
            <div id="jquery_jplayer_' . $GLOBALS['jplayer'] . '" class="jp-jplayer jp-jplayer-video"></div>
            <div id="jp_container_' . $GLOBALS['jplayer'] . '" class="jp-video">
                <div class="btn-group btn-group-sm no-dblclick">
                    <a href="#" class="jp-play btn btn-default btn-primary"><i class="fa fa-play icon-play icon-white"></i></a>
                    <a href="#" class="jp-pause btn btn-default btn-primary" style="display:none"><i class="fa fa-pause icon-pause icon-white"></i></a>
                    <a href="#" class="jp-stop btn btn-default"><i class="fa fa-stop icon-stop"></i></a>
                    <span class="btn btn-default" style="width:140px; position:relative;">
                        <span style="width:100%; text-align:center; z-index:2; position:absolute; left:0;">
                            <span class="jp-current-time">00:00</span> / <span class="jp-duration">00:00</span>
                        </span>
                        <div class="progress" style="margin-top:-1px;margin-bottom:-1px;">
                            <div class="bar extra-play-info" style="width: 0%;background: rgba(0, 0, 0, .2);height: 100%;"></div>
                        </div>
                    </span>
                    <a href="#" class="jp-unmute btn btn-default"><i class="fa fa-volume-off icon-volume-off"></i></a>
                    <a href="#" class="jp-mute btn btn-default" style="display: none;"><i class="fa fa-volume-up icon-volume-up"></i></a>
                    <a href="#" class="jp-full-screen btn btn-default" role="button" tabindex="0"><i class="fas fa-expand-arrows-alt"></i></a>
                    <a href="' . $htmlUrl . '" rel="download" title="' . _t('ATTACH_DOWNLOAD_THE_FILE') . ' : ' . $htmlUrl . '" class="btn btn-default"><i class="fas fa-download"></i></a>
                </div>
            </div>
          </div>';
        echo $output;
    } elseif ($extension == 'mm') {
        $output = '<embed id="visorFreeMind" height="' . $height . '" align="middle" width="' . $width . '" flashvars="openUrl=_blank&initLoadFile=' . $htmlUrl . '&startCollapsedToLevel=5" quality="high" bgcolor="#ffffff" src="tools/attach/players/visorFreemind.swf" type="application/x-shockwave-flash"/>';
        $output .= "[<a href=\"$htmlUrl\" title=\"" . _t('ATTACH_DOWNLOAD_THE_FILE') . '">mm</a>]';
        echo $output;
    } else {
        echo '<div class="alert alert-danger"><strong>' . _t('ATTACH_ACTION_PLAYER') . '</strong> : ' . _t('ATTACH_PLAYER_CAN_ONLY_OPEN_FILES_LIKE') . ' (' . $htmlUrl . ') ' . _t('ATTACH_NOT_LINKED_TO_GOOD_FILE_EXTENSION') . '.</div>' . "\n";
    }
} else {
    echo '<div class="alert alert-danger"><strong>' . _t('ATTACH_ACTION_PLAYER') . '</strong> : '
        . _t('ATTACH_PARAM_URL_REQUIRED') . '.</div>' . "\n";
}
