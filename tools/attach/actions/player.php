<?php
/*
player.php
Code original de ce fichier : Florian SCHMITT
All rights reserved.
Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions
are met:
1. Redistributions of source code must retain the above copyright
notice, this list of conditions and the following disclaimer.
2. Redistributions in binary form must reproduce the above copyright
notice, this list of conditions and the following disclaimer in the
documentation and/or other materials provided with the distribution.
3. The name of the author may not be used to endorse or promote products
derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE AUTHOR ``AS IS'' AND ANY EXPRESS OR
IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES
OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT,
INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}

$url = $this->GetParameter('url');
if (!empty($url)) {
	$height = $this->GetParameter('height');
	if (empty($height)) $height = "300px";

	$width = $this->GetParameter('width');
	if (empty($width)) $width = "400px";


	if (fopen($url, "r")) {
		$extension = strtolower(substr(strrchr($url, '.'), 1));
		if ($extension=="mp3") {
			if (!isset($GLOBALS['jplayer'])) {
				$GLOBALS['jplayer'] = 1;
				$GLOBALS['js'] = (isset($GLOBALS['js']) ? $GLOBALS['js'] : '').'<script src="tools/attach/libs/vendor/jplayer.2.2.0/js/jquery.jplayer.min.js"></script>'."\n";
			}
			else {
				$GLOBALS['jplayer']++;
			}
			$GLOBALS['js'] = (isset($GLOBALS['js']) ? $GLOBALS['js'] : '').
				'<script>
					//<![CDATA[
					$(document).ready(function(){
						// Local copy of jQuery selectors, for performance.
						var	my_jPlayer = $("#jquery_jplayer_'.$GLOBALS['jplayer'].'"),
						    my_playbtn = $("#jp_container_'.$GLOBALS['jplayer'].' .jp-play"),
						    my_pausebtn = $("#jp_container_'.$GLOBALS['jplayer'].' .jp-pause"),
							my_extraPlayInfo = $("#jp_container_'.$GLOBALS['jplayer'].' .extra-play-info");

						// Change the time format
						$.jPlayer.timeFormat.padMin = true;
						$.jPlayer.timeFormat.padSec = true;
						$.jPlayer.timeFormat.sepMin = ":";
						$.jPlayer.timeFormat.sepSec = "";

						$("#jquery_jplayer_'.$GLOBALS['jplayer'].'").jPlayer({
							ready: function () {
								$(this).jPlayer("setMedia", {
									mp3:"'.$url.'"
								});
							},
							cssSelectorAncestor: "#jp_container_'.$GLOBALS['jplayer'].'",
							swfPath: "tools/attach/libs/vendor/jplayer.2.2.0/js",
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
					});
					//]]>
				</script>'."\n";

			$output = '
				<div id="jquery_jplayer_'.$GLOBALS['jplayer'].'" class="jp-jplayer"></div>
				<div id="jp_container_'.$GLOBALS['jplayer'].'" class="jp-audio">
					<div class="btn-group no-dblclick">
						<a href="#" class="jp-play btn btn-primary btn-small"><i class="icon-play icon-white"></i></a>
						<a href="#" class="jp-pause btn btn-primary btn-small" style="display:none"><i class="icon-pause icon-white"></i></a>
						<a href="#" class="jp-stop btn btn-small"><i class="icon-stop"></i></a>
						<span class="btn btn-small disabled" style="width:100px; position:relative;">
							<span style="width:100%; text-align:center; z-index:2; position:absolute; left:0;">
								<span class="jp-current-time">00:00</span> / <span class="jp-duration">00:00</span>
							</span>
							<div class="progress" style="margin-bottom:0; height:18px">
						    	<div class="bar extra-play-info" style="width: 0%;"></div>
						    </div>
					    </span>
					    <a href="#" class="jp-mute btn btn-small"><i class="icon-volume-off"></i></a>
						<a href="#" class="jp-unmute btn btn-small" style="display: none;"><i class="icon-volume-up"></i></a>
						<a href="'.$url.'" title="T&eacute;l&eacute;charger le fichier '.($url).'" class="btn btn-small"><i class="icon-download-alt"></i></a>
					</div>
				</div>';
			echo $output;
		}
		elseif ($extension=="webm" || $extension=="mp4" || $extension=="ogg") {
			//todo jplayer video
		}
		elseif ($extension=="flv")
		{
			$output = '<a  
							 href="'.$url.'"  
							 style="display:block;width:'.$width.';height:'.$height.'"  
							 class="flvplayer"> 
						</a>'."\n";         
			$output .= '<script type="text/javascript" src="tools/attach/players/flowplayer-3.1.4.min.js"></script> 
						<script>
							flowplayer("a.flvplayer", "tools/attach/players/flowplayer-3.2.2.swf", { 
							    clip:  { 
								autoPlay: false, 
								autoBuffering: false
							    },
							    plugins:  { 
							        controls: {             
									url: \'tools/attach/players/flowplayer.controls-3.2.1.swf\', 
									autoHide: \'always\', 
									 
									// which buttons are visible and which are not? 
									play:true,      
									volume:true, 
									mute:true,  
									time:true,  
									stop:true, 
									playlist:false,  
									fullscreen:true, 
									 
									// scrubber is a well-known nickname for the timeline/playhead combination 
									scrubber: true         
									 
									// you can also use the "all" flag to disable/enable all controls 
								}
							    } 
							});
						</script>';
			echo $output;
		}
		elseif ($extension=="mm") 
		{
			$output = '<embed id="visorFreeMind" height="'.$height.'" align="middle" width="'.$width.'" flashvars="openUrl=_blank&initLoadFile='.$url.'&startCollapsedToLevel=5" quality="high" bgcolor="#ffffff" src="tools/attach/players/visorFreemind.swf" type="application/x-shockwave-flash"/>';
			$output .="[<a href=\"$url\" title=\"T&eacute;l&eacute;charger le fichier Freemind\">mm</a>]";
			echo $output;
		}
		else echo '<div class="alert">Le player ne peut que lire les fichiers mp3, flv et mm, et votre URL ('.$url.') ne pointe pas sur ces types de fichiers.</div>'."\n";
	}
	else
	{
		echo '<div class="alert alert-danger">Action player : l\'URL n\'est pas valide ou ne peut pas &ecirc;tre ouverte.</div>'."\n";
	}
}
else {
	echo '<div class="alert alert-danger">Action player : param&egrave;tre url obligatoire.</div>'."\n";
}

?>
