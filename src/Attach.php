<?php

namespace YesWiki\Core;

// Classe de gestion de l'action {{attach}} (ticket 17: a proper autoloaded class under
// YesWiki\Core -- resolved by src/autoload.inc.php's own fallback autoloader, same as
// ApiResponse/YesWikiController -- instead of a bare global-namespace class manually
// `include`d with an `if (!class_exists(...))` guard at every one of its ~11 call sites.
// Deliberately still plain `new Attach($wiki)`, NOT a DI-managed singleton service: its
// design is stateful per invocation (CheckParams()/doAttach() write onto $this), which is
// only safe with a fresh instance per {{attach}} tag/caller, exactly as before.

use stefangabos\Zebra_Image\Zebra_Image;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Identity\Service\InputFilter;
use YesWiki\Core\Service\FileManager;
use YesWiki\Core\Service\LinkTracker;
use YesWiki\Render\Service\TemplateHelperService;

class Attach
{
    public $wiki = ''; // objet wiki courant
    public $attachConfig = []; // configuration de l'action
    public $file = ''; // nom du fichier
    // ticket 17: set by CheckParams() when 'file'/'attachfile' resolves to a FileManager
    // file-entry tag (the {{attach}} action's own current path); left empty for the
    // legacy raw-filename fallback still used by other internal callers (see
    // GetFullFilename()'s own doc comment)
    public $fileTag = '';
    public $height;
    public $width;
    public $desc = ''; // description du fichier
    public $link = ''; // url de lien (image sensible)
    public $caption = ''; // texte de la vignette au survol
    public $legend = ''; // texte en dessous de l'image
    public $nofullimagelink = ''; // mettre un lien vers l'image entiere
    public $isPicture = 0; // indique si c'est une image
    public $isAudio = 0; // indique si c'est un fichier audio
    public $isWma = 0; // indique si c'est un fichier wma
    public $isPDF = 0; // indique si c'est un fichier pdf
    public $displayPDF = 0; // indique s'il faut afficher le fichier pdf
    public $classes = 'attached_file'; // classe pour afficher une image
    public $attachErr = ''; // message d'erreur
    public $pageId = 0; // identifiant de la page
    public $isSafeMode = true; // indicateur du safe mode de PHP
    public $data = ''; // indicateur du safe mode de PHP
    private $params;

    /**
     * Constructeur. Met les valeurs par defaut aux parametres de configuration.
     */
    public function __construct(&$wiki)
    {
        $this->wiki = $wiki;
        $this->params = $this->wiki->services->get(ParameterBagInterface::class);
        $this->attachConfig = $this->params->get('attach_config');

        if (!is_array($this->attachConfig)) {
            throw new \Exception('attach_config should be an array in yeswiki.config.php');
        }

        if (empty($this->attachConfig['max_file_size'])) {
            $this->attachConfig['max_file_size'] = $this->params->get('max-upload-size');
        }

        $safemode = $this->wiki->GetConfigValue('no_safe_mode');
        if (empty($safemode)) {
            if (version_compare(phpversion(), '5.3', '<')) {
                // le safe_mode n'existe que pour php < 5.3
                $this->isSafeMode = ini_get('safe_mode');
            } else {
                $this->isSafeMode = true;
            }
        } else {
            $this->isSafeMode = false;
        }
    }

    /******************************************************************************
     *    FONCTIONS UTILES
     *******************************************************************************/
    /**
     * transforme des valeurs en mega / kilo / giga octets en entier.
     *
     * @param string $size la taille
     *
     * @return int
     */
    public function parse_size($size)
    {
        $unit = preg_replace('/[^bkmgtpezy]/i', '', $size); // Remove the non-unit characters from the size.
        $size = preg_replace('/[^0-9\.]/', '', $size); // Remove the non-numeric characters from the size.
        if ($unit) {
            // Find the position of the unit in the ordered string which is the power of magnitude to multiply a kilobyte by.
            return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
        }

        return round($size);
    }

    /**
     * Création d'une suite de répertoires récursivement.
     */
    public function mkdir_recursif($dir)
    {
        if (strlen($dir) == 0) {
            return 0;
        }

        if (is_dir($dir)) {
            return 1;
        } elseif (dirname($dir) == $dir) {
            return 1;
        }

        return $this->mkdir_recursif(dirname($dir)) and mkdir($dir, 0755);
    }

    /**
     * Renvois le chemin du script.
     */
    public function GetScriptPath()
    {
        return $this->wiki->getBaseUrl() . '/';
        // if (preg_match("/.(php)$/i", $_SERVER["PHP_SELF"])) {
        //     $a = explode('/', $_SERVER["PHP_SELF"]);
        //     $a[count($a) - 1] = '';
        //     $path = implode('/', $a);
        // } else {
        //     $path = $_SERVER["PHP_SELF"];
        // }
        // $http = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://');
        // return !empty($_SERVER["HTTP_HOST"]) ?
        //     $http . $_SERVER["HTTP_HOST"] . $path
        //     : $http . $_SERVER["SERVER_NAME"] . $path;
    }

    /**
     * Calcul le repertoire d'upload en fonction du safe_mode.
     */
    public function GetUploadPath()
    {
        if ($this->isSafeMode) {
            $path = $this->attachConfig['upload_path'];
        } else {
            $path = $this->attachConfig['upload_path'] . '/' . $this->wiki->GetPageTag();
            if (!is_dir($path)) {
                $this->mkdir_recursif($path);
            }
        }

        return $path;
    }

