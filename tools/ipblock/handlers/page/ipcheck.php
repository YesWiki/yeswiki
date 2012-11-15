<?php
echo $this->Header();
?>
<div class="page">
<?php
if (!defined('WIKINI_VERSION')) {
	die ('acc&egrave;s direct interdit');
}


if (!function_exists('iptocountry')) {
	function iptocountry($ip) {   
		$numbers = preg_split( "/\./", $ip);   
		include("tools/ipblock/ip_files/".$numbers[0].".php");
		$code=($numbers[0] * 16777216) + ($numbers[1] * 65536) + ($numbers[2] * 256) + ($numbers[3]);   
		foreach($ranges as $key => $value){
			if($key<=$code){
				if($ranges[$key][0]>=$code){$country=$ranges[$key][1];break;}
			}
		}
		if ($country==""){$country="unknown";}
		return $country;
	}

}
// Filtrage IP 
$visitorIP=$_GET["ip"];

$two_letter_country_code=iptocountry($visitorIP);
echo $two_letter_country_code;


?>
</div>
<?php
        echo $this->Footer();
?>
