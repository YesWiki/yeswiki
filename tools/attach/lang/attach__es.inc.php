<?php

$GLOBALS['translations'] = array_merge(
    $GLOBALS['translations'],
    array(

        // actions/attach.class.php
        'ATTACH_ACTION_ATTACH' => 'Acción {{attach ...}}',
        'ATTACH_PARAM_DESC_REQUIRED' => 'parámetro "desc" obligatorio para una imagen',
        'ATTACH_PARAM_HEIGHT_NOT_NUMERIC' => 'el parámetro "height", en pixels, sólo debe estar compuesto de números enteros',
        'ATTACH_PARAM_WIDTH_NOT_NUMERIC' => 'el parámetro "width", en pixels, sólo debe estar compuesto de números enteros',
        'ATTACH_BACK_TO_PAGE' => 'Regreso hacia la página',
        'NO_RIGHT_TO_WRITE_IN_THIS_PAGE' => 'No tienes los derechos de acceso para escribir en esta página',
        'INVALID_REQUEST_METHOD' => 'Método de  petición invalide',
        'ERROR_MOVING_TEMPORARY_FILE' => 'Error durante el desplazamiento del archivo temporario',
        'ERROR_UPLOAD_MAX_FILESIZE' => 'Le archivo descargado excede al tamaño de upload_max_filesize, configurado en el php.ini.',
        'ERROR_MAX_FILE_SIZE' => 'EL archivo descargado excede al tamaño de MAX_FILE_SIZE, configurado en el formulario HTML.',
        'ERROR_PARTIAL_UPLOAD' => 'La descarga del archivo ha sido parcial.',
        'ERROR_NO_FILE_UPLOADED' => 'Ningun archivo ha sido descargado.',


        // actions/filemanager.php
        'ATTACH_NO_RIGHTS_TO_ACCESS_FILEMANAGER' => 'Necesitas los derechos de escritura de la página para acceder al administrador de los archivos atados',

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

        // handler edit
        'ACTIVATE_JS_TO_UPLOAD_FILES' => 'Activar el JavaScript para adjuntar archivos',
        'UPLOAD_A_FILE' => 'Adjuntar / Insertar un archivo',
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
        'LEFT' => 'Izquierda',
        'CENTER' => 'Centro',
        'RIGHT' => 'Derecha',
        'FAILED' => 'Error'
    )
);
