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
		$UploadBar = "<div id=\"attach-file-uploader\">		
						<noscript>			
							<p>Activer JavaScript pour joindre des fichiers.</p>
							<!-- or put a simple form for upload here -->
						</noscript>         
					</div>

					<script src=\"tools/attach/libs/fileuploader.js\" type=\"text/javascript\"></script>
					<script>        
						function createUploader(){            
							var uploader = new qq.FileUploader({
								element: document.getElementById('attach-file-uploader'),
								action: '".$this->Href('ajaxupload')."',
								debug: true,
								onSubmit: function(id, fileName){ 
									$('.qq-upload-list').appendTo('#overlay-link');
									$('#overlay-link').prepend('<h2>Joindre / Ins&eacute;rer un fichier</h2>').overlay({
										mask: {
											color: '#999',
											loadSpeed: 200,
											opacity: 0.5
										},
										closeOnClick: false,
										load: true
									});
								},
								onComplete: function(id, fileName, responseJSON){
									if ((responseJSON.extension === 'jpg')||(responseJSON.extension === 'jpeg')||(responseJSON.extension === 'gif')||(responseJSON.extension === 'png')) {
										$('#overlay-link').append('<label for=\"attach_alt\">Texte alternatif</label>'+
										'<input type=\"text\" value=\"\" name=\"attach_alt\" id=\"attach_alt\" class=\"input_text\" /><br />'+
										'<label for=\"attach_link\">Cible du lien</label>'+
										'<input type=\"text\" value=\"\" name=\"attach_link\" class=\"input_text\"><br />'+
										'<label for=\"attach_align\">Alignement</label>'+
										'<input type=\"radio\" value=\"none\" id=\"image-align-none\" name=\"attach_align\">'+
										'<label for=\"image-align-none\">Aucun</label>'+
										'<input type=\"radio\" checked=\"checked\" value=\"left\" id=\"image-align-left\" name=\"attach_align\">'+
										'<label for=\"image-align-left\">Gauche</label>'+
										'<input type=\"radio\" value=\"center\" id=\"image-align-center\" name=\"attach_align\">'+
										'<label for=\"image-align-center\">Centre</label>'+
										'<input type=\"radio\" value=\"right\" id=\"image-align-right\" name=\"attach_align\">'+
										'<label for=\"image-align-right\">Droite</label><br />'+
										'<label for=\"attach_imagesize\">Taille</label>'+
										'<input type=\"radio\" value=\"small\" id=\"image-size-small\" name=\"attach_imagesize\">'+
										'<label for=\"image-size-small\">Miniature<br />(150&nbsp;×&nbsp;150)</label>'+
										'<input type=\"radio\" checked=\"checked\" value=\"medium\" id=\"image-size-medium\" name=\"attach_imagesize\">'+
										'<label for=\"image-size-medium\">Moyenne<br />(234&nbsp;×&nbsp;300)</label>'+
										'<input type=\"radio\" value=\"big\" id=\"image-size-large\" name=\"attach_imagesize\">'+
										'<label for=\"image-size-big\">Large<br />(500&nbsp;x&nbsp;500)</label>'+
										'<input type=\"radio\" value=\"original\" id=\"image-size-original\" name=\"attach_imagesize\">'+
										'<label for=\"image-size-original\">Taille originale<br />(780&nbsp;×&nbsp;1000)</label><br />');
									}
									var actionattach = '{{attach file=\"'+responseJSON.simplefilename+'\" desc=\"'+responseJSON.simplefilename+'\" class=\"left\"}}';
									//$('#overlay-link').append(actionattach);
									wrapSelectionBis($('#body')[0], actionattach, \"\");
									$('#overlay-link').close();
								},
								onCancel: function(id, fileName){
									$('#overlay-link').close();
								}
							});
							$('#attach-file-uploader').appendTo('#toolbar');			        
						}
						
						// in your app create uploader as soon as the DOM is ready
						// don't wait for the window to load  
						window.onload = createUploader;     
					</script>
					<link href=\"tools/attach/presentation/styles/fileuploader.css\" rel=\"stylesheet\" type=\"text/css\">	
					
					";
		
		$plugin_output_new = preg_replace ( '/\<div class=\"page\"\>/',
											'<div class="page">'.$UploadBar,
											$plugin_output_new );
	}
}
