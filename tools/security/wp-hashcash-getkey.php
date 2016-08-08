<?php

require_once(realpath(dirname(__FILE__) . '/') . '/secret/wp-hashcash.lib');

header("Pragma: no-cache");
header("Expires: 0");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);

$expired = array();

$function_name = hashcash_random_string(rand(6,18));
$expired [] = $function_name;

$js = "function $function_name (){";

$type = rand(0, 3) * 0;
switch($type){
	/* Addition of n times of field value / n, + modulus:
	Time guarantee:  100 iterations or less */
	case 0:
		$eax = hashcash_random_string(rand(8,10), $expired);
		$expired [] = $eax;

		$val = hashcash_field_value();
		$inc = rand($val / 100, $val - 1);
		$n = floor($val / $inc);
		$r = $val % $inc;

		$js .= "var $eax = $inc; ";
		for($i = 0; $i < $n - 1; $i++){
			$js .= "$eax += $inc; ";
		}

		$js .= "$eax += $r; ";
		$js .= "return $eax; ";
	break;

	/* Conversion from binary:
	Time guarantee:  log(n) iterations or less */
	case 1:
		$eax = hashcash_random_string(rand(8,10), $expired);
		$expired [] = $eax;

		$ebx = hashcash_random_string(rand(8,10), $expired);
		$expired [] = $ebx;

		$ecx = hashcash_random_string(rand(8,10), $expired);
		$expired [] = $ecx;

		$val = hashcash_field_value();
		$binval = strrev(base_convert($val, 10, 2));
			$js .= "var $eax = \"$binval\"; ";
		$js .= "var $ebx = 0; ";
		$js .= "var $ecx = 0; ";
		$js .= "while($ecx < $eax.length){ ";
		$js .= "if($eax.charAt($ecx) == \"1\") { ";
		$js .= "$ebx += Math.pow(2, $ecx); ";
		$js .= "} ";
		$js .= "$ecx++; ";
		$js .= "} ";
		$js .= "return $ebx; ";

	break;

	/* Multiplication of square roots:
	Time guarantee:  constant time */
	case 2:
		$val = hashcash_field_value();
		$sqrt = floor(sqrt($val));
		$r = $val - ($sqrt * $sqrt);
		$js .= "return $sqrt * $sqrt + $r; ";
	break;

	/* Sum of random numbers to the final value:
	Time guarantee:  log(n) expected value */
	case 3:
		$val = hashcash_field_value();
		$js .= "return ";

		$i = 0;
		while($val > 0){
			if($i++ > 0)
				$js .= "+";

			$temp = rand(1, $val);
			$val -= $temp;
			$js .= $temp;
		}

		$js .= ";";
	break;
}

$js .= "} $function_name ();";

// pack bytes
function strToLongs($s) {
	$l = array();

	// pad $s to some multiple of 4
	$s = preg_split('//', $s, -1, PREG_SPLIT_NO_EMPTY);

	while(count($s) % 4 != 0){
		$s [] = ' ';
	}

	for ($i = 0; $i < ceil(count($s)/4); $i++) {
		$l[$i] = ord($s[$i*4]) + (ord($s[$i*4+1]) << 8) + (ord($s[$i*4+2]) << 16) + (ord($s[$i*4+3]) << 24);
    	}

	return $l;
}

// xor all the bytes with a random key
$key = rand(21474836, 2126008810);
$js = strToLongs($js);

for($i = 0; $i < count($js); $i++){
	$js[$i] = $js[$i] ^ $key;
}

// libs function encapsulation
$libs_name = hashcash_random_string(rand(6,18), $expired);
$expired [] = $libs_name;

$libs = "function $libs_name(){";

// write bytes to javascript, xor with key
$data_name = hashcash_random_string(rand(6,18), $expired);
$expired [] = $data_name;

$libs .= "var $data_name = new Array(" . count($js) . "); ";
for($i = 0; $i < count($js); $i++){
	$libs .= $data_name . '[' . $i . '] = ' . $js[$i] . ' ^ ' . $key .'; ';
}

// convert bytes back to string
$libs .= " var a = new Array($data_name.length); ";
$libs .= "for (var i=0; i<" . $data_name . ".length; i++) { ";
$libs .= 'a[i] = String.fromCharCode(' . $data_name .'[i] & 0xFF, ' . $data_name . '[i]>>>8 & 0xFF, ';
$libs .= $data_name . '[i]>>>16 & 0xFF, ' . $data_name . '[i]>>>24 & 0xFF); } ';
$libs .= "return eval(a.join('')); ";

// call libs function
$libs .= "} $libs_name();";

// return code
echo $libs;
?>
