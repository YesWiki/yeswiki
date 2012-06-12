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
		
		
		$js = "<script src=\"tools/attach/libs/fileuploader.js\"></script>
			<script>
			$(function(){
				var attachoverlay ;
				 
				function createUploader(){     			     
					var uploader = new qq.FileUploader({
						element: document.getElementById('attach-file-uploader'),
						action: '".$this->Href('ajaxupload')."',
						debug: false,
						onSubmit: function(id, fileName){
							if (!$('#overlay-link .contentWrap h2.upload-title').length) {
								$('#overlay-link .contentWrap').prepend('<h2 class=\"upload-title\">Joindre / Ins&eacute;rer un fichier</h2>').append($('.qq-upload-list'))
							}

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
									$('.qq-upload-list').empty();
							     	$('#toolbar').append($('.qq-upload-list'));
							     	$('#overlay-link .contentWrap').empty();
								 }
							    });
							}
						},
						onComplete: function(id, fileName, responseJSON){
							
							// pour la période de debug, on enleve le cache AJAX
							$.ajaxSetup( {
								cache : false
							});
							
							$('a.show_advanced').live('click',function() {
								if ($(this).hasClass('current')) {
									$(this).removeClass('current');
									$(this).find('.arrow').html('&#9658;');
									$(this).parent().next(\"div.advanced\").hide();
								} else { 
									$(this).addClass('current');
									$(this).find('.arrow').html('&#9660;');
									$(this).parent().next(\"div.advanced\").show();
								}
							});

						    var lastfileuploaded = $('.qq-upload-list li.qq-upload-success .qq-upload-file:contains('+fileName+')');
							lastfileuploaded.parent('.qq-upload-success').append('<div class=\"overlay-form\"></div>');
							var filesize = lastfileuploaded.siblings('.qq-upload-size').text();
							if (filesize !== '') {
								filesize = ' ('+filesize+')';
							}
							
							var overlayform = lastfileuploaded.siblings('.overlay-form');
							if ((responseJSON.extension === 'jpg')||(responseJSON.extension === 'jpeg')||(responseJSON.extension === 'gif')||(responseJSON.extension === 'png')) {
								overlayform.load('tools/attach/presentation/templates/AttachImageDialog.html',function() {
									$(this).find('.filename').val(responseJSON.simplefilename);
									$(this).find('.attach_alt').val('image '+responseJSON.simplefilename+filesize);
									$(this).find('input[type=\"radio\"], input[type=\"checkbox\"]').each(function() {
										var name = $(this).attr('name')+$(this).attr('value')+id;
										$(this).attr('id',name).next().attr('for',name);
									});
								});	
							}
							else {
								overlayform.load('tools/attach/presentation/templates/AttachFileDialog.html',function() {
									$(this).find('.filename').val(responseJSON.simplefilename);
									$(this).find('.attach_alt').val('Télécharger le fichier '+responseJSON.simplefilename+filesize);
									$(this).find('input[type=\"radio\"], input[type=\"checkbox\"]').each(function() {
										var name = $(this).attr('name')+$(this).attr('value')+id;
										$(this).attr('id',name).next().attr('for',name);
									});
								});	
							}
							
							
						},
						
						// a l'annulation du téléchargement en cours
						onCancel: function(id, fileName){
							// on supprime l'element dans la liste sinon 
							$('.qq-upload-list li.qq-upload-success .qq-upload-file:contains('+fileName+')').remove();
							
							// on ferme l'overlay si c'est le dernier
							if ($('.qq-upload-list li').length > 0) {
								$('#overlay-link').overlay().close(); 
								return true;
							}
						}
					});
					
					// on déplace la liste des fichiers dans la barre d'outils
					$('#attach-file-uploader').appendTo('#toolbar');	
				}
				createUploader(); 
				
				// On veut fermer l'overlay
				$('#overlay-link a.close').click(function(event) {
					if (confirm(\"Voulez-vous vraiment annuler les transferts non-insérés?\")) {
						// TODO: supprimer les fichiers associés
						return true;
					} 
					else {
						event.preventDefault();
						return false;
					}
				});
				
				// On annule l'insertion de fichier
				$('#overlay-link .bouton_annuler').live('click', function() {
					
					// TODO: supprimer le fichier associé
				
					// on supprime l'element dans la liste
					$(this).parents('li.qq-upload-success').slideUp(function() {
						$(this).remove();
						// on ferme l'overlay si c'est le dernier
						if ($('.qq-upload-list li').length == 0) { 	
							$('#overlay-link').overlay().close(); 
						}
					});

					return false;
					
				});
				
				// on insère l'action attach bien paramétrée!
				$('#overlay-link .bouton_sauver').live('click', function() {
					
					var nomfich = $(this).parents('li.qq-upload-success').find('.filename').val();
					var description = $(this).parents('li.qq-upload-success').find('.attach_alt').val();
					
					var actionattach = '{{attach file=\"'+nomfich+'\" desc=\"'+description+'\"';
					
					var imagesize = $(this).parents('li.qq-upload-success').find('input[name=attach_imagesize]:checked').val();
					if (typeof imagesize != 'undefined') {
						actionattach += ' size=\"'+imagesize+'\"';
					}
					
					var imagealign = $(this).parents('li.qq-upload-success').find('input[name=attach_align]:checked').val();
					if (typeof imagealign != 'undefined') {
						actionattach += ' class=\"'+imagealign;
						if (typeof imagesize != 'undefined') {actionattach += ' '+imagesize;}
						$(this).parents('li.qq-upload-success').find('input[name=\"attach_css_class\"]:checked').each(function() {actionattach += ' '+$(this).val();});
						actionattach += '\"';
					}
					
					var imagelink = $(this).parents('li.qq-upload-success').find('.attach_link').val();
					if (typeof imagelink != 'undefined' && imagelink!=='') {
						actionattach += ' link=\"'+imagelink+'\"';
					}
					
					var imagecaption = $(this).parents('li.qq-upload-success').find('.attach_caption').val();
					if (typeof imagecaption != 'undefined' && imagecaption!=='') {
						actionattach += ' caption=\"'+imagecaption+'\"';
					}
					
					actionattach += '}}';
					
					// on ajoute le code de l'action attach au mode édition
					wrapSelectionBis($('#body')[0], actionattach, \"\");
					
					// on supprime l'element dans la liste
					$(this).parents('li.qq-upload-success').slideUp(function() {
						$(this).remove();
						// on ferme l'overlay si c'est le dernier	
						if ($('.qq-upload-list li').length == 0) { 		
							$('#overlay-link').overlay().close(); 
						}
					});
					
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
