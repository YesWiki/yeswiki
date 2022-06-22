<?php

return [

    // controllers/ApiController.php
    // 'ATTACH_GET_URLIMAGE_CACHE_API_HELP' => "Fournit l'url du fichier de cache pour l'image voulue\n".
    //     "Nécessite le passage du jeton anti-csrf !",
    // 'ATTACH_GET_CACHE_URLIMAGE_NO_FILE' => 'Fichier image inexistant',

    // libs/attach.lib.php
    'ATTACH_ACTION_ATTACH' => 'Acció {{attach ...}}',
    'ATTACH_PARAM_DESC_REQUIRED' => 'El paràmetre "desc" és obligatori per una imatge',
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
    // 'ATTACH_ACTION_FULLIMAGELINK_TEXT' => "Ajouter un lien pour afficher l'image seule en entier",

    // 'ATTACH_FILE_MANAGEMENT' => 'Gestion des fichiers',
    // 'ATTACH_TRASH' => 'Corbeille',
    // 'ATTACH_NO_ATTACHED_FILES' => 'Pas de fichiers attachés à la page {tag} pour l\'instant.',
    // 'ATTACH_FILENAME' => 'Nom du fichier',
    // 'ATTACH_SIZE' => 'Taille',
    // 'ATTACH_DATE_OF_MODIFICATION' => 'Date de modification',
    // 'ATTACH_RESTORE' => 'Restaurer',
    // 'ATTACH_REAL_FILENAME' => 'Nom réel du fichier : {file}',
    // 'ATTACH_DELETED_ON' => ' - Supprimé le : {date}',
    // 'ATTACH_EMPTY_TRASH' => 'Vider la corbeille',
    // 'ATTACH_EMPTY_TRASH_NOTICE' => 'les fichiers effacés à partir de la corbeille le seront définitivement.',
    // 'ATTACH_FILE_MANAGEMENT_TITLE' => 'Gestion des fichiers attachés à la page {tag}',
    // 'ATTACH_FILE_MANAGEMENT_WARNING' => 'Les fichiers effac&eacute;s sur cette page le sont d&eacute;finitivement',
    // 'ATTACH_PAGE_REVISION' => 'Version de la page',
    // 'ATTACH_FILE_REVISION' => 'Version du fichier',
    // 'ATTACH_DELETION' => 'Suppression',

    // actions/filemanager.php
    'ATTACH_NO_RIGHTS_TO_ACCESS_FILEMANAGER' => 'Cal tenir drets d\'escriptura a la pàgina per accedir a la gestió dels fitxers adjunts',
    // 'FILEMANAGER_ACTION_NEED_ACCESS' => 'Seul le propriétaire de cette page peut accéder au gestionnaire des fichiers attaché',

    // actions/backgroundimage.php
    // 'ATTACH_ACTION_BACKGROUNDIMAGE' => 'Action {{backgroundimage ...}}',
    // 'ATTACH_PARAM_FILE_OR_BGCOLOR_NOT_FOUND' => 'il faut indiquer soit une image avec le paramètre "file" ou une couleur de fond avec le paramètre "bgcolor"',

    // actions/player.php
    'ATTACH_ACTION_PLAYER' => 'Acció {{player ...}}',
    'ATTACH_DOWNLOAD_THE_FILE' => 'Descàrrega del fitxer',
    'ATTACH_URL_NOT_VALID' => 'L\'URL no és vàlid o no es pot obrir',
    'ATTACH_PARAM_URL_REQUIRED' => 'Paràmetre "url" obligatori',
    'ATTACH_PLAYER_CAN_ONLY_OPEN_FILES_LIKE' => 'El programa no pot llegir els fitxers mp3, flv o mm',
    'ATTACH_NOT_LINKED_TO_GOOD_FILE_EXTENSION' => 'L\'adjunt no està ben lincat a l\'extensió de fitxer',

    // actions/pointimage.php
    'ATTACH_ACTION_POINTIMAGE' => 'Acció {{pointimage ...}}',
    'ATTACH_PARAM_FILE_NOT_FOUND' => 'Paràmetre "file" obligatori',
    'ATTACH_PARAM_FILE_MUST_BE_IMAGE' => 'El paràmetre "file" ha de ser una imatge (gif,jpg,jpeg,png)',
    'ATTACH_DEFAULT_MARKER' => 'Marcador per defecte',
    'ATTACH_ADD_MARKER' => 'Adjunta un marcador',
    'ATTACH_TITLE' => 'Títol',
    'ATTACH_DESCRIPTION' => 'Descripció',
    'ATTACH_CANCEL' => 'Cancel·la',
    'ATTACH_SAVE' => 'Desa',
    
    // actions/video.php
    'ATTACH_ACTION_VIDEO_PARAM_ERROR' => 'El acció video s\'ha de cridar amb els paràmetres «id» i «serveur». Per a «serveur», només es permeten els valors «vimeo» o «youtube» o «peertube».',
   
    // actions/pdf.php
    'ATTACH_ACTION_PDF_PARAM_URL_ERROR' => 'El acció pdf s\'ha de cridar amb el paràmetre « url » i l\'URL indicada ha d\'estar al mateix amfitrió que la wiki (per exemple \'xxx.yyy.com \'), el mateix esquema (per exemple \'https \') i el mateix port si s\'especifica (per exemple \'8080 \'). ',
    // 'ATTACH_ACTION_DISPLAY_PDF_TEXT' => 'Afficher le pdf dans la page :',
    // 'ATTACH_ACTION_DISPLAY_PDF_LINK_TEXT' => 'sous forme de lien',
    // 'ATTACH_ACTION_DISPLAY_PDF_INCLUDED_TEXT' => 'directement inclus dans la page',

    // handler edit
    'ACTIVATE_JS_TO_UPLOAD_FILES' => 'Activa el JavaScript per adjuntar fitxers',
    'UPLOAD_A_FILE' => 'Afegir/Inserir un fitxer',
    'UPLOAD_A_FILE_SHORT' => 'Fitxer',
    'UPLOAD_FILE' => 'Carrega el fitxer',
    'CANCEL_THIS_UPLOAD' => 'Cancel·la la pujada',
    'INSERT' => 'Insereix',
    'DOWNLOAD_LINK_TEXT' => 'Text de l\'enllaç de descàrrega',
    'IMAGE_ALIGN' => 'Alineament de la imatge',
    'IMAGE_SIZE' => 'Mida de la imatge',
    'THUMBNAIL' => 'Vista en miniatura',
    'MEDIUM' => 'Mitjana',
    'BIG' => 'Gran',
    'ORIGINAL_SIZE' => 'Mida original',
    'CAPTION' => 'Títol de la imatge',
    'SEE_THE_ADVANCED_PARAMETERS' => 'Mostra els paràmetres avançats',
    'ADVANCED_PARAMETERS' => 'Paràmetres avançats',
    'ASSOCIATED_LINK' => 'Enllaç associat',
    'GRAPHICAL_EFFECTS' => 'Efectes gràfics',
    'WHITE_BORDER' => 'Vora blanca',
    'DROP_SHADOW' => 'Ombra',
    'ZOOM_HOVER' => 'Augmenta',
    'ALT_INFOS' => 'Si la imatge no es troba, aquest text apareixerà en lloc seu',
    'ALTERNATIVE_TEXT' => 'Text alternatiu',
    // 'NONE' => 'Texte en dessous',
    'LEFT' => 'Esquerra',
    'CENTER' => 'Centrat',
    'RIGHT' => 'Dreta',
    'FAILED' => 'Error'

    // handler ajaxupload
    // 'ATTACH_HANDLER_AJAXUPLOAD_FOLDER_NOT_READABLE' => 'Le dossier de téléchargement n\'est pas accessible en écriture.',
    // 'ATTACH_HANDLER_AJAXUPLOAD_NO_FILE' => 'Pas de fichiers envoyés.',
    // 'ATTACH_HANDLER_AJAXUPLOAD_EMPTY_FILE' => 'Le fichier est vide.',
    // 'ATTACH_HANDLER_AJAXUPLOAD_FILE_TOO_LARGE' => 'Le fichier est trop large.',
    // 'ATTACH_HANDLER_AJAXUPLOAD_AUTHORIZED_EXT' => 'Le fichier n\'a pas une extension autorisée, voici les autorisées : {ext}.',
    // 'ATTACH_HANDLER_AJAXUPLOAD_ERROR' => 'Impossible de sauver le fichier. L\'upload a été annulé ou le serveur a planté...',

    // edit config action
    // 'EDIT_CONFIG_HINT_ATTACH-VIDEO-CONFIG[DEFAULT_VIDEO_SERVICE]' => 'Service de vidéo par défaut (peertube, youtube ou vimeo)',
    // 'EDIT_CONFIG_HINT_ATTACH-VIDEO-CONFIG[DEFAULT_PEERTUBE_INSTANCE]' => 'Adresse du serveur peertube par défaut',
    // 'EDIT_CONFIG_GROUP_ATTACH' => 'Insertion de médias (images, vidéos)',
];
