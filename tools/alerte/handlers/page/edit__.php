<?php
if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}
	$plugin_output_new = str_replace ("</body>", "
<script defer language='javascript'>
	var showPopup = 1
	
	window.onbeforeunload = exitPopup;
	
	$('input[name|=\"submit\"]').live('click', function() {
		showPopup = 0;
	});

	function exitPopup() {
	if (showPopup) {
			return('Cette page demande de confirmer sa fermeture des donn&acute;es saisies pourraient ne pas &ecirc;tre enregistr&eacute;es.');		
		}
	}
</script>

</body>", $plugin_output_new);

?>