    /**
     * Calcul le repertoire de cache en fonction du safe_mode.
     */
    public function GetCachePath()
    {
        if ($this->isSafeMode) {
            $path = $this->attachConfig['cache_path'];
        } else {
            $path = $this->attachConfig['cache_path'] . '/' . $this->wiki->GetPageTag();
            if (!is_dir($path)) {
                $this->mkdir_recursif($path);
            }
        }

        return $path;
    }

    /**
     * Calcule le nom complet du fichier attaché en fonction du safe_mode, du nom et de la date de
     * revision la page courante.
     * Le nom du fichier "mon fichier.ext" attache ? la page "LaPageWiki"sera :
     *  mon_fichier_datepage_update.ext
     *     update : date de derniere mise a jour du fichier
     *     datepage : date de revision de la page ? laquelle le fichier a ete lié/mis a jour
     *  Si le fichier n'est pas une image un '_' est ajoute : mon_fichier_datepage_update.ext_
     *  Selon la valeur de safe_mode :
     *  safe_mode = on :     LaPageWiki_mon_fichier_datepage_update.ext_
     *  safe_mode = off:     LaPageWiki/mon_fichier_datepage_update.ext_ avec "LaPageWiki" un sous-repertoire du répertoire upload.
     */
    public function GetFullFilename($newName = false)
    {
        // use current date if page has no date that could arrive when using page 'root' via Actions Builder
        $pagedate = $this->convertDate(
            isset($this->wiki->page['time'])
                ? $this->wiki->page['time']
                : (
                    $this->wiki->tag == 'root'
                    ? date('Y-m-d H:i:s')
                    : null // error
                )
        );

        $file = [];
        // decompose le nom du fichier en nom+extension ou en page/nom+extension
        if (preg_match('`^((.+)/)?(.*)\.(.*)$`', str_replace(' ', '_', $this->file), $match)) {
            list(, , $file['page'], $file['name'], $file['ext']) = $match;
            if (!$this->isPicture() && !$this->isAudio() && !$this->isVideo() && !$this->isWma() && !$this->isFlashvideo()) {
                $file['ext'] .= '_';
            }
        } else {
            return false;
        }
        // recuperation du chemin d'upload
        $path = $this->GetUploadPath($this->isSafeMode);
        $page_tag = $file['page'] ? $file['page'] : $this->wiki->GetPageTag();
        // generation du nom ou recherche de fichier ?
        if ($newName) {
            $full_file_name = $file['name'] . '_' . $pagedate . '_' . $this->getDate() . '.' . $file['ext'];
            if ($this->isSafeMode) {
                $full_file_name = $path . '/' . $page_tag . '_' . $full_file_name;
            } else {
                $full_file_name = $path . '/' . $full_file_name;
            }
        } else {
            $isActionBuilderPreview = $this->wiki->GetPageTag() == 'root';
            // recherche du fichier
            if ($isActionBuilderPreview) {
                // bazar action builder, preview action
                $searchPattern = '`' . $file['name'] . '_\d{14}_\d{14}\.' . $file['ext'] . '$`';
            } elseif ($this->isSafeMode) {
                // TODO Recherche dans le cas ou safe_mode=on
                $searchPattern = '`^' . $page_tag . '_' . $file['name'] . '_\d{14}_\d{14}\.' . $file['ext'] . '$`';
            } else {
                $searchPattern = '`^' . $file['name'] . '_\d{14}_\d{14}\.' . $file['ext'] . '$`';
            }

            $files = $this->searchFiles($searchPattern, $path);

            $unedate = 0;
            foreach ($files as $file) {
                // on garde la dateupload la plus grande
                if ($file['dateupload'] > $unedate) {
                    $theFile = $file;
                    $unedate = $file['dateupload'];
                }
            }
            if ($isActionBuilderPreview && count($files) > 0) {
                $theFile = $files[0];
            }
            $full_file_name = '';
            if (isset($theFile) && is_array($theFile)) {
                $full_file_name = $path . '/' . $theFile['realname'];
            }
        }

        return $full_file_name;
    }

    /**
     * Test si le fichier est une image.
     */
    public function isPicture($file = null)
    {
        if ($file == null) {
            $file = $this->file;
        }

        return preg_match('/.(' . $this->attachConfig['ext_images'] . ')$/i', $file) == 1;
    }

    /**
     * Test si le fichier est un fichier audio.
     */
    public function isAudio()
    {
        return preg_match('/.(' . $this->attachConfig['ext_audio'] . ')$/i', $this->file) == 1;
    }

    /**
     * Test si le fichier est un fichier vidéo.
     */
    public function isVideo()
    {
        return preg_match('/.(' . $this->attachConfig['ext_video'] . ')$/i', $this->file) == 1;
    }

    // isFreeMindMindMap()/showAsFreeMindMindMap() are gone (ticket 17): the FreeMind
    // mindmap viewer embedded a Flash .swf, unsupported in every browser since ~2021.

    /**
     * Test si le fichier est un fichier flv Flash video.
     */
    public function isFlashvideo()
    {
        return preg_match('/.(' . $this->attachConfig['ext_flashvideo'] . ')$/i', $this->file) == 1;
    }

    /**
     * Test si le fichier est un fichier wma.
     */
    public function isWma()
    {
        return preg_match('/.(' . $this->attachConfig['ext_wma'] . ')$/i', $this->file) == 1;
    }

