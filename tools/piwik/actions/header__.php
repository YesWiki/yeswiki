<?php

include("tools/clickheat/config.php");

$piwik_server = '193.50.71.238';
$piwik_path = '/piwik/';

if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}

//comptatibilité PHP4...
if (version_compare(phpversion(), '5.0') < 0)
{
    eval('
    function clone($object) {
      return $object;
    }
    ');
}

$plugin_output_new = preg_replace ('/<head>/',
'<head>
<!-- Piwik -->
<!-- <a href="http://piwik.org" title="Website analytics" onclick="window.open(this.href);return(false);"> -->
<script type="text/javascript">
var pkBaseURL = (("https:" == document.location.protocol) ? "https://'.$piwik_server.$piwik_path.'" : "http://'.$piwik_server.$piwik_path.'");
document.write(unescape("%3Cscript src=\'" + pkBaseURL + "piwik.js\' type=\'text/javascript\'%3E%3C/script%3E"));
</script>
<script type="text/javascript">
<!--
piwik_action_name = \'\';
piwik_idsite = 6;
piwik_url = pkBaseURL + "piwik.php";
piwik_log(piwik_action_name, piwik_idsite, piwik_url);
//-->
</script><object>	
<noscript>
<p>Website analytics<img src="http://'.$piwik_server.$piwik_path.'piwik.php" style="border:0" alt="piwik"/>
</p>
</noscript></object></a>
<!-- /Piwik --> ',
$plugin_output_new);
?>
