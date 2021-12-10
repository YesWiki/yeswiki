<?php

$GLOBALS['translations'] = array_merge($GLOBALS['translations'], array(

// libs/attach.lib.php
'ATTACH_ACTION_ATTACH' => 'Action {{attach ...}}',
'ATTACH_PARAM_DESC_REQUIRED' => '"desc" parameter required for an image',
'ATTACH_PARAM_HEIGHT_NOT_NUMERIC' => '"height" parameter, in pixels, should be a number integer',
'ATTACH_PARAM_WIDTH_NOT_NUMERIC' => '"width" parameter, in pixels, should be a number integer',
'ATTACH_UPLOAD_FORM_FOR_FILE' => 'Upload form for file',
'ATTACH_BACK_TO_PAGE' => 'Back to the page',
'NO_RIGHT_TO_WRITE_IN_THIS_PAGE' => 'No rights to write in this page',
'ATTACH_ACTION_FULLIMAGELINK_TEXT' => "Add a link to only display the full image",


// actions/filemanager.php
'ATTACH_NO_RIGHTS_TO_ACCESS_FILEMANAGER' => 'You have no rights to access the filemanager',

// actions/player.php
'ATTACH_ACTION_PLAYER' => 'Action {{player ...}}',
'ATTACH_DOWNLOAD_THE_FILE' => 'Download the file',
'ATTACH_URL_NOT_VALID' => 'Invalid URL or not openable',
'ATTACH_PARAM_URL_REQUIRED' => '"url" parameter required',
'ATTACH_PLAYER_CAN_ONLY_OPEN_FILES_LIKE' => 'The player can only open .mp3, .flv and .mm files and your URL',
'ATTACH_NOT_LINKED_TO_GOOD_FILE_EXTENSION' => 'is not linked to those type of files',

// actions/pointimage.php
'ATTACH_ACTION_POINTIMAGE' => 'Action {{pointimage ...}}',
'ATTACH_PARAM_FILE_NOT_FOUND' => '"file" parameter required',
'ATTACH_PARAM_FILE_MUST_BE_IMAGE' => '"file" parameter must be an image (.gif,.jpg,.jpeg or .png)',
'ATTACH_DEFAULT_MARKER' => 'Default marker',
'ATTACH_ADD_MARKER' => 'Add a marker',
'ATTACH_TITLE' => 'Title',
'ATTACH_DESCRIPTION' => 'Description',
'ATTACH_CANCEL' => 'Cancel',
'ATTACH_SAVE' => 'Save',

// actions/video.php
'ATTACH_ACTION_VIDEO_PARAM_ERROR' => 'The action video must be called with parameters « id » and « serveur ». For « serveur », only values « vimeo » or « youtube » or « peertube » are allowed.',
        
// actions/pdf.php
'ATTACH_ACTION_PDF_PARAM_URL_ERROR' => 'The action pdf must be called with parameter « url » and the given url must be on the same host than the wiki(for example \'xxx.yyy.com\'), same schema (for example \'https\') and the same port if specified (for example \'8080\').',

// handler edit
'ACTIVATE_JS_TO_UPLOAD_FILES' => 'Activate JavaScript to upload files',
'UPLOAD_A_FILE' => 'Upload a file',
'UPLOAD_A_FILE_SHORT' => 'File',
'UPLOAD_FILE' => 'Upload file',
'CANCEL_THIS_UPLOAD' => 'Cancel this upload',
'INSERT' => 'Insert',
'DOWNLOAD_LINK_TEXT' => 'Download link text',
'IMAGE_ALIGN' => 'Image alignment',
'IMAGE_SIZE' => 'Image size',
'THUMBNAIL' => 'Thumbnail',
'MEDIUM' => 'Medium',
'BIG' => 'Big',
'ORIGINAL_SIZE' => 'Original size',
'CAPTION' => 'Caption text',
'SEE_THE_ADVANCED_PARAMETERS' => 'See the advanced parameters',
'ADVANCED_PARAMETERS' => 'Advanced parameters',
'ASSOCIATED_LINK' => 'Associated link',
'GRAPHICAL_EFFECTS' => 'Graphical effects',
'WHITE_BORDER' => 'White border',
'DROP_SHADOW' => 'Drop shadow',
'ZOOM_HOVER' => 'Zoom on hover',
'ALT_INFOS' => 'This texte will be displayed instead of the image, if the image is not found',
'ALTERNATIVE_TEXT' => 'Alternative text',
'LEFT' => 'Left',
'CENTER' => 'Center',
'RIGHT' => 'Right',
'FAILED' => 'Failed'

));