    /**
     * Test si le fichier est un fichier pdf.
     */
    public function isPDF()
    {
        return preg_match('/.(' . $this->attachConfig['ext_pdf'] . ')$/i', $this->file) == 1;
    }

    /**
     * Renvoie la date courante au format utilise par les fichiers.
     */
    public function getDate()
    {
        return date('YmdHis');
    }

    /**
     * convertie une date yyyy-mm-dd hh:mm:ss au format yyyymmddhhmmss.
     */
    public function convertDate($date)
    {
        if (!is_string($date)) {
            return '';
        }
        $date = str_replace(' ', '', $date);
        $date = str_replace(':', '', $date);

        return str_replace('-', '', $date);
    }

    /**
     * Parse une date au format yyyymmddhhmmss et renvoie un tableau assiatif.
     */
    public function parseDate($sDate)
    {
        $pattern = '`^(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})$`';
        $res = '';
        if (preg_match($pattern, $sDate, $m)) {
            // list(,$res['year'],$res['month'],$res['day'],$res['hour'],$res['min'],$res['sec'])=$m;
            $res = $m[1] . '-' . $m[2] . '-' . $m[3] . ' ' . $m[4] . ':' . $m[5] . ':' . $m[6];
        }

        return $res ? $res : false;
    }

    /**
     * sanitize filename without special chars - spaces or accents.
     *
     * @return string $sanitizedFilename
     */
    public function sanitizeFilename(string $filename): string
    {
        $search = ['@[éèêëÊË]@i', '@[àâäÂÄ]@i', '@[îïÎÏ]@i', '@[ûùüÛÜ]@i', '@[ôöÔÖ]@i', '@[ç]@i', '@[ ]@i', '@[^a-zA-Z0-9_\.]@'];
        $replace = ['e', 'a', 'i', 'u', 'o', 'c', '_', ''];
        $sanitizedFilename = preg_replace($search, $replace, mb_convert_encoding($filename, 'ISO-8859-1', 'UTF-8'));

        return $sanitizedFilename;
    }

    /**
     * Decode un nom long de fichier.
     */
    public function decodeLongFilename($filename)
    {
        $afile = [];
        $afile['realname'] = basename($filename);
        $afile['size'] = file_exists($filename) ? filesize($filename) : null;
        $afile['path'] = dirname($filename);
        if (preg_match('`^(.*)_(\d{14})_(\d{14})\.(.*)(trash\d{14})?$`', $afile['realname'], $m)) {
            $afile['name'] = $m[1];
            // suppression du nom de la page si safe_mode=on
            if ($this->isSafeMode) {
                $afile['name'] = preg_replace('`^(' . $this->wiki->tag . ')_(.*)$`i', '$2', $afile['name']);
            }
            $afile['datepage'] = $m[2];
            $afile['dateupload'] = $m[3];
            $afile['trashdate'] = preg_replace('`(.*)trash(\d{14})`', '$2', $m[4]);
            // suppression de trashxxxxxxxxxxxxxx eventuel
            $afile['ext'] = preg_replace('`^(.*)(trash\d{14})$`', '$1', $m[4]);
            $afile['ext'] = rtrim($afile['ext'], '_');
            // $afile['ext'] = rtrim($m[4],'_');
        }

        return $afile;
    }

    /**
     * Renvois un tableau des fichiers correspondant au pattern. Chaque element du tableau est un
     * tableau associatif contenant les informations sur le fichier.
     */
    public function searchFiles($filepattern, $start_dir)
    {
        $files_matched = [];
        $start_dir = rtrim($start_dir, '\/');
        $fh = opendir($start_dir);
        while (($file = readdir($fh)) !== false) {
            if (strcmp($file, '.') == 0 || strcmp($file, '..') == 0 || is_dir($file)) {
                continue;
            }

            if (preg_match($filepattern, $file)) {
                $files_matched[] = $this->decodeLongFilename($start_dir . '/' . $file);
            }
        }

        return $files_matched;
    }

