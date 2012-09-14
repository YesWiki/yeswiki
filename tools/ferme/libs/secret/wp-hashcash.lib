<?php

define('HASHCASH_FORM_ACTION', 'wp-comments-post.php');
define('HASHCASH_SECRET_FILE', realpath(dirname(__FILE__) . '/') . '/wp-hashcash.key');
define('HASHCASH_FORM_ID', 'newWiki');
define('HASHCASH_FORM_CLASS', 'newForm'); //class du div englobant le formulaire
define('HASHCASH_REFRESH', 60*60*4);
define('HASHCASH_IP_EXPIRE', 60*60*24*7);
define('HASHCASH_VERSION', 3.2);

// Produce random unique strings
function hashcash_random_string($l, $exclude = array()) {
	// Sanity check
	if($l < 1){
		return '';
	}
	
	$str = '';
	while(in_array($str, $exclude) || strlen($str) < $l){
		$str = '';
		while(strlen($str) < $l){
			$str .= chr(rand(65, 90) + rand(0, 1) * 32);
		}
	}
	
	return $str;
}

// looks up the secret key
function hashcash_field_value(){
	if(function_exists('file_get_contents')){
		return file_get_contents(HASHCASH_SECRET_FILE);
	} else {
		$fp = fopen(HASHCASH_SECRET_FILE, 'r');
		$data = fread($fp, @filesize(HASHCASH_SECRET_FILE));
		fclose($fp);
		return $data;
	}
}

// Returns a phrase representing the product
function hashcash_verbage(){

	$phrase = 'Protection anti-spam active';

	return $phrase;
}

?>
