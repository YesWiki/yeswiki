<?php
define ('CHEMIN', 'tools'.DIRECTORY_SEPARATOR.'mooflow'.DIRECTORY_SEPARATOR.'actions'.DIRECTORY_SEPARATOR);

$couleur = $this->GetParameter("couleur");
if (empty($couleur)) {
	$couleur='white';
}
$cheminimages = $this->GetParameter("cheminimages");
if (empty($cheminimages)) {
	exit('Il faut renseigner le chemin vers les images (cheminimages) obligatoirement.');
} else {
    $dir = opendir($cheminimages);
    while (false !== ($file = readdir($dir))) {
    	if  ($file!='.' && $file!='..' && $file!='CVS') {
    		$extension=substr($file, -4, 4);
	    	if ($extension=='.jpg' || $extension=='.gif' ||$extension=='.bmp') {
	    		$images[]=$file;
	    	}	    	
    	}
    }
}
if (count($images>0)) {
	$res= '<!-- Mooflow -->
	<script type="text/javascript">
	var myMooFlowPage = {
	
		start: function(){
	
			var mf = new MooFlow($(\'MooFlow\'), {
				stylePath: \''.CHEMIN.'presentations'.DIRECTORY_SEPARATOR.$couleur.'.css\',
				useSlider: true,
				useAutoPlay: true,
				useCaption: true,
				useResize: true,
				useWindowResize: true,
				useMouseWheel: true,
				useKeyInput: true,
				bgColor: \''.$couleur.'\',
				startIndex: 4,
				reflection: 0.5,
  				heightRatio: 0.55,
  				interval: 3000,
  				factor: 130
			});	
		}
		
	};
	
	window.addEvent(\'domready\', myMooFlowPage.start);
	</script>

	<div id="MooFlow">';
	foreach($images as $key => $value) {
	    $res .= '<div><img src="'.$cheminimages.DIRECTORY_SEPARATOR.$value.'" title="'.$value.'" alt="'.$value.'" /></div>'."\n";
	}
	$res .= '</div>
    <!-- end Mooflow -->';
    echo $res;	
} else {
	echo 'Pas d\'images trouv&eacute;es dans le r&eacute;pertoire '.$cheminimages.'.';
}

?>