    /******************************************************************************
     *    FONCTIONS D'ATTACHEMENTS
     *******************************************************************************/
    /**
     * Test les parametres passes a l'action.
     */
    public function CheckParams()
    {
        // recuperation des parametres necessaire
        $this->file = htmlspecialchars($this->wiki->GetParameter('attachfile'));
        if (empty($this->file)) {
            $this->file = htmlspecialchars($this->wiki->GetParameter('file'));
        }
        // ticket 17: {{attach}}'s own file= is now a FileManager tag, not a raw
        // filename -- resolve it here so doAttach()/showAsX() below can serve it
        // through the ACL-checked download route instead of a direct static path.
        // Falls through to the legacy raw-filename search (GetFullFilename()) for
        // anything that isn't a known file tag -- other internal callers (tag-cloud
        // thumbnails, farm imports, ...) still set $this->file to a raw filename
        // directly and never go through CheckParams()/this resolution at all.
        $fileManager = $this->wiki->services->get(FileManager::class);
        if ($fileManager->isFileTag($this->file)) {
            $entry = $fileManager->getOne($this->file);
            $this->fileTag = $this->file;
            $this->file = $entry['original_filename'];
        }

        $this->desc = $this->wiki->GetParameter('attachdesc');
        if (empty($this->desc)) {
            $this->desc = $this->wiki->GetParameter('desc');
        }
        $this->desc = htmlentities(strip_tags($this->desc)); // avoid XSS

        $this->link = $this->wiki->GetParameter('attachlink'); // url de lien - uniquement si c'est une image
        if (empty($this->link)) {
            $this->link = $this->wiki->GetParameter('link');
        }

        $this->caption = $this->wiki->GetParameter('caption'); // texte de la vignette (au survol)
        $this->legend = $this->wiki->GetParameter('legend'); // texte de la vignette (en dessous)
        $this->nofullimagelink = $this->wiki->GetParameter('nofullimagelink');
        $this->height = $this->wiki->GetParameter('height');
        $this->width = $this->wiki->GetParameter('width');
        $this->displayPDF = $this->wiki->GetParameter('displaypdf');
        $this->data = $this->wiki->services->get(TemplateHelperService::class)->getDataParameter();

        // test de validité des parametres
        if (empty($this->file)) {
            $this->attachErr = '<div class="alert alert-danger"><strong>' . _t('ATTACH_ACTION_ATTACH') . '</strong> : ' . _t('ATTACH_PARAM_FILE_NOT_FOUND') . '.</div>' . "\n";
        }
        if (!empty($this->width) && !ctype_digit(strval($this->width))) {
            $this->attachErr = '<div class="alert alert-danger"><strong>' . _t('ATTACH_ACTION_ATTACH') . '</strong> : ' . _t('ATTACH_PARAM_WIDTH_NOT_NUMERIC') . '.</div>' . "\n";
        }
        if (!empty($this->height) && !ctype_digit(strval($this->height))) {
            $this->attachErr = '<div class="alert alert-danger"><strong>' . _t('ATTACH_ACTION_ATTACH') . '</strong> : ' . _t('ATTACH_PARAM_HEIGHT_NOT_NUMERIC') . '.</div>' . "\n";
        }

        if ($this->wiki->GetParameter('class')) {
            $array_classes = explode(' ', $this->wiki->GetParameter('class'));
            foreach ($array_classes as $c) {
                $this->classes .= ' ' . trim($c);
            }
        }

        $size = $this->wiki->GetParameter('size');
        switch ($size) {
            case 'small':
                $this->width = $this->wiki->config['image-small-width'];
                $this->height = $this->wiki->config['image-small-height'];
                break;
            case 'medium':
                $this->width = $this->wiki->config['image-medium-width'];
                $this->height = $this->wiki->config['image-medium-height'];
                break;
            case 'big':
                $this->width = $this->wiki->config['image-big-width'];
                $this->height = $this->wiki->config['image-big-height'];
                break;
        }

        if (empty($this->height) && !empty($this->width)) {
            // on ajuste la hauteur
            $this->height = $this->width;
        } elseif (!empty($this->height) && empty($this->width)) {
            // on ajuste la largeur
            $this->width = $this->height;
        }
    }

    /**
     * URL vers le contenu du fichier. Pour une entree taguee FileManager (le chemin
     * {{attach}} normal desormais), c'est la route API qui applique reellement l'ACL
     * de lecture -- $fullFilename (sous private/) n'est plus servi directement par le
     * serveur web. Pour le chemin legacy (fichier brut, non tague), $fullFilename reste
     * sous l'ancien files/ directement accessible, comme avant.
     */
    private function fileUrl($fullFilename, bool $forceDownload = false): string
    {
        if (!empty($this->fileTag)) {
            $url = $this->wiki->href('', 'api/files/' . $this->fileTag . '/download', [], false);

            return $forceDownload ? $url . '?download=1' : $url;
        }

        return $this->GetScriptPath() . $fullFilename;
    }

    /**
     * Affiche le fichier lié comme une image.
     */
    public function showAsImage($fullFilename)
    {
        // ticket 17: skip the resize-to-cache step for a tagged (new-model) file --
        // $fullFilename now lives under private/, and writing a resized COPY of it
        // into the public cache/ directory would defeat the point of moving it there.
        // Full-size image, width/height as plain HTML attributes (browser-side scaling)
        // instead of a server-side pre-resize. The legacy (untagged) fallback below
        // keeps the original resize-to-cache behavior unchanged.
        if (!empty($this->fileTag) || preg_match('/.(svg)$/i', $this->file) == 1) {
            $width = $this->width;
            $height = $this->height;
            $img_name = $fullFilename;
        } else {
            if ((!empty($this->height)) && (!empty($this->width))) {
                if (!file_exists($image_dest = $this->getResizedFilename($fullFilename, $this->width, $this->height))) {
                    $this->redimensionner_image($fullFilename, $image_dest, $this->width, $this->height);
                }
                $img_name = $image_dest;
            } else {
                $img_name = $fullFilename;
            }
            list($width, $height, $type, $attr) = getimagesize($img_name);
        }
        // pour l'image avec bordure on enleve la taille de la bordure!
        if (strstr($this->classes, 'whiteborder')) {
            $width = $width - 20;
            $height = $height - 20;
        }

        $imgSrc = !empty($this->fileTag) ? $this->fileUrl($fullFilename) : ($this->GetScriptPath() . $img_name);
        // c'est une image : balise <IMG..../>
        $img = '<img loading="lazy" class="img-responsive" src="' . $imgSrc . '" ' .
            'alt="' . $this->desc . ($this->link ? "\nLien vers: $this->link" : '') . '"' .
            (!empty($width) ? ' width="' . $width . '"' : '') . (!empty($height) ? ' height="' . $height . '"' : '') . ' />';
        // test si c'est une image sensible
        $classDataForLinks =
            strstr($this->classes, 'new-window')
            ? ' class="new-window"'
            : (
                strstr($this->classes, 'modalbox')
                ? ' class="modalbox" data-size="modal-lg"'
                : ''
            );
        if (!empty($this->link)) {
            // create link if needed
            $linkParts = $this->wiki->extractLinkParts($this->link);
            if ($linkParts) {
                $this->wiki->services->get(LinkTracker::class)->forceAddIfNotIncluded($linkParts['tag']);
            }
            $link = '<a href="' . $this->wiki->generateLink($this->link) . '"' . $classDataForLinks . '>';
        } else {
            if (empty($this->nofullimagelink) or !$this->nofullimagelink) {
                $link = '<a href="' . $this->fileUrl($fullFilename, true) . '"' . $classDataForLinks . '>';
            }
        }
        $caption = '';
        if (!empty($this->caption)) {
            $caption .= '<figcaption>' . $this->caption . '</figcaption>';
        }
        $legend = '';
        if (!empty($this->legend)) {
            $legend .= '<div class="legend">' . $this->legend . '</div>';
        }
        $data = '';
        if (is_array($this->data)) {
            foreach ($this->data as $key => $value) {
                $data .= ' data-' . $key . '="' . $value . '"';
            }
        }

        $notAligned = (strpos($this->classes, 'left') === false && strpos($this->classes, 'right') == false && strpos($this->classes, 'center') == false);
        $output = ($notAligned ? '<div>' : '') . (isset($link) ? $link : '') . "<figure class=\"$this->classes\" $data>$img$caption$legend</figure>" . (isset($link) ? '</a>' : '') . ($notAligned ? '</div>' : '');

        echo $output;
    }

