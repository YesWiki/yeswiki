<?php
if (!defined("WIKINI_VERSION")){die ("acc&egrave;s direct interdit");}

if (isset($this->config["google_analytics_account"])) {
	if (version_compare(phpversion(), '5.0') < 0) {
		eval('
		if (!function_exists("clone")) {
			function clone($object) {
					return $object;
			}
		}
		');
	}/**/
	
	$plugin_output_new=preg_replace ('/<\/head>/',
		"	<script type=\"text/javascript\">

		var _gaq = _gaq || [];
		_gaq.push(['_setAccount', '".$this->config["google_analytics_account"]."']);
		_gaq.push(['_trackPageview']);

		(function() {
			var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
			ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
			var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
		})();

	</script>
</head>", $plugin_output_new);/**/
}

?>
