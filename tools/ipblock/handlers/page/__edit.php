<?php


if (!defined('WIKINI_VERSION')) {
    die ('acc&egrave;s direct interdit');
}


if ($this->HasAccess("write") && $this->HasAccess("read")) // Les admins sont autorises
{
    // preview?
    if ($_POST["submit"] == "Sauver")
    {

        if (!function_exists('iptocountry')) {
            function iptocountry($ip) {   
                $numbers = preg_split( "/\./", $ip);   
                include("ip_files/".$numbers[0].".php");
                $code=($numbers[0] * 16777216) + ($numbers[1] * 65536) + ($numbers[2] * 256) + ($numbers[3]);   
                foreach($ranges as $key => $value){
                    if($key<=$code){
                        if($ranges[$key][0]>=$code){$country=$ranges[$key][1];break;}
                    }
                }
                if ($country==""){$country="unkown";}
                return $country;
            }

        }
        // Filtrage IP 
        $visitorIP=$_SERVER["REMOTE_ADDR"];

        $two_letter_country_code=iptocountry($visitorIP);

        $this->SetMessage($two_letter_country_code);
        //$this->Redirect($this->href());
    }

}

?>