    /**
     * Affiche le fichier lié comme un lien.
     */
    public function showAsLink($fullFilename)
    {
        $url = $this->fileUrl($fullFilename, true);
        echo '<a href="' . $url . '">' . ($this->desc ? $this->desc : $this->file) . '</a>';
    }

    // Affiche le fichier liee comme un fichier video
    public function showAsVideo($fullFilename)
    {
        $output = $this->wiki->format(
            '{{player url="' . $this->fileUrl($fullFilename) . '" type="video" ' .
                'height="' . (!empty($this->height) ? $this->height : '300px') . '" ' .
                'width="' . (!empty($this->width) ? $this->width : '400px') . '"}}'
        );
        echo $output;
    }

    // Affiche le fichier liee comme un fichier audio
    public function showAsAudio($fullFilename)
    {
        $output = $this->wiki->format('{{player url="' . $this->fileUrl($fullFilename) . '" type="audio"}}');
        echo $output;
    }

    // Affiche le fichier liee comme un fichier pdf
    public function showAsPDF($fullFilename)
    {
        // Defines parameters for pdf action
        $this->wiki->setParameter('url', $this->fileUrl($fullFilename));
        if (empty($this->wiki->GetParameter('hauteurmax')) && empty($this->wiki->GetParameter('largeurmax'))) {
            $this->wiki->setParameter('hauteurmax', $this->wiki->GetParameter('height'));
            $this->wiki->setParameter('largeurmax', $this->wiki->GetParameter('width'));
        }
        // position
        $newclass = '';
        if (strstr($this->classes, 'right')) {
            if (strstr($this->classes, 'pull-right')) {
                $newclass = str_replace('right', '', $this->classes);
            } else {
                $newclass = str_replace('right', 'pull-right', $this->classes);
            }
        }
        if (strstr($this->classes, 'left')) {
            if (strstr($this->classes, 'pull-left')) {
                $newclass = str_replace('left', '', $this->classes);
            } else {
                $newclass = str_replace('left', 'pull-left', $this->classes);
            }
        }

        // define class
        if ($newclass != '') {
            $this->wiki->setParameter('class', $newclass);
        }

        // Call pdf actions
        $params = $this->wiki->parameter;
        echo $this->wiki->Action('pdf', 0, $params);
    }

    // showUpdateLink() is gone (ticket 17): it linked to tools/attach/handlers/page/
    // upload.php, deleted along with the rest of tools/attach -- "update this exact
    // attached file in place" doesn't have a direct equivalent once a file is its own
    // reusable, independently-tagged entry (upload a new one via the picker instead).

    /**
     * Affiche un liens comme un fichier inexistant.
     */
    public function showFileNotExits()
    {
        echo '<div class="yw-alert yw-alert--danger">' . _t('ATTACH_PARAM_FILE_NOT_FOUND') . ' (' . htmlspecialchars($this->file) . ')</div>';
    }

    /**
     * Affiche l'attachement.
     */
    public function doAttach()
    {
        $this->CheckParams();
        if ($this->attachErr) {
            echo $this->attachErr;

            return;
        }
        // ticket 17: a tag-resolved file's bytes live under private/ (getPhysicalPath()),
        // no longer web-servable directly -- the legacy GetFullFilename() search is only
        // reached for the raw-filename fallback (see CheckParams()'s own doc comment)
        $fullFilename = !empty($this->fileTag)
            ? $this->wiki->services->get(FileManager::class)->getPhysicalPath($this->fileTag)
            : $this->GetFullFilename();
        // test d'existance du fichier
        if (empty($fullFilename) || !file_exists($fullFilename)) {
            $this->showFileNotExits();

            return;
        }
        // le fichier existe : affichage en fonction du type
        if ($this->isPicture()) {
            $this->showAsImage($fullFilename);
        } elseif ($this->isVideo() || $this->isFlashvideo()) {
            $this->showAsVideo($fullFilename);
        } elseif ($this->isAudio() || $this->isWma()) {
            $this->showAsAudio($fullFilename);
        } elseif ($this->isPDF() && $this->displayPDF) {
            $this->showAsPDF($fullFilename);
        } else {
            $this->showAsLink($fullFilename);
        }
    }

