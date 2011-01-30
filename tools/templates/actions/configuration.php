<?php
if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}
$param = $this->GetParameter('param');
if (!empty($param)) {
	switch($param) {		
	  	case 'wakka_version':
	  	case 'wikini_version':
	  	case 'root_page':
	  	case 'wakka_name':
	  	case 'base_url':
	  	case 'navigation_links':
	  	case 'meta_keywords':
	  	case 'meta_description':
	  	case 'favorite_theme':
	  	case 'favorite_style':
	  	case 'favorite_squelette':
	  		echo $this->config[$param];
			break;
	  	default:
	  		break;	
	}		
			
} 
	
	
?>