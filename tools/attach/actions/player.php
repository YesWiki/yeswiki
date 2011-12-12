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
if (empty($url)) exit($this->Format("//Action player : paramêtre url obligatoire.//"));

$height = $this->GetParameter('height');
if (empty($height)) $height="300px";

$width = $this->GetParameter('width');
if (empty($width)) $width="400px";


if (fopen($url, "r")) 
{
	$extension = substr ($url, strlen ($url) - 3);
	if ($extension=="mp3" or $extension=="MP3")
	{
		$output =  '<object type="application/x-shockwave-flash" data="tools/attach/players/dewplayer.swf?mp3='.$url.'&amp;bgcolor=EEEEEE&amp;showtime=1" width="200" height="20"><param name="wmode" value="transparent" />
						<param name="movie" value="tools/attach/players/dewplayer.swf?mp3='.$url.'&amp;bgcolor=EEEEEE&amp;showtime=1" />
					</object>';
		$output .="[<a href=\"$url\" title=\"T&eacute;l&eacute;charger le fichier mp3\">mp3</a>]";
		echo $output;
	}
	elseif ($extension=="flv" or $extension=="FLV")
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
	elseif ($extension==".mm" or $extension==".MM") 
	{
		$output = '<embed id="visorFreeMind" height="'.$height.'" align="middle" width="'.$width.'" flashvars="openUrl=_blank&initLoadFile='.$url.'&startCollapsedToLevel=5" quality="high" bgcolor="#ffffff" src="tools/attach/players/visorFreemind.swf" type="application/x-shockwave-flash"/>';
		$output .="[<a href=\"$url\" title=\"T&eacute;l&eacute;charger le fichier Freemind\">mm</a>]";
		echo $output;
	}
	else echo "Le player ne peut que lire les fichiers mp3, flv et mm, et votre URL (".$url.") ne pointe pas sur ces types de fichiers.";
}
else
{
	echo 'Action player : l\'URL n\'est pas valide';
}

?>