    // doUpload()/showUploadForm()/performUpload() and doDownload() are gone (ticket
    // 17): superseded by ApiController::uploadFile()/downloadFile() (a single
    // validated upload path backed by a real API route, and a download route that
    // actually enforces the file's own read ACL -- the old doDownload() below
    // performed none at all beyond an unrelated page-context ACL check in
    // DownloadHandler). Confirmed via a repo-wide grep that nothing outside
    // tools/attach's own now-deleted handlers called these four methods.

    /******************************************************************************
     *    FONTIONS DU FILEMANAGER
     *******************************************************************************/
    public function doFileManager($isAction = false)
    {
        $do = (isset($_GET['do']) && $_GET['do']) ? $_GET['do'] : '';
        switch ($do) {
            case 'restore':
                $this->fmRestore();
                $this->fmShow(true, $isAction);
                break;
            case 'erase':
                $this->fmErase();
                $this->fmShow(true, $isAction);
                break;
            case 'del':
                $this->fmDelete();
                $this->fmShow(false, $isAction);
                break;
            case 'trash':
                $this->fmShow(true, $isAction);
                break;
            case 'emptytrash':
                $this->fmEmptyTrash(); // pas de break car apres un emptytrash => retour au gestionnaire
                // no break
            default:
                $this->fmShow(false, $isAction);
        }
    }

    /**
     * Controlleur du gestionnaire des fichiers, modifie pour utilisation dans une action {{filemanager}}.
     */
    public function doFileManagerAction()
    {
        $this->doFileManager(true);
    }

    /**
     * Return human readable sizes.
     *
     * @author      Aidan Lister <aidan@php.net>
     *
     * @version     1.3.0
     *
     * @see        http://aidanlister.com/2004/04/human-readable-file-sizes/
     *
     * @param int    $size      size in bytes
     * @param string $max       maximum unit
     * @param string $system    'si' for SI, 'bi' for binary prefixes
     * @param string $retstring return string format
     */
    public function size_readable($size, $max = null, $system = 'si', $retstring = '%01.2f %s')
    {
        // Pick units
        $systems = [];
        $systems['si']['prefix'] = ['', 'Ko', 'Mo', 'Go', 'To', 'Po'];
        $systems['si']['size'] = 1000;
        $systems['bi']['prefix'] = ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB'];
        $systems['bi']['size'] = 1024;
        $sys = isset($systems[$system]) ? $systems[$system] : $systems['si'];

        // Max unit to display
        $depth = count($sys['prefix']) - 1;
        if ($max && false !== $d = array_search($max, $sys['prefix'])) {
            $depth = $d;
        }

        // Loop
        $i = 0;
        while ($size >= $sys['size'] && $i < $depth) {
            $size /= $sys['size'];
            $i++;
        }
        if ($sys['prefix'][$i] == '') {
            $retstring = '%01u %s';
        }

        return sprintf($retstring, $size, $sys['prefix'][$i]);
    }

    /**
     * Affiche la liste des fichiers, modifiee pour utilisation dans une action {{filemanager}}.
     */
    public function fmShowAction($trash = false)
    {
        $this->fmShow($trash, true);
    }

    /**
     * Affiche la liste des fichiers.
     */
    public function fmShow($trash = false, bool $isAction = false)
    {
        $method = ($this->wiki->GetMethod() != 'show' ? $this->wiki->GetMethod() : '');

        $files = $this->fmGetFiles($trash);
        if (is_array($files)) {
            $files = $this->sortByNameRevFile($files);
            $files = array_map(function ($file) {
                return array_merge($file, [
                    'parsedTrashDate' => isset($file['trashdate']) ? $this->parseDate($file['trashdate']) : '',
                    'parsedDateUpload' => isset($file['dateupload']) ? $this->parseDate($file['dateupload']) : '',
                    'readableSize' => isset($file['size']) ? $this->size_readable($file['size']) : '',
                ]);
            }, $files);
        }
        echo $this->wiki->render($isAction
            ? '@core/attach-filemanager.twig'
            : '@core/attach-filemanager-handler.twig', [
                'tag' => $this->wiki->tag,
                'method' => ($this->wiki->GetMethod() != 'show' ? $this->wiki->GetMethod() : ''),
                'trash' => $trash,
                'files' => $files,
            ]);
    }

    /**
     * Renvoie la liste des fichiers.
     */
    public function fmGetFiles($trash = false)
    {
        $path = $this->GetUploadPath();
        if ($this->isSafeMode) {
            $filePattern = '^' . $this->wiki->GetPageTag() . '_.*_\d{14}_\d{14}\..*';
        } else {
            $filePattern = '^.*_\d{14}_\d{14}\..*';
        }
        if ($trash) {
            $filePattern .= 'trash\d{14}';
        } else {
            $filePattern .= '[^(trash\d{14})]';
        }

        return $this->searchFiles('`' . $filePattern . '$`', $path);
    }

