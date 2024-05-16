<?php

return [
    // controllers/ApiController.php
    'ATTACH_GET_URLIMAGE_CACHE_API_HELP' => "Fournit l'url du fichier de cache pour l'image voulue\n" .
        'Nécessite le passage du jeton anti-csrf !',
    'ATTACH_GET_CACHE_URLIMAGE_NO_FILE' => 'Fichier image inexistant',

    // libs/attach.lib.php
    'ATTACH_ACTION_ATTACH' => 'Action {{attach ...}}',
    'ATTACH_PARAM_DESC_REQUIRED' => 'param&egrave;tre "desc" obligatoire pour une image',
    'ATTACH_PARAM_HEIGHT_NOT_NUMERIC' => 'le param&egrave;tre "height", en pixels, doit &ecirc;tre uniquement compos&eacute; de chiffres entiers',
    'ATTACH_PARAM_WIDTH_NOT_NUMERIC' => 'le param&egrave;tre "width", en pixels, doit &ecirc;tre uniquement compos&eacute; de chiffres entiers',
    'ATTACH_UPLOAD_FORM_FOR_FILE' => 'Formulaire d\'envoi du fichier',
    'ATTACH_BACK_TO_PAGE' => 'Retour &agrave; la page',
    'NO_RIGHT_TO_WRITE_IN_THIS_PAGE' => 'Vous n\'avez pas l\'accès en &eacute;criture &agrave; cette page',
    'INVALID_REQUEST_METHOD' => 'M&eacute;thode de requ&egrave;te invalide',
    'ERROR_MOVING_TEMPORARY_FILE' => 'Erreur lors du d&eacute;placement du fichier temporaire',
    'ERROR_UPLOAD_MAX_FILESIZE' => 'Le fichier t&eacute;l&eacute;charg&eacute; exc&ecirc;de la taille de upload_max_filesize, configur&eacute; dans le php.ini.',
    'ERROR_MAX_FILE_SIZE' => 'Le fichier t&eacute;l&eacute;charg&eacute; exc&ecirc;de la taille de MAX_FILE_SIZE, qui a &eacute;t&eacute; sp&eacute;cifi&eacute;e dans le formulaire HTML.',
    'ERROR_PARTIAL_UPLOAD' => 'Le fichier n\'a &eacute;t&eacute; que partiellement t&eacute;l&eacute;charg&eacute;.',
    'ERROR_NO_FILE_UPLOADED' => 'Aucun fichier n\'a &eacute;t&eacute; t&eacute;l&eacute;charg&eacute;.',
    'ERROR_NOT_AUTHORIZED_EXTENSION' => 'Le fichier n\'a pas une extension autorisée, voici celles que la configuration autorise : ',
    'ATTACH_ACTION_FULLIMAGELINK_TEXT' => "Permettre de cliquer sur l'image pour l'afficher en grand",

    'ATTACH_FILE_MANAGEMENT' => 'Gestion des fichiers',
    'ATTACH_TRASH' => 'Corbeille',
    'ATTACH_NO_ATTACHED_FILES' => 'Pas de fichiers attachés à la page {tag} pour l\'instant.',
    'ATTACH_FILENAME' => 'Nom du fichier',
    'ATTACH_SIZE' => 'Taille',
    'ATTACH_DATE_OF_MODIFICATION' => 'Date de modification',
    'ATTACH_RESTORE' => 'Restaurer',
    'ATTACH_REAL_FILENAME' => 'Nom réel du fichier : {file}',
    'ATTACH_DELETED_ON' => ' - Supprimé le : {date}',
    'ATTACH_EMPTY_TRASH' => 'Vider la corbeille',
    'ATTACH_EMPTY_TRASH_NOTICE' => 'les fichiers effacés à partir de la corbeille le seront définitivement.',
    'ATTACH_FILE_MANAGEMENT_TITLE' => 'Gestion des fichiers attachés à la page {tag}',
    'ATTACH_FILE_MANAGEMENT_WARNING' => 'Les fichiers effac&eacute;s sur cette page le sont d&eacute;finitivement',
    'ATTACH_PAGE_REVISION' => 'Version de la page',
    'ATTACH_FILE_REVISION' => 'Version du fichier',
    'ATTACH_DELETION' => 'Suppression',

    // actions/filemanager.php
    'ATTACH_NO_RIGHTS_TO_ACCESS_FILEMANAGER' => 'Il faut avoir acc&egrave; en &eacute;criture &agrave; la page pour acc&eacute;der au gestionnaire des fichiers attach&eacute;s',
    'FILEMANAGER_ACTION_NEED_ACCESS' => 'Seul le propriétaire de cette page peut accéder au gestionnaire des fichiers attaché',

    // actions/backgroundimage.php
    'ATTACH_ACTION_BACKGROUNDIMAGE' => 'Action {{backgroundimage ...}}',
    'ATTACH_PARAM_FILE_OR_BGCOLOR_NOT_FOUND' => 'il faut indiquer soit une image avec le paramètre "file" ou une couleur de fond avec le paramètre "bgcolor"',

    // actions/player.php
    'ATTACH_ACTION_PLAYER' => 'Action {{player ...}}',
    'ATTACH_DOWNLOAD_THE_FILE' => 'T&eacute;l&eacute;charger le fichier',
    'ATTACH_URL_NOT_VALID' => 'l\'URL n\'est pas valide ou ne peut pas &ecirc;tre ouverte',
    'ATTACH_PARAM_URL_REQUIRED' => 'param&egrave;tre "url" obligatoire',
    'ATTACH_PLAYER_CAN_ONLY_OPEN_FILES_LIKE' => 'le player ne peut que lire les fichiers mp3, flv et mm, et votre URL',
    'ATTACH_NOT_LINKED_TO_GOOD_FILE_EXTENSION' => 'ne pointe pas sur ces types de fichiers',

    // actions/pointimage.php
    'ATTACH_ACTION_POINTIMAGE' => 'Action {{pointimage ...}}',
    'ATTACH_PARAM_FILE_NOT_FOUND' => 'param&egrave;tre "file" obligatoire',
    'ATTACH_PARAM_FILE_MUST_BE_IMAGE' => 'le param&egrave;tre "file" doit &ecirc;tre une image (svg,gif,jpg,jpeg,png)',
    'ATTACH_DEFAULT_MARKER' => 'Point par d&eacute;faut',
    'ATTACH_ADD_MARKER' => 'Ajouter un point',
    'ATTACH_TITLE' => 'Titre',
    'ATTACH_DESCRIPTION' => 'Description',
    'ATTACH_CANCEL' => 'Annuler',
    'ATTACH_SAVE' => 'Sauver',

    // actions/video.php
    'ATTACH_ACTION_VIDEO_PARAM_ERROR' => 'L\'action video doit être appelée avec les paramètres « id » et « serveur ». Pour « serveur », seules les valeurs « vimeo » ou « youtube » ou « peertube » sont acceptées.',

    // actions/pdf.php
    'ATTACH_ACTION_PDF_PARAM_URL_ERROR' => 'L\'action pdf doit être appelée avec le paramètre « url » et l\'url renseignée doit provenir de la même origine que le wiki : c\'est à dire du même sous-domaine du serveur (par exemple \'xxx.yyy.com\'), du même schéma (par exemple \'https\') et du même port s\'il est spécifié (par exemple \'8080\').',
    'ATTACH_ACTION_DISPLAY_PDF_TEXT' => 'Afficher le pdf dans la page :',
    'ATTACH_ACTION_DISPLAY_PDF_LINK_TEXT' => 'sous forme de lien',
    'ATTACH_ACTION_DISPLAY_PDF_INCLUDED_TEXT' => 'directement inclus dans la page',

    // handler edit
    'ACTIVATE_JS_TO_UPLOAD_FILES' => 'Activer JavaScript pour joindre des fichiers',
    'UPLOAD_A_FILE' => 'Joindre / Ins&eacute;rer un fichier',
    'UPLOAD_A_FILE_SHORT' => 'Fichier',
    'UPLOAD_FILE' => 'T&eacute;l&eacute;charger le fichier',
    'CANCEL_THIS_UPLOAD' => 'Annuler cet envoi',
    'INSERT' => 'Ins&eacute;rer',
    'DOWNLOAD_LINK_TEXT' => 'Texte du lien de t&eacute;l&eacute;chargement',
    'IMAGE_ALIGN' => 'Alignement de l\'image',
    'IMAGE_SIZE' => 'Taille de l\'image',
    'THUMBNAIL' => 'Miniature',
    'MEDIUM' => 'Moyenne',
    'BIG' => 'Large',
    'ORIGINAL_SIZE' => 'Taille originale',
    'CAPTION' => 'Texte affiché au survol',
    'SEE_THE_ADVANCED_PARAMETERS' => 'Voir les param&egrave;tres avanc&eacute;s',
    'ADVANCED_PARAMETERS' => 'Param&egrave;tres avanc&eacute;s',
    'ASSOCIATED_LINK' => 'Lien web associé au clic',
    'GRAPHICAL_EFFECTS' => 'Effets graphiques',
    'WHITE_BORDER' => 'Bord blanc',
    'DROP_SHADOW' => 'Ombre port&eacute;e',
    'ZOOM_HOVER' => 'Agrandissement au survol',
    'ALT_INFOS' => 'Ce texte sera affich&eacute; &agrave; la place de l\'image si elle est introuvable sur le serveur. C\'est aussi celle qui sera lue par les technologies d\'assistance aux personnes malvoyantes. À laisser vide si l\'image est purement décorative',
    'ALTERNATIVE_TEXT' => 'Texte alternatif pour les personnes malvoyantes',
    'NONE' => 'Texte en dessous',
    'LEFT' => 'Gauche',
    'CENTER' => 'Centre',
    'RIGHT' => 'Droite',
    'FAILED' => '&Eacute;chou&eacute;',

    // handler ajaxupload
    'ATTACH_HANDLER_AJAXUPLOAD_FOLDER_NOT_READABLE' => 'Le dossier de téléchargement n\'est pas accessible en écriture.',
    'ATTACH_HANDLER_AJAXUPLOAD_NO_FILE' => 'Pas de fichiers envoyés.',
    'ATTACH_HANDLER_AJAXUPLOAD_EMPTY_FILE' => 'Le fichier est vide.',
    'ATTACH_HANDLER_AJAXUPLOAD_FILE_TOO_LARGE' => 'Le fichier est trop large.',
    'ATTACH_HANDLER_AJAXUPLOAD_AUTHORIZED_EXT' => 'Le fichier n\'a pas une extension autorisée, voici les autorisées : {ext}.',
    'ATTACH_HANDLER_AJAXUPLOAD_ERROR' => 'Impossible de sauver le fichier. L\'upload a été annulé ou le serveur a planté...',

    // edit config action
    'EDIT_CONFIG_HINT_ATTACH-VIDEO-CONFIG[DEFAULT_VIDEO_SERVICE]' => 'Service de vidéo par défaut (peertube, youtube ou vimeo)',
    'EDIT_CONFIG_HINT_ATTACH-VIDEO-CONFIG[DEFAULT_PEERTUBE_INSTANCE]' => 'Adresse du serveur peertube par défaut',
    'EDIT_CONFIG_HINT_MAX_FILE_SIZE' => 'Taille maximum des fichiers téléversés - octets (ex: Taille maximum des fichiers téléversés (ex: 2097152, 2048k, 2m)',
    'EDIT_CONFIG_GROUP_ATTACH' => 'Insertion de médias (images, vidéos)',
];
