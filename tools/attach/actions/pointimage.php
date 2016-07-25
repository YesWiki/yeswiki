<?php
/*
pointimage.php

2006 Laurent Marseault (idea) & David Delon (code)
2013 Florian Schmitt (use of attach, rewriting for bootstrap)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*/


// Get the action's parameters :

// image's filename
$file = $this->GetParameter('file');
if (empty($file)) {
	// former parameter from filename
	$file = $this->GetParameter('srcmap');
	if (empty($file)) {
		echo '<div class="alert alert-danger"><strong>'._t('ATTACH_ACTION_POINTIMAGE').'</strong> : '._t('ATTACH_PARAM_FILE_NOT_FOUND').'.</div>'."\n";
		return;
	}
}

// test of image extension
$supported_image_extensions = array('gif', 'jpg', 'jpeg', 'png');
$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION)); // Using strtolower to overcome case sensitive
if (!in_array($ext, $supported_image_extensions)) {
   	echo '<div class="alert alert-danger"><strong>'._t('ATTACH_ACTION_POINTIMAGE').'</strong> : '._t('ATTACH_PARAM_FILE_MUST_BE_IMAGE').'.</div>'."\n";
	return;
}

// image size
$height = $this->GetParameter('height');
$width = $this->GetParameter('width');
if (empty($height) && empty($width)) { $size="original"; }


// colors of markers
$colors = $this->GetParameter('color');
if (empty($colors)) {
	// older parameter
	$colors = $this->GetParameter('pointcolor');
	if (empty($colors)) { $colors = 'green';}
}
$colors = '["'.str_replace(',', '","', $colors).'"]';


// labels of markers
$labels = $this->GetParameter('label');
if (empty($labels)) {
	$labels = _t('ATTACH_DEFAULT_MARKER');
}
$labels = '["'.str_replace(',', '","', $labels).'"]';


// default size of marker : 10 pixels
$point_size = $this->GetParameter('pointsize');
if (empty($point_size)) {
	$point_size = 10;
}

// readonly (no add of markers)
$readonly = $this->GetParameter('readonly');

// get an unique pagename based on the image filename, without extension
$datapagetag = mysqli_real_escape_string($this->dblink, $this->GetPageTag().'PI'.preg_replace("/[^A-Za-z0-9 ]/", '', str_replace('.'.$ext, '', $file)));

// save the posted data
if (isset($_POST['title']) && !empty($_POST['title'])
    && isset($_POST['description']) && !empty($_POST['description'])
    && isset($_POST['pagetag']) && !empty($_POST['pagetag'])
    && isset($_POST['image_x']) && !empty($_POST['image_x'])
    && isset($_POST['image_y']) && !empty($_POST['image_y'])
    && isset($_POST['color']) && !empty($_POST['color'])) {
	$pagetag = mysqli_real_escape_string($this->dblink, str_replace($this->config['base_url'], '', $_POST['pagetag']));
	$chaine = "\n\n~~\"\"<!--".$_POST['image_x']."-".$_POST['image_y']."-".$_POST['color']."--><!--title-->".$_POST['title']."<!--/title-->\"\"\n\"\"<!--desc-->\"\"".$_POST['description']."\"\"<!--/desc-->\n\"\"~~";
	$donneesbody = $this->LoadSingle("SELECT * FROM ".$this->config["table_prefix"]."pages WHERE tag = '".$pagetag."'and latest = 'Y' limit 1");
	$this->SavePage($pagetag, $donneesbody['body'].$chaine, "", true);
	$this->Redirect($this->Href());
}

// get the data for the image
$donneesbody = $this->LoadSingle("SELECT * FROM ".$this->config["table_prefix"]."pages WHERE tag = '".$datapagetag."'and latest = 'Y' limit 1");

// search for markers info
preg_match_all('/~~(.*)~~/msU', $donneesbody['body'], $locations);
$markers = array();
foreach ($locations[1] as $location){
	// extract all informations if present
	preg_match('/<!--([0-9][0-9]*)-([0-9][0-9]*)-(.*)--><!--title-->(.*)<!--\/title-->.*<!--desc-->\"\"(.*)\"\"<!--\/desc-->/msU', $location, $elements);
	if ($elements[1]) {
		$marker['x'] = round($elements[1]);
		$marker['y'] = round($elements[2]);
		$marker['color'] = $elements[3];
		$marker['title'] = $elements[4];
		$marker['description'] = $this->Format($elements[5]);
	}

	if (count($marker)==5) {
		$markers[] = $marker;
	}
}