    /**
     * Vide la corbeille.
     */
    public function fmEmptyTrash()
    {
        $files = $this->fmGetFiles(true);
        foreach ($files as $file) {
            $filename = $file['path'] . '/' . $file['realname'];
            if (file_exists($filename)) {
                unlink($filename);
            }
        }
    }

    /**
     * Effacement d'un fichier dans la corbeille.
     */
    public function fmErase()
    {
        $path = $this->GetUploadPath();
        // Sanitize file path
        $filename = $this->GetUploadPath() . '/' . basename(realpath($_GET['file'] ? $_GET['file'] : ''));
        // Make sure that the filename ends with trash and a date
        if (file_exists($filename) && preg_match('/trash\d{14}$/', $filename)) {
            unlink($filename);
        }
    }

    /**
     * Met le fichier a la corbeille.
     */
    public function fmDelete(string $rawFileName = '')
    {
        $path = $this->GetUploadPath();
        $rawFileName = empty($rawFileName)
            ? $this->wiki->services->get(InputFilter::class)->filterInput(INPUT_GET, 'file', FILTER_SANITIZE_FULL_SPECIAL_CHARS, false, 'string')
            : $rawFileName;
        $filename = $path . '/' . basename($rawFileName);
        if (!empty($rawFileName) && file_exists($filename)) {
            $trash = $filename . 'trash' . $this->getDate();
            rename($filename, $trash);

            // delete cache files
            $cachePath = $this->GetCachePath();
            $fileInfo = $this->decodeLongFilename($filename);

            $filenamesToDelete = [];
            // vignettes
            $filenamesToDelete[] = $this->getResizedFilename($filename, '[0-9][0-9][0-9]', '[0-9][0-9][0-9]', 'fit');
            $filenamesToDelete[] = $this->getResizedFilename($filename, '[0-9][0-9][0-9][0-9]', '[0-9][0-9][0-9]', 'fit');
            $filenamesToDelete[] = $this->getResizedFilename($filename, '[0-9][0-9][0-9]', '[0-9][0-9][0-9][0-9]', 'fit');
            $filenamesToDelete[] = $this->getResizedFilename($filename, '[0-9][0-9][0-9][0-9]', '[0-9][0-9][0-9][0-9]', 'fit');
            $filenamesToDelete[] = $this->getResizedFilename($filename, '[0-9][0-9][0-9]', '[0-9][0-9][0-9]', 'crop');
            $filenamesToDelete[] = $this->getResizedFilename($filename, '[0-9][0-9][0-9][0-9]', '[0-9][0-9][0-9]', 'crop');
            $filenamesToDelete[] = $this->getResizedFilename($filename, '[0-9][0-9][0-9]', '[0-9][0-9][0-9][0-9]', 'crop');
            $filenamesToDelete[] = $this->getResizedFilename($filename, '[0-9][0-9][0-9][0-9]', '[0-9][0-9][0-9][0-9]', 'crop');
            // old Image Field
            $filenamesToDelete[] = $cachePath . '/vignette_' . basename($filename);
            $filenamesToDelete[] = $cachePath . '/image_' . basename($filename);
            // old agenda.tpl.html|blog.tpl.html|damier.tpl.html|materiel-card.tpl.html|news.tpl.html|photobox.tpl.html|trombinoscope.tpl.html
            $filenamesToDelete[] = $cachePath . '/image_[0-9][0-9][0-9][x_][0-9][0-9][0-9]_' . basename($filename);
            $filenamesToDelete[] = $cachePath . '/image_[0-9][0-9][0-9][x_][0-9][0-9][0-9][0-9]_' . basename($filename);
            $filenamesToDelete[] = $cachePath . '/image_[0-9][0-9][0-9][0-9][x_][0-9][0-9][0-9]_' . basename($filename);
            $filenamesToDelete[] = $cachePath . '/image_[0-9][0-9][0-9][0-9][x_][0-9][0-9][0-9][0-9]_' . basename($filename);
            // old templates.functions.php getImageFromBody
            $filenamesToDelete[] = $cachePath . '/[0-9][0-9][0-9]x[0-9][0-9][0-9]-' . basename($filename);
            $filenamesToDelete[] = $cachePath . '/[0-9][0-9][0-9][0-9]x[0-9][0-9][0-9]-' . basename($filename);
            $filenamesToDelete[] = $cachePath . '/[0-9][0-9][0-9]x[0-9]0-9][0-9][0-9]-' . basename($filename);
            $filenamesToDelete[] = $cachePath . '/[0-9][0-9][0-9][0-9]x[0-9]0-9][0-9][0-9]-' . basename($filename);
            foreach ($filenamesToDelete as $path) {
                array_map('unlink', glob($path));
            }
        }
    }

    /**
     * Restauration d'un fichier mis a la corbeille.
     */
    public function fmRestore()
    {
        $path = $this->GetUploadPath();
        $filename = $path . '/' . ($_GET['file'] ? $_GET['file'] : '');
        if (file_exists($filename)) {
            $restFile = preg_replace('`^(.*\..*)trash\d{14}$`', '$1', $filename);
            rename($filename, $restFile);
        }
    }

