<?php

return [

    // controllers/ApiController.php
    // 'ATTACH_GET_URLIMAGE_CACHE_API_HELP' => "Fournit l'url du fichier de cache pour l'image voulue\n".
    //     "Nécessite le passage du jeton anti-csrf !",
    // 'ATTACH_GET_CACHE_URLIMAGE_NO_FILE' => 'Fichier image inexistant',

    // libs/attach.lib.php
    'ATTACH_ACTION_ATTACH' => 'Ação {{attach ...}}',
    'ATTACH_PARAM_DESC_REQUIRED' => 'parâmetro "desc" necessário para uma imagem',
    'ATTACH_PARAM_HEIGHT_NOT_NUMERIC' => 'o parâmetro "height", em pixels, deve ser composto unicamente de números inteiros',
    'ATTACH_PARAM_WIDTH_NOT_NUMERIC' => 'o parâmetro "width" em pixels deve ser composto unicamente de números inteiros',
    'ATTACH_UPLOAD_FORM_FOR_FILE' => 'Formulário de upload de arquivo',
    'ATTACH_BACK_TO_PAGE' => 'Voltar a página',
    'NO_RIGHT_TO_WRITE_IN_THIS_PAGE' => 'Você não têm o acesso em gravação para esta página',
    'INVALID_REQUEST_METHOD' => 'Método de solicitação inválido',
    'ERROR_MOVING_TEMPORARY_FILE' => 'Erro ao mover o arquivo temporário',
    'ERROR_UPLOAD_MAX_FILESIZE' => 'O arquivo de upload excede o tamanho de upload_max_filesize configurado no php.ini.',
    'ERROR_MAX_FILE_SIZE' => 'O arquivo de upload excede o tamanho MAX_FILE_SIZE, que foi especificado no formulário HTML.',
    'ERROR_PARTIAL_UPLOAD' => 'O arquivo foi parcialmente baixado.',
    'ERROR_NO_FILE_UPLOADED' => 'Nenhum arquivo foi baixado.',
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
    'ATTACH_NO_RIGHTS_TO_ACCESS_FILEMANAGER' => 'Deve ter acesso em gravação à página para acessar aos anexos gerentes',
    
    // actions/backgroundimage.php
    // 'ATTACH_ACTION_BACKGROUNDIMAGE' => 'Action {{backgroundimage ...}}',
    // 'ATTACH_PARAM_FILE_OR_BGCOLOR_NOT_FOUND' => 'il faut indiquer soit une image avec le paramètre "file" ou une couleur de fond avec le paramètre "bgcolor"',


    // actions/player.php
    'ATTACH_ACTION_PLAYER' => 'Ação {{player ...}}',
    'ATTACH_DOWNLOAD_THE_FILE' => 'Baixar arquivo',
    'ATTACH_URL_NOT_VALID' => 'URL inválido ou não pôde ser aberto',
    'ATTACH_PARAM_URL_REQUIRED' => 'parâmetro "url" obrigatório',
    'ATTACH_PLAYER_CAN_ONLY_OPEN_FILES_LIKE' => 'o player só pode ler arquivos mp3, flv e mm, e URL',
    'ATTACH_NOT_LINKED_TO_GOOD_FILE_EXTENSION' => 'não aponta para esses tipos de arquivos',

    // actions/pointimage.php
    'ATTACH_ACTION_POINTIMAGE' => 'Ação {{pointimage ...}}',
    'ATTACH_PARAM_FILE_NOT_FOUND' => 'parâmetro "file" necessária',
    'ATTACH_PARAM_FILE_MUST_BE_IMAGE' => 'o parâmetro "file" deve ser uma imagem (gif, jpg, jpeg, png)',
    'ATTACH_DEFAULT_MARKER' => 'Ponto padrão',
    'ATTACH_ADD_MARKER' => 'Adicionar um ponto',
    'ATTACH_TITLE' => 'Título',
    'ATTACH_DESCRIPTION' => 'Descrição',
    'ATTACH_CANCEL' => 'Cancelar',
    'ATTACH_SAVE' => 'Salvar',

    // actions/video.php
    'ATTACH_ACTION_VIDEO_PARAM_ERROR' => 'O vídeo de ação deve ser chamado com os parâmetros «id» e «serveur». Para «serveur», apenas os valores «vimeo» ou «youtube» ou «peertube» são permitidos.',
    // 'FILEMANAGER_ACTION_NEED_ACCESS' => 'Seul le propriétaire de cette page peut accéder au gestionnaire des fichiers attaché',

    // actions/pdf.php
    'ATTACH_ACTION_PDF_PARAM_URL_ERROR' => 'O pdf de ação deve ser chamado com o parâmetro «url» e o url fornecido deve estar no mesmo host que o wiki (por exemplo \' xxx.yyy.com \'), mesmo esquema (por exemplo \' https \') e a mesma porta se especificada (por exemplo \'8080 \'). ',
    // 'ATTACH_ACTION_DISPLAY_PDF_TEXT' => 'Afficher le pdf dans la page :',
    // 'ATTACH_ACTION_DISPLAY_PDF_LINK_TEXT' => 'sous forme de lien',
    // 'ATTACH_ACTION_DISPLAY_PDF_INCLUDED_TEXT' => 'directement inclus dans la page',

    // handler edit
    'ACTIVATE_JS_TO_UPLOAD_FILES' => 'Habilitar o JavaScript para anexar arquivos',
    'UPLOAD_A_FILE' => 'Anexar / Inserir arquivo',
    'UPLOAD_A_FILE_SHORT' => 'Arquivo',
    'UPLOAD_FILE' => 'Baixar arquivo',
    'CANCEL_THIS_UPLOAD' => 'Cancelar a transmissão',
    'INSERT' => 'Inserir',
    'DOWNLOAD_LINK_TEXT' => 'Texto do link para download',
    'IMAGE_ALIGN' => 'Alinhamento da imagem',
    'IMAGE_SIZE' => 'Tamanho de imagem',
    'THUMBNAIL' => 'Miniatura',
    'MEDIUM' => 'Média',
    'BIG' => 'Grande',
    'ORIGINAL_SIZE' => 'Tamanho original',
    'CAPTION' => 'Texto da miniatura',
    'SEE_THE_ADVANCED_PARAMETERS' => 'Mostrar configurações avançadas',
    'ADVANCED_PARAMETERS' => 'Configurações avançadas',
    'ASSOCIATED_LINK' => 'Link relacionado',
    'GRAPHICAL_EFFECTS' => 'Efeitos gráficos',
    'WHITE_BORDER' => 'Quadro branco',
    'DROP_SHADOW' => 'Sombra',
    'ZOOM_HOVER' => 'Visão geral ampliação',
    'ALT_INFOS' => 'Este texto será exibido em vez da imagem, se não for encontrada no servidor',
    'ALTERNATIVE_TEXT' => 'Texto de substituição',
    // 'NONE' => 'Texte en dessous',
    'LEFT' => 'Esquerda',
    'CENTER' => 'Centro',
    'RIGHT' => 'Direita',
    'FAILED' => 'Falhado',

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
