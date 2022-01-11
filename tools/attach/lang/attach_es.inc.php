<?php

return [

    // libs/attach.lib.php
    'ATTACH_ACTION_ATTACH' => 'Acción {{attach ...}}',
    'ATTACH_PARAM_DESC_REQUIRED' => 'parámetro "desc" obligatorio para una imagen',
    'ATTACH_PARAM_HEIGHT_NOT_NUMERIC' => 'el parámetro "height", en pixels, sólo debe estar compuesto de números enteros',
    'ATTACH_PARAM_WIDTH_NOT_NUMERIC' => 'el parámetro "width", en pixels, sólo debe estar compuesto de números enteros',
    // 'ATTACH_UPLOAD_FORM_FOR_FILE' => 'Formulaire d\'envoi du fichier',
    'ATTACH_BACK_TO_PAGE' => 'Regreso hacia la página',
    'NO_RIGHT_TO_WRITE_IN_THIS_PAGE' => 'No tienes los derechos de acceso para escribir en esta página',
    'INVALID_REQUEST_METHOD' => 'Método de  petición invalide',
    'ERROR_MOVING_TEMPORARY_FILE' => 'Error durante el desplazamiento del archivo temporario',
    'ERROR_UPLOAD_MAX_FILESIZE' => 'Le archivo descargado excede al tamaño de upload_max_filesize, configurado en el php.ini.',
    'ERROR_MAX_FILE_SIZE' => 'EL archivo descargado excede al tamaño de MAX_FILE_SIZE, configurado en el formulario HTML.',
    'ERROR_PARTIAL_UPLOAD' => 'La descarga del archivo ha sido parcial.',
    'ERROR_NO_FILE_UPLOADED' => 'Ningun archivo ha sido descargado.',
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
    'ATTACH_NO_RIGHTS_TO_ACCESS_FILEMANAGER' => 'Necesitas los derechos de escritura de la página para acceder al administrador de los archivos atados',
    // 'FILEMANAGER_ACTION_NEED_ACCESS' => 'Seul le propriétaire de cette page peut accéder au gestionnaire des fichiers attaché',

    // actions/backgroundimage.php
    'ATTACH_ACTION_BACKGROUNDIMAGE' => 'Acción {{backgroundimage ...}}',
    'ATTACH_PARAM_FILE_OR_BGCOLOR_NOT_FOUND' => 'hay que indicar o una imagen con el parámetro "file" o un color de fondo con el parámetro "bgcolor"',

    // actions/player.php
    'ATTACH_ACTION_PLAYER' => 'Acción {{player ...}}',
    'ATTACH_DOWNLOAD_THE_FILE' => 'Descargar el archivo',
    'ATTACH_URL_NOT_VALID' => 'la dirección URL no es valida o no se puede abrir',
    'ATTACH_PARAM_URL_REQUIRED' => 'parámetro "url" obligatorio',
    'ATTACH_PLAYER_CAN_ONLY_OPEN_FILES_LIKE' => 'el player no puede leer los archivos mp3, flv y mm, y tu URL',
    'ATTACH_NOT_LINKED_TO_GOOD_FILE_EXTENSION' => 'El adjunto no està bien atado a la extension del archivo',

    // actions/pointimage.php
    'ATTACH_ACTION_POINTIMAGE' => 'Acción {{pointimage ...}}',
    'ATTACH_PARAM_FILE_NOT_FOUND' => 'parámetro "file" obligatorio',
    'ATTACH_PARAM_FILE_MUST_BE_IMAGE' => 'el parámetro "file" tiene que ser una imagen (svg,gif,jpg,jpeg,png)',
    'ATTACH_DEFAULT_MARKER' => 'Punto por defecto',
    'ATTACH_ADD_MARKER' => 'Añadir un punto',
    'ATTACH_TITLE' => 'Título',
    'ATTACH_DESCRIPTION' => 'Descripción',
    'ATTACH_CANCEL' => 'Cancelar',
    'ATTACH_SAVE' => 'Guardar',

    // actions/video.php
    'ATTACH_ACTION_VIDEO_PARAM_ERROR' => 'La acción video debe llamarse con los parámetros «id» y «serveur». Para «serveur», solo se permiten los valores «vimeo» o «youtube» o «peertube».',
    
    // actions/pdf.php
    'ATTACH_ACTION_PDF_PARAM_URL_ERROR' => 'La acción pdf debe llamarse con el parámetro «url» y la URL dada debe estar en el mismo host que el wiki (por ejemplo, \' xxx.yyy.com \'), mismo esquema (por ejemplo, \' https \') y el mismo puerto si se especifica (por ejemplo, \'8080 \'). ',
    // 'ATTACH_ACTION_DISPLAY_PDF_TEXT' => 'Afficher le pdf dans la page :',
    // 'ATTACH_ACTION_DISPLAY_PDF_LINK_TEXT' => 'sous forme de lien',
    // 'ATTACH_ACTION_DISPLAY_PDF_INCLUDED_TEXT' => 'directement inclus dans la page',

    // handler edit
    'ACTIVATE_JS_TO_UPLOAD_FILES' => 'Activar el JavaScript para adjuntar archivos',
    'UPLOAD_A_FILE' => 'Adjuntar / Insertar un archivo',
    'UPLOAD_A_FILE_SHORT' => 'Archivo',
    'UPLOAD_FILE' => 'Descargar archivo',
    'CANCEL_THIS_UPLOAD' => 'Cancelar este envio',
    'INSERT' => 'Insertar',
    'DOWNLOAD_LINK_TEXT' => 'Texto del enlace de descarga',
    'IMAGE_ALIGN' => 'Alineación de la imagen',
    'IMAGE_SIZE' => 'Tamaño de la imagen',
    'THUMBNAIL' => 'Miniatura',
    'MEDIUM' => 'Moyenne',
    'BIG' => 'Grande',
    'ORIGINAL_SIZE' => 'Tamaño original',
    'CAPTION' => 'Texto de la  viñeta',
    'SEE_THE_ADVANCED_PARAMETERS' => 'Ver los parámetros avanzados',
    'ADVANCED_PARAMETERS' => 'Parámetros avanzados',
    'ASSOCIATED_LINK' => 'Enlace asociado',
    'GRAPHICAL_EFFECTS' => 'Efectos gráficos',
    'WHITE_BORDER' => 'Borde blanco',
    'DROP_SHADOW' => 'Sombra',
    'ZOOM_HOVER' => 'Ampliación',
    'ALT_INFOS' => 'Si no se encuentra la imagen, este texto aparecera en su lugar',
    'ALTERNATIVE_TEXT' => 'Texto de sustitución',
    // 'NONE' => 'Texte en dessous',
    'LEFT' => 'Izquierda',
    'CENTER' => 'Centro',
    'RIGHT' => 'Derecha',
    'FAILED' => 'Error',

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

    // 'FILEMANAGER_ACTION_NEED_ACCESS' => 'Seul le propriétaire de cette page peut accéder au gestionnaire des fichiers attaché',