    /**
     * Tri tu tableau liste des fichiers par nom puis par date de revision(upload) du fichier, ordre croissant.
     */
    public function sortByNameRevFile($files)
    {
        if (!function_exists('ByNameByRevFile')) {
            function ByNameByRevFile($f1, $f2)
            {
                $f1Name = $f1['name'] . '.' . $f1['ext'];
                $f2Name = $f2['name'] . '.' . $f2['ext'];
                $res = strcasecmp($f1Name, $f2Name);
                if ($res == 0) {
                    // si meme nom => compare la revision du fichier
                    $res = strcasecmp($f1['dateupload'], $f2['dateupload']);
                }

                return $res;
            }
        }
        usort($files, 'ByNameByRevFile');

        return $files;
    }

    public function calculer_nom_fichier_vignette($fullFilename, $width, $height)
    {
        $file = $this->decodeLongFilename($fullFilename);
        if (!empty($file['name'])) {
            if ($this->isSafeMode) {
                $currentTag = $this->wiki->GetPageTag();
                $prefixFileName = substr($file['realname'], 0, strlen($currentTag)) == $currentTag ? $currentTag . '_' : '';
                $file_vignette = $file['path'] . '/' . $prefixFileName . $file['name'] . '_vignette_' . $width . '_' . $height . '_' . $file['datepage'] . '_' . $file['dateupload'] . '.' . $file['ext'];
            } else {
                $file_vignette = $file['path'] . '/' . $file['name'] . '_vignette_' . $width . '_' . $height . '_' . $file['datepage'] . '_' . $file['dateupload'] . '.' . $file['ext'];
            }
        } else {
            $pathInfo = pathinfo($fullFilename);
            $file_vignette = "{$file['path']}/{$pathInfo['filename']}_vignette_{$width}_{$height}" . (isset($pathInfo['extension']) ? ".{$pathInfo['extension']}" : '');
        }

        return $file_vignette;
    }

    public function getResizedFilename($fullFilename, $width, $height, string $mode = 'fit')
    {
        $uploadPath = $this->GetUploadPath();
        $cachePath = $this->GetCachePath();
        $newFileName = preg_replace("/^$uploadPath/", "$cachePath", $fullFilename);
        $newFileName = $this->calculer_nom_fichier_vignette($newFileName, $width, $height);
        if ($mode == 'crop') {
            $newFileName = preg_replace('/_vignette_/', '_cropped_', $newFileName);
        }

        return $newFileName;
    }

    public function redimensionner_image($image_src, $image_dest, $largeur, $hauteur, $mode = 'fit')
    {
        if (empty($image_src) || empty($image_dest)) {
            return false;
        }
        $imgTrans = new Zebra_Image();
        $imgTrans->auto_handle_exif_orientation = true;
        $imgTrans->preserve_aspect_ratio = true;
        $imgTrans->enlarge_smaller_images = true;
        $imgTrans->preserve_time = true;
        $imgTrans->auto_handle_exif_orientation = true;
        $imgTrans->source_path = $image_src;
        $imgTrans->target_path = $image_dest;

        // Zebra_Image still calls imagedestroy(), a no-op deprecated since PHP 8.5.
        // Silence it here (rather than patching vendor/) so debug-mode deprecation
        // output isn't echoed into an in-progress image response.
        $previousErrorReporting = error_reporting();
        error_reporting($previousErrorReporting & ~E_DEPRECATED);
        try {
            if ($mode == 'crop') {
                $wantedRatio = $largeur / $hauteur;
                // get image info except for webp (code copier from Zebra_Image)
                if (
                    !(
                        version_compare(PHP_VERSION, '7.0.0') >= 0
                        && version_compare(PHP_VERSION, '7.1.0') < 0
                        && (
                            $imgTrans->source_type = strtolower(substr($imgTrans->source_path, strrpos($imgTrans->source_path, '.') + 1))
                        ) === 'webp'
                    )
                    && !list($sourceImageWidth, $sourceImageHeight, $sourceImageType) = @getimagesize($imgTrans->source_path)
                ) {
                    return false;
                }
                $imageRatio = $sourceImageWidth / $sourceImageHeight;

                if ($imageRatio != $wantedRatio) {
                    if ($imageRatio > $wantedRatio) {
                        // width too large, keep height
                        $newWidth = round($sourceImageHeight * $wantedRatio);
                        $newHeight = $sourceImageHeight;
                    } else {
                        // height too large, keep width
                        $newHeight = round($sourceImageWidth / $wantedRatio);
                        $newWidth = $sourceImageWidth;
                    }
                    // crop
                    $ext = pathinfo($image_src)['extension'];
                    do {
                        $tempFile = tmpfile();
                        $tempFileName = stream_get_meta_data($tempFile)['uri'] . ".$ext";
                        unlink(stream_get_meta_data($tempFile)['uri']);
                    } while (file_exists($tempFileName));
                    $imgTrans->target_path = $tempFileName;
                    if ($imgTrans->resize(intval($newWidth), intval($newHeight), ZEBRA_IMAGE_CROP_CENTER, -1)) {
                        $imgTrans->source_path = $tempFileName;
                    }
                    $imgTrans->target_path = $image_dest;
                }
            }
            $result = $imgTrans->resize(intval($largeur), intval($hauteur), ZEBRA_IMAGE_NOT_BOXED, -1);
        } finally {
            error_reporting($previousErrorReporting);
        }

        if ($mode == 'crop' && !empty($tempFileName) && file_exists($tempFileName)) {
            unlink($tempFileName);
        }
        if (!$result) {
            // in case of error, show error code
            return $imgTrans->error;
            // if there were no errors
        }

        return $imgTrans->target_path;
    }
}
