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
			if (substr(WIKINI_VERSION,2,1)<=4) {
				$plugin_output_new = preg_replace ('/\<textarea onkeydown/',
					'<textarea class="tinymce" onkeydown',
					$plugin_output_new);
			}
			else  {
				if (substr(WIKINI_VERSION,2,1)>=5) {
					$plugin_output_new = preg_replace ('/\<textarea id="body" name="body" cols="60" rows="40" wrap="soft"/',
					'<textarea id="body" class="tinymce" name="body" cols="60" rows="40" ',
					$plugin_output_new);			
				}
			}
			
			preg_match("/<textarea id=\"body\".*>(?P<text>.*)<\/textarea>/isU", $plugin_output_new, $textarea_value);
			$textarea_value["text"] = preg_replace ('/(\{\{.*?\}\})/msU', '##$1##', $textarea_value["text"]);
			$textarea_value_html = $this->Format(trim($textarea_value["text"]));
			
			//on reformate certaines balises pour meilleure compatibilité
			$patterns[] = '/<span class="del">(.*)<\/span>/Uis';
			$replacements[] = "<span style=\"text-decoration: line-through;\">$1</span>";
			$textarea_value_html = preg_replace($patterns, $replacements, $textarea_value_html);
			
			//on remplace le contenu de la balise textarea par le contenu formaté en html
			$plugin_output_new = str_replace($textarea_value["text"], $textarea_value_html, $plugin_output_new);

			
			$plugin_output_new = str_replace('</body>',
				'<!-- Load TinyMCE -->
				<script type="text/javascript" src="tools/wysiwyg/libs/yeswiky.js"></script>
				<script type="text/javascript" src="tools/wysiwyg/libs/tiny_mce/jquery.tinymce.js"></script>
				<script type="text/javascript">
					$().ready(function() {
						//on change le nom des boutons submit, pour que le bouton save de l editeur fonctionne
						$(\'input[name="submit"]\').attr("name","submit-edit");
						$("#ACEditor").bind("submit", function() { $(\'input[name="submit-edit"]\').attr("name","submit"); });
						
						var tiny = $("textarea.tinymce").tinymce({
							// Location of TinyMCE script
							script_url : "tools/wysiwyg/libs/tiny_mce/tiny_mce.js",

							// General options
							theme : "advanced",
							language : "fr",
							width : "100%",
							content_css : "tools/templates/themes/'.$this->config['favorite_theme'].'/styles/'.$this->config['favorite_style'].'",
							plugins : "save,autoresize",
							//plugins : "save,autoresize,pagebreak,style,layer,table,advhr,advimage,advlink,emotions,iespell,inlinepopups,insertdatetime,preview,media,paste,directionality,noneditable,visualchars,nonbreaking,xhtmlxtras",

							// Theme options
							theme_advanced_buttons1 : "save,|,bold,italic,underline,strikethrough,|,formatselect,|,link,unlink,bullist,numlist,outdent,indent,hr,cleanup,|,cut,copy,paste,pastetext,pasteword,|,undo,redo,|,code",
							theme_advanced_buttons2 : "",
							theme_advanced_buttons3 : "",
							theme_advanced_buttons4 : "",

							theme_advanced_toolbar_location : "top",
							theme_advanced_toolbar_align : "left",
							theme_advanced_statusbar_location : "none",
							theme_advanced_resizing : true,
							theme_advanced_blockformats : "h1,h2,h3,h4,h5,h6,blockquote,code",
							
							setup : function(ed) {
								  ed.onKeyPress.add(function(ed, e) {
									  $(\'#idwikisyntax\').empty().append(Wiky.toWiki($(\'#body\').html()));
								  });
							   }


						});
						$("#body_save").bind("click", function() {$("#ACEditor").submit();return false;});
						
						$("#ACEditor").after(\'<textarea id="idwikisyntax" style="position:absolute;top:0;right:0;width:300px;height:600px;"></textarea>\');
						$(\'#idwikisyntax\').append(Wiky.toWiki($(\'#body\').text()));
					});
				</script>
				<!-- /TinyMCE -->
				</body>', $plugin_output_new);

		
	}
}


?>
