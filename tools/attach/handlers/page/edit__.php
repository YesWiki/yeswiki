<?php

if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}

if ($this->HasAccess("write") && $this->HasAccess("read") && $type!='fiche_bazar' && !isset($this->page["metadatas"]["ebook-title"]) )
{
	// preview?
	if (isset($_POST["submit"]) && $_POST["submit"] == "Apercu") {
		// Rien
	}
	else {
		$uploadModal = '
	<div class="modal fade" id="UploadModal">
	  <div class="modal-dialog">
	    <div class="modal-content">
	      <div class="modal-header">
	        <button type="button" class="close" data-dismiss="modal">&times;</button>
	        <h4 class="modal-title">'._t('UPLOAD_FILE').' </h4>
	      </div>
	      <div class="modal-body">
	      	<form id="form-modal-upload" class="form-horizontal">
				<input type="hidden" value="" name="filename" class="filename" />

				<div class="control-group file-option">
					<label class="control-label">'._t('DOWNLOAD_LINK_TEXT').'</label>
					<div class="controls">
					  <input type="text" name="attach_alt" value="" class="attach_alt input-xlarge">
					</div>
			    </div>

			    <div class="control-group image-option">
					<label class="control-label">'._t('IMAGE_ALIGN').'</label>
					<div class="controls">
					  <label class="radio inline label-image-align-left" for="image-align-left">
					    <input type="radio" id="image-align-left" checked="checked" value="left" name="attach_align" class="input_radio image-align-left" /><img src="tools/attach/presentation/images/align-left.png" alt="align-left" /> '._t('LEFT').'
					  </label>
					  <label class="radio inline label-image-align-center" for="image-align-center">
					    <input type="radio" id="image-align-center" value="center" name="attach_align" class="input_radio image-align-center" /><img src="tools/attach/presentation/images/align-center.png" alt="align-center" /> '._t('CENTER').'
					  </label>
					  <label class="radio inline label-image-align-right" for="image-align-right">
					    <input type="radio" id="image-align-right" value="right" name="attach_align" class="input_radio image-align-right"><img src="tools/attach/presentation/images/align-right.png" alt="align-right" /> '._t('RIGHT').'
					  </label>
					</div>
				</div>

				<div class="control-group image-option">
					<label class="control-label">'._t('IMAGE_SIZE').'</label>
					<div class="controls">
						<label class="radio" for="image-size-small">
						    <input type="radio" id="image-size-small" value="small" name="attach_imagesize" class="input_radio image-size-small">
						    '._t('THUMBNAIL').'&nbsp;('.$this->config['image-small-width'].'x'.$this->config['image-small-height'].')
					  	</label>
					  	<label class="radio" for="image-size-medium">
						    <input type="radio" id="image-size-medium" value="medium" name="attach_imagesize" class="input_radio image-size-medium">
						    '._t('MEDIUM').'&nbsp;('.$this->config['image-medium-width'].'x'.$this->config['image-medium-height'].')
					  	</label>
						<label class="radio" for="image-size-big">
						    <input type="radio" id="image-size-big" value="big" name="attach_imagesize" class="input_radio image-size-big">
						    '._t('BIG').'&nbsp;('.$this->config['image-big-width'].'x'.$this->config['image-big-height'].')
					  	</label>
					  	<label class="radio" for="image-size-original">
						    <input type="radio" id="image-size-original" checked="checked" value="original" name="attach_imagesize" class="input_radio image-size-original">
						    '._t('ORIGINAL_SIZE').'
					  	</label>
					</div>
				</div>

				<div class="control-group image-option">
					<label class="control-label" for="attach_caption">'._t('CAPTION').'</label>
					<div class="controls">
					  <input type="text" id="attach_caption" name="attach_caption" value="" class="attach_caption input-xlarge">
					</div>
			    </div>

				<a title="'._t('SEE_THE_ADVANCED_PARAMETERS').'" data-target="#avanced-settings" data-toggle="collapse" class="accordion-trigger image-option"><span class="arrow">&#9658;</span>&nbsp;'._t('ADVANCED_PARAMETERS').'</a>

				<div id="avanced-settings" class="collapse image-option">
					<div class="control-group">
						<label class="control-label" for="attach_link">'._t('ASSOCIATED_LINK').'</label>
						<div class="controls">
						  <input type="text" id="attach_link" name="attach_link" value="" class="attach_link input-xlarge">
						</div>
				    </div>
				    <div class="control-group">
						<label class="control-label">'._t('GRAPHICAL_EFFECTS').'</label>
						<div class="controls">
						  <label class="checkbox">
						    <input type="checkbox" name="attach_css_class" value="whiteborder" />
						    '._t('WHITE_BORDER').'
						  </label>
						  <label class="checkbox">
						    <input type="checkbox" name="attach_css_class" value="lightshadow" />
						    '._t('DROP_SHADOW').'
						  </label>
						  <label class="checkbox">
						    <input type="checkbox" name="attach_css_class" value="zoom" />
						    '._t('ZOOM_HOVER').'
						  </label>
						</div>
					</div>
					<div class="control-group">
						<label class="control-label" title="'._t('ALT_INFOS').'">'._t('ALTERNATIVE_TEXT').'</label>
						<div class="controls">
						  <input type="text" name="attach_alt" value="" class="attach_alt input-xlarge">
						</div>
				    </div>
				</div>
			</form>
	      </div>
	      <div class="modal-footer">
	        <a href="#" data-dismiss="modal" role="button" class="btn btn-default btn-cancel-upload">'._t('CANCEL_THIS_UPLOAD').'</a>
			<button name="insert" class="btn btn-primary btn-insert-upload">'._t('INSERT').'</button>
	      </div>
	    </div><!-- /.modal-content -->
	  </div><!-- /.modal-dialog -->
	</div><!-- /.modal /#UploadModal -->'."\n";
		$plugin_output_new = preg_replace('/<body(.*)>/U', '<body$1>'.$uploadModal, $plugin_output_new);

		$js = '<script src="tools/attach/libs/fileuploader.js"></script>';
		$plugin_output_new = str_replace('</body>', $js.'</body>', $plugin_output_new);

		$UploadBar =   "<div id=\"attach-file-uploader\" class=\"btn-group\">
							<noscript>
								<span class=\"alert alert-danger alert-error\">"._t('ACTIVATE_JS_TO_UPLOAD_FILES').".</span>
							</noscript>
							<div class=\"qq-uploader\">
								<div class=\"qq-upload-button btn btn-default\"><i class=\"glyphicon glyphicon-upload icon-upload\"></i>&nbsp;"._t('UPLOAD_A_FILE')."</div>
								<ul class=\"qq-upload-list\"></ul>
							</div>
							<div class=\"sample-upload-list hide\">
								<li>
									<span class=\"qq-upload-file\"></span>
									<span class=\"qq-upload-spinner\"></span>
									<span class=\"qq-upload-size\"></span>
									<a class=\"qq-upload-cancel\" href=\"#\">"._t('ATTACH_CANCEL')."</a>
									<span class=\"qq-upload-failed-text\">"._t('FAILED')."</span>
								</li>
							</div>
						</div>";

		$plugin_output_new = preg_replace ( '/\<div class=\"page\"\>/',
											'<div class="page">'.$UploadBar,
											$plugin_output_new );
	}
}