// create markers links
$listofmarkers = '';
if (count($markers)>0) {
	foreach ($markers as $nb => $marker ) {
		// all informations must be written in one line and escaped from html chars
		$marker['title'] = htmlspecialchars(str_replace(array("\r\n", "\r", "\n", PHP_EOL, chr(10), chr(13), chr(10).chr(13)), "", $marker['title']), ENT_QUOTES, YW_CHARSET);
    $marker['modaltitle'] = htmlspecialchars('<button type="button" class="btn-close-popover pull-right close">&times;</button>', ENT_QUOTES, YW_CHARSET).$marker['title'];
		$marker['description'] = htmlspecialchars(str_replace(array("\r\n", "\r", "\n", PHP_EOL, chr(10), chr(13), chr(10).chr(13)), "", $marker['description']), ENT_QUOTES, YW_CHARSET);

		$listofmarkers .= "<a
    class=\"img-marker\"
    style=\"height:".$point_size."px;width:".$point_size."px;left:".($marker['x']-round($point_size/2))."px;
    top:".($marker['y']-round($point_size/2))."px;background:".$marker['color'].";\"
    data-toggle=\"popover\"
    data-trigger=\"hover\"
    data-original-title=\"".$marker['title']."\"
    data-content=\"".$marker['description']."\" href=\"#\"></a>\n";
	}
}

$javascriptadded = '	<script src="tools/attach/libs/pointimage.js"></script>

	<div class="modal fade modal-pointimage">
	  <div class="modal-dialog">
	    <div class="modal-content">
	      <div class="modal-header">
	        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
	        <h4 class="modal-title">'._t('ATTACH_ADD_MARKER').'</h4>
	      </div>
	      <form class="form-pointimage" method="post" action="'.$this->href().'">
	      <div class="modal-body">
	      	<div class="form-group markers-choice"></div>
	     	<div class="form-group">
	        	<input name="title" required="required" class="form-control" placeholder="'._t('ATTACH_TITLE').'" />
	        </div>
	        <div class="form-group">
	        	<textarea name="description" required="required" class="form-control wiki-textarea" placeholder="'._t('ATTACH_DESCRIPTION').'"></textarea>
	        </div>
	      </div>
	      <div class="modal-footer">
	        <button type="button" class="btn btn-default btn-close" data-dismiss="modal">'._t('ATTACH_CANCEL').'</button>
	        <button type="submit" class="btn btn-primary btn-save">'._t('ATTACH_SAVE').'</button>
	      </div>
	      </form>
	    </div><!-- /.modal-content -->
	  </div><!-- /.modal-dialog -->
	</div><!-- /.modal -->'."\n";

// adds the javascript just one time
if (!isset($GLOBALS['pointimagejsincluded'])) {
	$GLOBALS['js'] =  (isset($GLOBALS['js']) ? $GLOBALS['js'] : '').$javascriptadded;
	$GLOBALS['pointimagejsincluded'] = true;
}

// output the image on the page

echo '<div class="pointimage-container no-dblclick" data-readonly="'.((!empty($readonly) && $readonly==1) ? 'true' : 'false').'" data-markerscolor=\''.$colors.'\' data-markerslabel=\''.$labels.'\' data-markersize="'.$point_size.'" data-pagetag="'.$this->Href('', $datapagetag).'">'."\n";
if (isset($size)) {
	echo $this->Format('{{attach file="'.$file.'" desc="image '.$file.'" size="original" class="pointimage-image" nofullimagelink="1"}}');
} else {
	echo $this->Format('{{attach file="'.$file.'" desc="image '.$file.'"'.(!empty($width) ? ' width="'.$width.'"' : '').(!empty($height) ? ' height="'.$height.'"' : '').' class="pointimage-image" nofullimagelink="1"}}');
}
echo $listofmarkers;
echo '</div> <!-- /.pointimage-container -->'."\n";
