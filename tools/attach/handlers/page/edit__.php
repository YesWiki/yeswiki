<?php

if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

if ($this->HasAccess("write") && $this->HasAccess("read") && $type!='fiche_bazar' && !isset($this->page["metadatas"]["ebook-title"])) {
    // preview?
    if (isset($_POST["submit"]) && $_POST["submit"] == "Apercu") {
        // Rien
    } else {
        $uploadModal = '
	<div class="modal fade" id="UploadModal">
	  <div class="modal-dialog modal-lg">
	    <div class="modal-content">
	      <div class="modal-header">
	        <button type="button" class="close" data-dismiss="modal">&times;</button>
	        <h4 class="modal-title">'._t('UPLOAD_FILE').' </h4>
	      </div>
	      <div class="modal-body">
	      	<form id="form-modal-upload" class="form-horizontal">
				<input type="hidden" value="" name="filename" class="filename" />

				<div class="form-group file-option">
					<label class="control-label col-sm-3">'._t('DOWNLOAD_LINK_TEXT').'</label>
					<div class="controls col-sm-9">
					  <input type="text" name="attach_link_text" value="" class="attach_link_text form-control">
					</div>
			    </div>

			    <div class="form-group image-option">
					<label class="control-label col-sm-3">'._t('IMAGE_ALIGN').'</label>
					<div class="controls col-sm-9">
            <div class="radio inline-container">
  					  <label class="label-image-align-left" for="image-align-left">
                        <input type="radio" id="image-align-left" checked="checked" value="left" name="attach_align" class="input_radio image-align-left" />
                        <span></span>
                          <img src="tools/attach/presentation/images/align-left.png" alt="align-left" /> '._t('LEFT').'
                      </label>
  					  <label class="label-image-align-center" for="image-align-center">
                        <input type="radio" id="image-align-center" value="center" name="attach_align" class="input_radio image-align-center" />
                        <span></span>
                          <img src="tools/attach/presentation/images/align-center.png" alt="align-center" /> '._t('CENTER').'
                      </label>
  					  <label class="label-image-align-right" for="image-align-right">
                        <input type="radio" id="image-align-right" value="right" name="attach_align" class="input_radio image-align-right">
                        <span></span>
                          <img src="tools/attach/presentation/images/align-right.png" alt="align-right" />
                          '._t('RIGHT').'
                      </label>
  					</div>
          </div>
				</div>

				<div class="form-group image-option">
					<label class="control-label col-sm-3">'._t('IMAGE_SIZE').'</label>
					<div class="controls col-sm-9">
            <div class="radio inline-container">
  						<label for="image-size-small">
                          <input type="radio" id="image-size-small" value="small" name="attach_imagesize" class="input_radio image-size-small">
                          <span></span>
                              '._t('THUMBNAIL').'&nbsp;('.$this->config['image-small-width'].'x'.$this->config['image-small-height'].')
  					  	</label>
  					  	<label for="image-size-medium">
                            <input type="radio" id="image-size-medium" value="medium" name="attach_imagesize" class="input_radio image-size-medium">
                            <span></span>
                            '._t('MEDIUM').'&nbsp;('.$this->config['image-medium-width'].'x'.$this->config['image-medium-height'].')
  					  	</label>
  						<label for="image-size-big">
                          <input type="radio" id="image-size-big" value="big" name="attach_imagesize" class="input_radio image-size-big">
                          <span></span>
                            '._t('BIG').'&nbsp;('.$this->config['image-big-width'].'x'.$this->config['image-big-height'].')
  					  	</label>
  					  	<label for="image-size-original">
                            <input type="radio" id="image-size-original" checked="checked" value="original" name="attach_imagesize" class="input_radio image-size-original">
                            <span></span>
                            '._t('ORIGINAL_SIZE').'
  					  	</label>
              </div>
					</div>
				</div>

				<div class="form-group image-option">
					<label class="control-label col-sm-3" for="attach_caption">'._t('CAPTION').'</label>
					<div class="controls col-sm-9">
					  <input type="text" id="attach_caption" name="attach_caption" value="" class="attach_caption form-control">
					</div>
			    </div>

				<a title="'._t('SEE_THE_ADVANCED_PARAMETERS').'" href="#avanced-settings" data-toggle="collapse" class="btn btn-default">'._t('ADVANCED_PARAMETERS').'</a>
				<div id="avanced-settings" class="collapse">
					<hr>
          <div class="form-group">
						<label class="control-label col-sm-3" for="attach_link">'._t('ASSOCIATED_LINK').'</label>
						<div class="controls col-sm-9">
						  <input type="text" id="attach_link" name="attach_link" value="" class="attach_link form-control">
						</div>
				    </div>
				    <div class="form-group">
						<label class="control-label col-sm-3">'._t('GRAPHICAL_EFFECTS').'</label>
						<div class="controls col-sm-9">
                            <div class="checkbox inline-container">
                                <label>
                                <input type="checkbox" name="attach_css_class" value="whiteborder" />
                                <span></span>
                                    '._t('WHITE_BORDER').'
                                </label>
                                <label>
                                <input type="checkbox" name="attach_css_class" value="lightshadow" />
                                <span></span>
                                    '._t('DROP_SHADOW').'
                                </label>
                                <label>
                                <input type="checkbox" name="attach_css_class" value="zoom" />
                                <span></span>
                                    '._t('ZOOM_HOVER').'
                                </label>
                            </div>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-sm-3" title="'._t('ALT_INFOS').'">'._t('ALTERNATIVE_TEXT').'</label>
						<div class="controls col-sm-9">
						  <input type="text" name="attach_alt" value="" class="attach_alt form-control">
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
        $UploadBar =   "<div id=\"attach-file-uploader\" class=\"btn-group\">
					<noscript>
						<span class=\"alert alert-danger alert-error\">"._t('ACTIVATE_JS_TO_UPLOAD_FILES').".</span>
					</noscript>
					<div class=\"qq-uploader\">
						<div class=\"qq-upload-button btn btn-default\" title=\""._t('UPLOAD_A_FILE')."\"><i class=\"fa fa-upload icon-upload\"></i>&nbsp;"._t('UPLOAD_A_FILE_SHORT')."</div>
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
        $plugin_output_new = str_replace('<form id="ACEditor"', $UploadBar.$uploadModal.'<form id="ACEditor"', $plugin_output_new);
    }
}
