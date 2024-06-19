<?php

// Classe de gestion de l'action {{attach}}

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Core\Service\HtmlPurifierService;
use YesWiki\Core\Service\LinkTracker;
use YesWiki\Security\Controller\SecurityController;

if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}

if (!class_exists('attach')) {
    class attach
    {
        public $wiki = ''; //objet wiki courant
        public $attachConfig = []; //configuration de l'action
        public $file = ''; //nom du fichier
        public $height;
        public $width;
        public $desc = ''; //description du fichier
        public $link = ''; //url de lien (image sensible)
        public $caption = ''; //texte de la vignette au survol
        public $legend = ''; //texte en dessous de l'image
        public $nofullimagelink = ''; //mettre un lien vers l'image entiere
        public $isPicture = 0; //indique si c'est une image
        public $isAudio = 0; //indique si c'est un fichier audio
        public $isFreeMindMindMap = 0; //indique si c'est un fichier mindmap freemind
        public $isWma = 0; //indique si c'est un fichier wma
        public $isPDF = 0; //indique si c'est un fichier pdf
        public $displayPDF = 0; //indique s'il faut afficher le fichier pdf
        public $classes = 'attached_file'; //classe pour afficher une image
        public $attachErr = ''; //message d'erreur
        public $pageId = 0; //identifiant de la page
        public $isSafeMode = true; //indicateur du safe mode de PHP
        public $data = ''; //indicateur du safe mode de PHP
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
                throw new Exception('attach_config should be an array in wakka.config.php');
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
            } else {
                return round($size);
            }
        }

        /**
         * Cr&eacute;ation d'une suite de r&eacute;pertoires r&eacute;cursivement.
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
         * Calcule le nom complet du fichier attach&eacute; en fonction du safe_mode, du nom et de la date de
         * revision la page courante.
         * Le nom du fichier "mon fichier.ext" attache ? la page "LaPageWiki"sera :
         *  mon_fichier_datepage_update.ext
         *     update : date de derniere mise a jour du fichier
         *     datepage : date de revision de la page ? laquelle le fichier a ete li&eacute;/mis a jour
         *  Si le fichier n'est pas une image un '_' est ajoute : mon_fichier_datepage_update.ext_
         *  Selon la valeur de safe_mode :
         *  safe_mode = on :     LaPageWiki_mon_fichier_datepage_update.ext_
         *  safe_mode = off:     LaPageWiki/mon_fichier_datepage_update.ext_ avec "LaPageWiki" un sous-repertoire du r&eacute;pertoire upload.
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

            //decompose le nom du fichier en nom+extension ou en page/nom+extension
            if (preg_match('`^((.+)/)?(.*)\.(.*)$`', str_replace(' ', '_', $this->file), $match)) {
                list(, , $file['page'], $file['name'], $file['ext']) = $match;
                if (!$this->isPicture() && !$this->isAudio() && !$this->isVideo() && !$this->isFreeMindMindMap() && !$this->isWma() && !$this->isFlashvideo()) {
                    $file['ext'] .= '_';
                }
            } else {
                return false;
            }
            //recuperation du chemin d'upload
            $path = $this->GetUploadPath($this->isSafeMode);
            $page_tag = $file['page'] ? $file['page'] : $this->wiki->GetPageTag();
            //generation du nom ou recherche de fichier ?
            if ($newName) {
                $full_file_name = $file['name'] . '_' . $pagedate . '_' . $this->getDate() . '.' . $file['ext'];
                if ($this->isSafeMode) {
                    $full_file_name = $path . '/' . $page_tag . '_' . $full_file_name;
                } else {
                    $full_file_name = $path . '/' . $full_file_name;
                }
            } else {
                $isActionBuilderPreview = $this->wiki->GetPageTag() == 'root';
                //recherche du fichier
                if ($isActionBuilderPreview) {
                    // bazar action builder, preview action
                    $searchPattern = '`' . $file['name'] . '_\d{14}_\d{14}\.' . $file['ext'] . '$`';
                } elseif ($this->isSafeMode) {
                    //TODO Recherche dans le cas ou safe_mode=on
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

        /**
         * Test si le fichier est un fichier freemind mind map.
         */
        public function isFreeMindMindMap()
        {
            return preg_match('/.(' . $this->attachConfig['ext_freemind'] . ')$/i', $this->file) == 1;
        }

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
                //list(,$res['year'],$res['month'],$res['day'],$res['hour'],$res['min'],$res['sec'])=$m;
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
                //suppression du nom de la page si safe_mode=on
                if ($this->isSafeMode) {
                    $afile['name'] = preg_replace('`^(' . $this->wiki->tag . ')_(.*)$`i', '$2', $afile['name']);
                }
                $afile['datepage'] = $m[2];
                $afile['dateupload'] = $m[3];
                $afile['trashdate'] = preg_replace('`(.*)trash(\d{14})`', '$2', $m[4]);
                //suppression de trashxxxxxxxxxxxxxx eventuel
                $afile['ext'] = preg_replace('`^(.*)(trash\d{14})$`', '$1', $m[4]);
                $afile['ext'] = rtrim($afile['ext'], '_');
                //$afile['ext'] = rtrim($m[4],'_');
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
            //recuperation des parametres necessaire
            $this->file = $this->wiki->GetParameter('attachfile');
            if (empty($this->file)) {
                $this->file = $this->wiki->GetParameter('file');
            }

            $this->desc = $this->wiki->GetParameter('attachdesc');
            if (empty($this->desc)) {
                $this->desc = $this->wiki->GetParameter('desc');
            }
            $this->desc = htmlentities(strip_tags($this->desc)); // avoid XSS

            $this->link = $this->wiki->GetParameter('attachlink'); //url de lien - uniquement si c'est une image
            if (empty($this->link)) {
                $this->link = $this->wiki->GetParameter('link');
            }

            $this->caption = $this->wiki->GetParameter('caption'); //texte de la vignette (au survol)
            $this->legend = $this->wiki->GetParameter('legend'); //texte de la vignette (en dessous)
            $this->nofullimagelink = $this->wiki->GetParameter('nofullimagelink');
            $this->height = $this->wiki->GetParameter('height');
            $this->width = $this->wiki->GetParameter('width');
            $this->displayPDF = $this->wiki->GetParameter('displaypdf');
            $this->data = $this->wiki->services->get(\YesWiki\Templates\Service\Utils::class)->getDataParameter();

            //test de validit&eacute; des parametres
            if (empty($this->file)) {
                $this->attachErr = '<div class="alert alert-danger"><strong>' . _t('ATTACH_ACTION_ATTACH') . '</strong> : ' . _t('ATTACH_PARAM_FILE_NOT_FOUND') . '.</div>' . "\n";
            }
            if ($this->isPicture() && empty($this->desc)) {
                $this->attachErr = '<div class="alert alert-danger"><strong>' . _t('ATTACH_ACTION_ATTACH') . '</strong> : ' . _t('ATTACH_PARAM_DESC_REQUIRED') . '.</div>' . "\n";
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
         * Affiche le fichier li&eacute; comme une image.
         */
        public function showAsImage($fullFilename)
        {
            // Generation d'une vignette si absente ou si changement de dimension  , TODO : suupprimer ancienne vignette ?

            $image_redimensionnee = 0;
            if (!preg_match('/.(svg)$/i', $this->file) == 1) {
                if ((!empty($this->height)) && (!empty($this->width))) {
                    // Si des parametres width ou height present : redimensionnement
                    if (!file_exists($image_dest = $this->getResizedFilename($fullFilename, $this->width, $this->height))) {
                        $this->redimensionner_image($fullFilename, $image_dest, $this->width, $this->height);
                    }
                    $img_name = $image_dest;
                } else {
                    $img_name = $fullFilename;
                }
                list($width, $height, $type, $attr) = getimagesize($img_name);
            } else {
                // valeurs par défaut pour le svg
                $width = $this->width;
                $height = $this->height;
                $img_name = $fullFilename;
            }
            // pour l'image avec bordure on enleve la taille de la bordure!
            if (strstr($this->classes, 'whiteborder')) {
                $width = $width - 20;
                $height = $height - 20;
            }

            //c'est une image : balise <IMG..../>
            $img = '<img loading="lazy" class="img-responsive" src="' . $this->GetScriptPath() . $img_name . '" ' .
            'alt="' . $this->desc . ($this->link ? "\nLien vers: $this->link" : '') . '" width="' . $width . '" height="' . $height . '" />';
            //test si c'est une image sensible
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
                    $link = '<a href="' . $this->GetScriptPath() . $fullFilename . '"' . $classDataForLinks . '>';
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
            //$this->showUpdateLink();
        }

        /**
         * Affiche le fichier li&eacute; comme un lien.
         */
        public function showAsLink($fullFilename)
        {
            $url = $this->wiki->href('download', $this->wiki->GetPageTag(), "file=$this->file");
            echo '<a href="' . $url . '">' . ($this->desc ? $this->desc : $this->file) . '</a>';
            $this->showUpdateLink();
        }

        // Affiche le fichier liee comme un fichier video
        public function showAsVideo($fullFilename)
        {
            $output = $this->wiki->format(
                '{{player url="' . $this->wiki->getBaseUrl() . '/' . $fullFilename . '" type="video" ' .
                'height="' . (!empty($height) ? $height : '300px') . '" ' .
                'width="' . (!empty($width) ? $width : '400px') . '"}}'
            );
            echo $output;
            $this->showUpdateLink();
        }

        // Affiche le fichier liee comme un fichier audio
        public function showAsAudio($fullFilename)
        {
            $output = $this->wiki->format('{{player url="' . $this->wiki->getBaseUrl() . '/' . $fullFilename . '" type="audio"}}');
            echo $output;
            $this->showUpdateLink();
        }

        // Affiche le fichier liee comme un fichier mind map  freemind
        public function showAsFreeMindMindMap($fullFilename)
        {
            $output = $this->wiki->format(
                '{{player url="' . $this->wiki->getBaseUrl() . '/' . $fullFilename . '" ' .
                'height="' . (!empty($height) ? $height : '650px') . '" ' .
                'width="' . (!empty($width) ? $width : '100%') . '"}}'
            );
            echo $output;
            $this->showUpdateLink();
        }

        // Affiche le fichier liee comme un fichier mind map  freemind
        public function showAsWma($fullFilename)
        {
        }

        // End Paste

        // Affiche le fichier liee comme un fichier pdf
        public function showAsPDF($fullFilename)
        {
            // Defines parameters for pdf action
            // remove '?' and following
            $base_url = explode('?', $this->wiki->config['base_url'])[0];
            $url = $base_url . $fullFilename;
            $this->wiki->setParameter('url', $url);
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

        /**
         * Affiche le lien de mise a jour.
         */
        public function showUpdateLink()
        {
            echo ' <a href="' .
            $this->wiki->href('upload', $this->wiki->GetPageTag(), "file=$this->file") .
            "\" title='Mise &agrave; jour'>" . $this->attachConfig['update_symbole'] . '</a>';
        }

        /**
         * Affiche un liens comme un fichier inexistant.
         */
        public function showFileNotExits()
        {
            echo '<a href="' . $this->wiki->href('upload', $this->wiki->GetPageTag(), "file=$this->file") . '" class="btn btn-primary"><i class="fa fa-upload icon-upload icon-white"></i> ' . _t('UPLOAD_FILE') . ' ' . $this->file . '</a>';
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
            $fullFilename = $this->GetFullFilename();
            //test d'existance du fichier
            if ((!file_exists($fullFilename)) || ($fullFilename == '')) {
                $this->showFileNotExits();

                return;
            }
            //le fichier existe : affichage en fonction du type
            if ($this->isPicture()) {
                $this->showAsImage($fullFilename);
            } elseif ($this->isVideo() || $this->isFlashvideo()) {
                $this->showAsVideo($fullFilename);
            } elseif ($this->isAudio()) {
                $this->showAsAudio($fullFilename);
            } elseif ($this->isFreeMindMindMap()) {
                $this->showAsFreeMindMindMap($fullFilename);
            } elseif ($this->isWma()) {
                $this->showAsWma($fullFilename);
            } elseif ($this->isPDF() && $this->displayPDF) {
                $this->showAsPDF($fullFilename);
            } else {
                $this->showAsLink($fullFilename);
            }
        }

        /******************************************************************************
         *    FONTIONS D'UPLOAD DE FICHIERS
         *******************************************************************************/
        /**
         * Traitement des uploads.
         */
        public function doUpload()
        {
            $HasAccessWrite = $this->wiki->HasAccess('write');
            if ($HasAccessWrite) {
                switch ($_SERVER['REQUEST_METHOD']) {
                    case 'GET':
                        $this->showUploadForm();
                        break;
                    case 'POST':
                        $this->performUpload();
                        break;
                    default:
                        echo '<div class="alert alert-error alert-danger">' . _t('INVALID_REQUEST_METHOD') . "</div>\n";
                }
            } else {
                echo '<div class="alert alert-error alert-danger">' . _t('NO_RIGHT_TO_WRITE_IN_THIS_PAGE') . "</div>\n";
                echo $this->wiki->Format(_t('ATTACH_BACK_TO_PAGE') . ' ' . $this->wiki->GetPageTag());
            }
        }

        /**
         * Formulaire d'upload.
         */
        public function showUploadForm()
        {
            $this->file = $_GET['file'];
            echo '<h3>' . _t('ATTACH_UPLOAD_FORM_FOR_FILE') . ' ' . $this->file . "</h3>\n";
            echo '<form enctype="multipart/form-data" name="frmUpload" method="POST" action="' . $this->wiki->href('upload', $this->wiki->GetPageTag()) . "\">\n"
            . '	<input type="hidden" name="wiki" value="' . $this->wiki->GetPageTag() . "/upload\" />\n"
            . '	<input type="hidden" name="MAX_FILE_SIZE" value="' . $this->attachConfig['max_file_size'] . "\" />\n"
            . "	<input type=\"hidden\" name=\"file\" value=\"$this->file\" />\n"
            . "	<input type=\"file\" name=\"upFile\" size=\"50\" /><br />\n"
            . '	<input class="btn btn-primary" type="submit" value="' . _t('ATTACH_SAVE') . "\" />\n"
                . "</form>\n";
        }

        /**
         * Execute l'upload.
         */
        public function performUpload()
        {
            $this->file = $_POST['file'];
            $pathinfo = pathinfo($this->file);
            $ext = strtolower($pathinfo['extension']);
            if ($this->wiki->config['authorized-extensions'] && !in_array($ext, array_keys($this->wiki->config['authorized-extensions']))) {
                $_FILES['upFile']['error'] = 5;
            }
            $destFile = $this->GetFullFilename(true); //nom du fichier destination
            //test de la taille du fichier recu
            if ($_FILES['upFile']['error'] == 0) {
                $size = filesize($_FILES['upFile']['tmp_name']);
                if ($size > $this->attachConfig['max_file_size']) {
                    $_FILES['upFile']['error'] = 2;
                }
            }
            switch ($_FILES['upFile']['error']) {
                case 0:
                    $srcFile = $_FILES['upFile']['tmp_name'];
                    if (move_uploaded_file($srcFile, $destFile)) {
                        chmod($destFile, 0644);
                        if ($ext === 'svg' || $ext === 'xml') {
                            $purifier = $this->wiki->services->get(HtmlPurifierService::class);
                            $purifier->cleanFile($destFile, $ext);
                        }
                        header('Location: ' . $this->wiki->href('', $this->wiki->GetPageTag(), ''));
                    } else {
                        echo '<div class="alert alert-error alert-danger">' . _t('ERROR_MOVING_TEMPORARY_FILE') . "</div>\n";
                    }
                    break;
                case 1:
                    echo '<div class="alert alert-error alert-danger">' . _t('ERROR_UPLOAD_MAX_FILESIZE') . "</div>\n";
                    break;
                case 2:
                    echo '<div class="alert alert-error alert-danger">' . _t('ERROR_MAX_FILE_SIZE') . "</div>\n";
                    break;
                case 3:
                    echo '<div class="alert alert-error alert-danger">' . _t('ERROR_PARTIAL_UPLOAD') . "</div>\n";
                    break;
                case 4:
                    echo '<div class="alert alert-error alert-danger">' . _t('ERROR_NO_FILE_UPLOADED') . "</div>\n";
                    break;
                case 5:
                    $t = [];
                    foreach ($this->wiki->config['authorized-extensions'] as $ext => $des) {
                        $t[] = $ext . ' (' . $des . ')';
                    }
                    $these = implode(', ', $t);
                    echo '<div class="alert alert-error alert-danger">' . _t('ERROR_NOT_AUTHORIZED_EXTENSION') . $these . '.</div>';
                    break;
            }
            echo $this->wiki->Format(_t('ATTACH_BACK_TO_PAGE') . ' ' . $this->wiki->GetPageTag());
        }

        /******************************************************************************
         *    FUNCTIONS DE DOWNLOAD DE FICHIERS
         *******************************************************************************/
        public function doDownload()
        {
            $this->file = $_GET['file'];
            $fullFilename = $this->GetUploadPath() . '/' . basename(realpath($this->file) . $this->file);
            //        $fullFilename = $this->GetUploadPath().'/'.$this->file;
            if (!file_exists($fullFilename)) {
                $fullFilename = $this->GetFullFilename();
                $dlFilename = $this->file;
                $size = filesize($fullFilename);
            } else {
                $file = $this->decodeLongFilename($fullFilename);
                $size = $file['size'];
                $dlFilename = $file['name'] . '.' . $file['ext'];
            }
            try {
                header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
                header('Content-type: application/force-download');
                header('Pragma: public');
                header('Pragma: no-cache'); // HTTP/1.0
                header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
                header('Cache-Control: no-store, no-cache, must-revalidate'); // HTTP/1.1
                header('Cache-Control: pre-check=0, post-check=0, max-age=0'); // HTTP/1.1
                header('Content-Transfer-Encoding: none');
                header('Content-Type: application/octet-stream; name="' . $dlFilename . '"'); //This should work for the rest
                header('Content-Type: application/octetstream; name="' . $dlFilename . '"'); //This should work for IE & Opera
                if (in_array(preg_replace("/^.*\.([^.]+$)/", '$1', $dlFilename), ['txt', 'md', 'png', 'svg', 'jpeg', 'jpg', 'mp3'])) {
                    header('Content-Type: ' . mime_content_type($fullFilename) . '; name="' . $dlFilename . '"');
                }
                header('Content-Disposition: attachment; filename="' . $dlFilename . '"');
                header('Content-Description: File Transfer');
                header("Content-length: $size");
                readfile($fullFilename);
            } catch (Throwable $th) {
                if (!headers_sent()) {
                    header_remove(null);
                    header('HTTP/1.0 500 Internal Server Error');
                    header('Content-Disposition: inline;', true);
                    header('Content-Type: text/html', true);
                }
                throw $th;
            }
        }

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
                    $this->fmEmptyTrash(); //pas de break car apres un emptytrash => retour au gestionnaire
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
                ? '@attach/attach-filemanager.twig'
                : '@attach/attach-filemanager-handler.twig', [
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
            $filename = $path . '/' . ($_GET['file'] ? $_GET['file'] : '');
            if (file_exists($filename)) {
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
                ? $this->wiki->services->get(SecurityController::class)->filterInput(INPUT_GET, 'file', FILTER_SANITIZE_FULL_SPECIAL_CHARS, false, 'string')
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
                        //si meme nom => compare la revision du fichier
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

            if ($mode == 'crop') {
                $wantedRatio = $largeur / $hauteur;
                // get image info except for webp (code copier from Zebra_Image)
                if (
                    !(
                        version_compare(PHP_VERSION, '7.0.0') >= 0 &&
                        version_compare(PHP_VERSION, '7.1.0') < 0 &&
                        (
                            $imgTrans->source_type = strtolower(substr($imgTrans->source_path, strrpos($imgTrans->source_path, '.') + 1))
                        ) === 'webp'
                    ) &&
                    !list($sourceImageWidth, $sourceImageHeight, $sourceImageType) = @getimagesize($imgTrans->source_path)
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

            if ($mode == 'crop' && !empty($tempFileName) && file_exists($tempFileName)) {
                unlink($tempFileName);
            }
            if (!$result) {
                // in case of error, show error code
                return $imgTrans->error;
            // if there were no errors
            } else {
                return $imgTrans->target_path;
            }
        }
    }
}
