<?php

if (!defined('WIKINI_VERSION')) {
    die ('acc&egrave;s direct interdit');
}

// ID : Indonesie
// MY : Malaisie
$pays_bloque = array("ID", "MY");


if ($this->HasAccess("write") && $this->HasAccess("read")) // Les admins sont autorises
{
    // preview?
    if (isset($_POST["submit"]) && $_POST["submit"] == "Sauver")
    {

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
        $visitorIP=$_SERVER["REMOTE_ADDR"];

        $two_letter_country_code=iptocountry($visitorIP);

    	if (in_array($two_letter_country_code,$pays_bloque)) { 
            	$this->SetMessage("Vous ne pouvez pas modifier le contenu de ce Wiki depuis ce poste de travail");
            	$this->Redirect($this->href());
    	}

    }

}

?>
