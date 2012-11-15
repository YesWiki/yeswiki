<?php

if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}

if ($this->HasAccess("write") && $this->HasAccess("read"))
{
	// preview?
	if (isset($_POST["submit"]) && $_POST["submit"] == "Aperçu") {
		// Rien
	}
	else {

		$js = '<script src="tools/attach/libs/fileuploader.js"></script>';
		$plugin_output_new = str_replace('</body>', $js.'</body>', $plugin_output_new);

		$UploadBar =   "<div id=\"attach-file-uploader\" class=\"btn-group\">
							<noscript>			
								<p>Activer JavaScript pour joindre des fichiers.</p>
								<!-- or put a simple form for upload here -->
							</noscript>         
						</div>";

		$plugin_output_new = preg_replace ( '/\<div class=\"page\"\>/',
											'<div class="page">'.$UploadBar,
											$plugin_output_new );
	}
}
