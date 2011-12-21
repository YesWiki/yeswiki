<?php

if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}

if ($this->HasAccess("write") && $this->HasAccess("read"))
{
	// preview?
	if (isset($_POST["submit"]) && $_POST["submit"] == "Aperçu")
	{
		// Rien
	}
	else
	{
		
		
		$js = "<script src=\"tools/attach/libs/fileuploader.js\" type=\"text/javascript\"></script>
			<script>
			$(function(){
				var attachoverlay ;
				 
				function createUploader(){     			     
					var uploader = new qq.FileUploader({
						element: document.getElementById('attach-file-uploader'),
						action: '".$this->Href('ajaxupload')."',
						debug: false,
						onSubmit: function(id, fileName){ 
							$('#overlay-link .contentWrap').append('<h2>Joindre / Ins&eacute;rer un fichier</h2>').append($('.qq-upload-list')).append('<div id=\"overlay-form\"></div>');

							if ($('#overlay-link').hasClass('init')) {
							    $('#overlay-link').overlay().load();
							}
							else {
							    $('#overlay-link').addClass('init');
							    $('#overlay-link').overlay({ 
							      mask: {
									color: '#999',
									loadSpeed: 200,
									opacity: 0.5
								 },
							     closeOnClick: false,
							     load: true,
							     onClose: function() {
							     	$('#toolbar').append($('.qq-upload-list'));
							     	$('#overlay-link .contentWrap').empty();
								 }
							    });
							}
						},
						onComplete: function(id, fileName, responseJSON){
							if ((responseJSON.extension === 'jpg')||(responseJSON.extension === 'jpeg')||(responseJSON.extension === 'gif')||(responseJSON.extension === 'png')) {
								$('#overlay-form').load('tools/attach/presentation/templates/AttachImageDialog.html');
							}
							var actionattach = '{{attach file=\"'+responseJSON.simplefilename+'\" desc=\"'+responseJSON.simplefilename+'\" class=\"left\"}}';
							//$('#overlay-link').append(actionattach);
							wrapSelectionBis($('#body')[0], actionattach, \"\");
							//attachoverlay.close();
						},
						onCancel: function(id, fileName){
							attachoverlay.close();
							attachoverlay = NULL;
						}
					});
					$('#attach-file-uploader').appendTo('#toolbar');	
				}
				
				// in your app create uploader as soon as the DOM is ready
				// don't wait for the window to load  
				window.onload = createUploader; 
				
				$('#overlay-link .bouton_annuler').live('click', function() {
					$('#overlay-link').overlay().close(); 
					//attachoverlay.close();
					//attachoverlay = NULL;
					return false;
				});
				
			});     
			</script>";
		$plugin_output_new = str_replace('</body>', $js.'</body>', $plugin_output_new);

		$UploadBar =   "<div id=\"attach-file-uploader\">
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
