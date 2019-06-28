<?php
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

// inclusion de la langue
if (isset($metadatas['lang'])) {
    $wakkaConfig['lang'] = $metadatas['lang'];
} elseif (!isset($wakkaConfig['lang'])) {
    $wakkaConfig['lang'] = 'fr';
}
include_once 'tools/attach/lang/attach_'.$wakkaConfig['lang'].'.inc.php';


// theme de jplayer utilise : de base on a 2 possibilites : pink.flag ou blue.monday
$wakkaConfig['attach_jplayer_skin'] = isset($wakkaConfig['attach_jplayer_skin']) ? $wakkaConfig['attach_jplayer_skin'] : 'blue.monday';

// size of images
$wakkaConfig['image-small-width'] = isset($wakkaConfig['image-small-width']) ? $wakkaConfig['image-small-width'] : 140;
$wakkaConfig['image-small-height'] = isset($wakkaConfig['image-small-height']) ? $wakkaConfig['image-small-height'] : 97;
$wakkaConfig['image-medium-width'] = isset($wakkaConfig['image-medium-width']) ? $wakkaConfig['image-medium-width'] : 300;
$wakkaConfig['image-medium-height'] = isset($wakkaConfig['image-medium-height']) ? $wakkaConfig['image-medium-height'] : 209;
$wakkaConfig['image-big-width'] = isset($wakkaConfig['image-big-width']) ? $wakkaConfig['image-big-width'] : 780;
$wakkaConfig['image-big-height'] = isset($wakkaConfig['image-big-height']) ? $wakkaConfig['image-big-height'] : 544;
$wakkaConfig['authorized_extensions'] = isset($wakkaConfig['authorized_extensions']) ? $wakkaConfig['authorized-extensions'] : array(
    // Images reconnues par PHP
    'jpg' => 'JPEG', 'png' => 'PNG', 'gif' => 'GIF', 'jpeg' => 'JPEG',

    // Autres images (peuvent utiliser le tag <img>)
    'bmp' => 'BMP', 'tif' => 'TIFF', 'svg' => 'SVG',

    // Audio / Video
    'aiff' => 'AIFF', 'anx' => 'Annodex', 'axa' => 'Annodex Audio', 'axv' => 'Annodex Video', 'asf' => 'Windows Media', 'avi' => 'AVI', 'flac' => 'Free Lossless Audio Codec', 'flv' => 'Flash Video', 'mid' => 'Midi', 'mng' => 'MNG', 'mka' => 'Matroska Audio', 'mkv' => 'Matroska Video', 'mov' => 'QuickTime', 'mp3' => 'MP3', 'mp4' => 'MPEG4', 'mpg' => 'MPEG', 'oga' => 'Ogg Audio', 'ogg' => 'Ogg Vorbis', 'ogv' => 'Ogg Video', 'ogx' => 'Ogg Multiplex', 'qt' => 'QuickTime', 'ra' => 'RealAudio', 'ram' => 'RealAudio', 'rm' => 'RealAudio', 'spx' => 'Ogg Speex', 'svg' => 'Scalable Vector Graphics', 'swf' => 'Flash', 'wav' => 'WAV', 'wmv' => 'Windows Media', '3gp' => '3rd Generation Partnership Project',

    // Documents
    'abw' => 'Abiword', 'ai' => 'Adobe Illustrator', 'bz2' => 'BZip', 'bin' => 'Binary Data', 'blend' => 'Blender', 'c' => 'C source', 'cls' => 'LaTeX Class', 'css' => 'Cascading Style Sheet', 'csv' => 'Comma Separated Values', 'deb' => 'Debian', 'doc' => 'Word', 'docx' => 'Word', 'djvu' => 'DjVu', 'dvi' => 'LaTeX DVI', 'eps' => 'PostScript', 'gz' => 'GZ', 'h' => 'C header', 'kml' => 'Keyhole Markup Language', 'kmz' => 'Google Earth Placemark File', 'mm' => 'Mindmap', 'pas' => 'Pascal', 'pdf' => 'PDF', 'pgn' => 'Portable Game Notation', 'ppt' => 'PowerPoint', 'pptx' => 'PowerPoint', 'ps' => 'PostScript', 'psd' => 'Photoshop', 'pub' => 'Microsoft Publisher', 'rpm' => 'RedHat/Mandrake/SuSE', 'rtf' => 'RTF', 'sdd' => 'StarOffice', 'sdw' => 'StarOffice', 'sit' => 'Stuffit', 'sty' => 'LaTeX Style Sheet', 'sxc' => 'OpenOffice.org Calc', 'sxi' => 'OpenOffice.org Impress', 'sxw' => 'OpenOffice.org', 'tex' => 'LaTeX', 'tgz' => 'TGZ', 'torrent' => 'BitTorrent', 'ttf' => 'TTF Font', 'txt' => 'texte', 'xcf' => 'GIMP multi-layer', 'xspf' => 'XSPF', 'xls' => 'Excel', 'xlsx' => 'Excel', 'xml' => 'XML', 'zip' => 'Zip',

    // open document format
    'odt' => 'opendocument text', 'ods' => 'opendocument spreadsheet', 'odp' => 'opendocument presentation', 'odg' => 'opendocument graphics', 'odc' => 'opendocument chart', 'odf' => 'opendocument formula', 'odb' => 'opendocument database', 'odi' => 'opendocument image', 'odm' => 'opendocument text-master', 'ott' => 'opendocument text-template', 'ots' => 'opendocument spreadsheet-template', 'otp' => 'opendocument presentation-template', 'otg' => 'opendocument graphics-template',
);


// une fonction pour passer les parametres a l'upload
$wikiClasses [] = 'ExtendAttach';

$wikiClassesContent [] = '
	// Fonction supplementaire pour paser des parametres a l\'upload
    function setParameter($parameter,$value) {
        $this->parameter[$parameter]=$value;
    }
';
