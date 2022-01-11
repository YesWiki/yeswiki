<?php

return [

    // libs/attach.lib.php
    'ATTACH_ACTION_ATTACH' => 'Action {{attach ...}}',
    'ATTACH_PARAM_DESC_REQUIRED' => '"desc" parameter required for an image',
    'ATTACH_PARAM_HEIGHT_NOT_NUMERIC' => '"height" parameter, in pixels, should be a number integer',
    'ATTACH_PARAM_WIDTH_NOT_NUMERIC' => '"width" parameter, in pixels, should be a number integer',
    'ATTACH_UPLOAD_FORM_FOR_FILE' => 'Upload form for file',
    'ATTACH_BACK_TO_PAGE' => 'Back to the page',
    'NO_RIGHT_TO_WRITE_IN_THIS_PAGE' => 'No rights to write in this page',
    // 'INVALID_REQUEST_METHOD' => 'M&eacute;thode de requ&egrave;te invalide',
    // 'ERROR_MOVING_TEMPORARY_FILE' => 'Erreur lors du d&eacute;placement du fichier temporaire',
    // 'ERROR_UPLOAD_MAX_FILESIZE' => 'Le fichier t&eacute;l&eacute;charg&eacute; exc&ecirc;de la taille de upload_max_filesize, configur&eacute; dans le php.ini.',
    // 'ERROR_MAX_FILE_SIZE' => 'Le fichier t&eacute;l&eacute;charg&eacute; exc&ecirc;de la taille de MAX_FILE_SIZE, qui a &eacute;t&eacute; sp&eacute;cifi&eacute;e dans le formulaire HTML.',
    // 'ERROR_PARTIAL_UPLOAD' => 'Le fichier n\'a &eacute;t&eacute; que partiellement t&eacute;l&eacute;charg&eacute;.',
    // 'ERROR_NO_FILE_UPLOADED' => 'Aucun fichier n\'a &eacute;t&eacute; t&eacute;l&eacute;charg&eacute;.',
    // 'ERROR_NOT_AUTHORIZED_EXTENSION' => 'Le fichier n\'a pas une extension autorisée, voici celles que la configuration autorise : ',
    'ATTACH_ACTION_FULLIMAGELINK_TEXT' => "Add a link to only display the full image",


    // actions/filemanager.php
    'ATTACH_NO_RIGHTS_TO_ACCESS_FILEMANAGER' => 'You have no rights to access the filemanager',

    // actions/backgroundimage.php
    // 'ATTACH_ACTION_BACKGROUNDIMAGE' => 'Action {{backgroundimage ...}}',
    // 'ATTACH_PARAM_FILE_OR_BGCOLOR_NOT_FOUND' => 'il faut indiquer soit une image avec le paramètre "file" ou une couleur de fond avec le paramètre "bgcolor"',

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
    // 'ATTACH_ACTION_DISPLAY_PDF_TEXT' => 'Afficher le pdf dans la page :',
    // 'ATTACH_ACTION_DISPLAY_PDF_LINK_TEXT' => 'sous forme de lien',
    // 'ATTACH_ACTION_DISPLAY_PDF_INCLUDED_TEXT' => 'directement inclus dans la page',

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
    // 'NONE' => 'Texte en dessous',
    'LEFT' => 'Left',
    'CENTER' => 'Center',
    'RIGHT' => 'Right',
    'FAILED' => 'Failed',
    
    // edit config action
    // 'EDIT_CONFIG_HINT_ATTACH-VIDEO-CONFIG[DEFAULT_VIDEO_SERVICE]' => 'Service de vidéo par défaut (peertube, youtube ou vimeo)',
    // 'EDIT_CONFIG_HINT_ATTACH-VIDEO-CONFIG[DEFAULT_PEERTUBE_INSTANCE]' => 'Adresse du serveur peertube par défaut',
    // 'EDIT_CONFIG_GROUP_ATTACH' => 'Insertion de médias (images, vidéos)',
];
