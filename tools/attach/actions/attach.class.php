<?php
/*
attach.class.php
Code original de ce fichier : Eric FELDSTEIN
Copyright (c) 2002, Hendrik Mans <hendrik@mans.de>
Copyright 2002, 2003 David DELON
Copyright 2002, 2003 Charles NEPOTE
Copyright  2003,2004  Eric FELDSTEIN
Copyright  2003  Jean-Pascal MILCENT
All rights reserved.
Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions
are met:
1. Redistributions of source code must retain the above copyright
notice, this list of conditions and the following disclaimer.
2. Redistributions in binary form must reproduce the above copyright
notice, this list of conditions and the following disclaimer in the
documentation and/or other materials provided with the distribution.
3. The name of the author may not be used to endorse or promote products
derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE AUTHOR ``AS IS'' AND ANY EXPRESS OR
IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES
OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT,
INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */
# Classe de gestion de l'action {{attach}}
# voir actions/attach.php ppour la documentation
# copyrigth Eric Feldstein 2003-2004

if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

if (!class_exists('attach')) {
    class attach
    {
        public $wiki = ''; //objet wiki courant
        public $attachConfig = array(); //configuration de l'action
        public $file = ''; //nom du fichier
        public $desc = ''; //description du fichier
        public $link = ''; //url de lien (image sensible)
        public $caption = ''; //texte de la vignette au survol
        public $legend = ''; //texte en dessous de l'image
        public $nofullimagelink = ''; //mettre un lien vers l'image entiere
        public $isPicture = 0; //indique si c'est une image
        public $isAudio = 0; //indique si c'est un fichier audio
        public $isFreeMindMindMap = 0; //indique si c'est un fichier mindmap freemind
        public $isWma = 0; //indique si c'est un fichier wma
        public $classes = 'attached_file'; //classe pour afficher une image
        public $attachErr = ''; //message d'erreur
        public $pageId = 0; //identifiant de la page
        public $isSafeMode = true; //indicateur du safe mode de PHP
        /**
         * Constructeur. Met les valeurs par defaut aux parametres de configuration
         */
        public function __construct(&$wiki)
        {
            $this->wiki = $wiki;
            $this->attachConfig = $this->wiki->GetConfigValue("attach_config");

            if (!is_array($this->attachConfig)) {
                $this->attachConfig = array();
            }

            if (empty($this->attachConfig["ext_images"])) {
                $this->attachConfig["ext_images"] = "gif|jpeg|png|jpg|svg";
            }

            if (empty($this->attachConfig["ext_audio"])) {
                $this->attachConfig["ext_audio"] = "mp3";
            }

            if (empty($this->attachConfig["ext_wma"])) {
                $this->attachConfig["ext_wma"] = "wma";
            }

            if (empty($this->attachConfig["ext_freemind"])) {
                $this->attachConfig["ext_freemind"] = "mm";
            }

            if (empty($this->attachConfig["ext_flashvideo"])) {
                $this->attachConfig["ext_flashvideo"] = "flv";
            }

            if (empty($this->attachConfig["ext_script"])) {
                $this->attachConfig["ext_script"] = "php|php3|asp|asx|vb|vbs|js";
            }

            if (empty($this->attachConfig['upload_path'])) {
                $this->attachConfig['upload_path'] = 'files';
            }

            if (empty($this->attachConfig['update_symbole'])) {
                $this->attachConfig['update_symbole'] = '';
            }

            if (empty($this->attachConfig['max_file_size'])) {
                $this->attachConfig['max_file_size'] = 1024 * 8000;
            }
            //8000ko max
            if (empty($this->attachConfig['fmDelete_symbole'])) {
                $this->attachConfig['fmDelete_symbole'] = 'Supr';
            }

            if (empty($this->attachConfig['fmRestore_symbole'])) {
                $this->attachConfig['fmRestore_symbole'] = 'Rest';
            }

            if (empty($this->attachConfig['fmTrash_symbole'])) {
                $this->attachConfig['fmTrash_symbole'] = 'Corbeille';
            }

            $safemode = $this->wiki->GetConfigValue("no_safe_mode");
            if (empty($safemode)) {
                if (version_compare(phpversion(), '5.3', '<')) {
                    // le safe_mode n'existe que pour php < 5.3
                    $this->isSafeMode = ini_get("safe_mode");
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
         * Cr&eacute;ation d'une suite de r&eacute;pertoires r&eacute;cursivement
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

            return ($this->mkdir_recursif(dirname($dir)) and mkdir($dir, 0755));
        }
        /**
         * Renvois le chemin du script
         */
        public function GetScriptPath()
        {
            if (preg_match("/.(php)$/i", $_SERVER["PHP_SELF"])) {
                $a = explode('/', $_SERVER["PHP_SELF"]);
                $a[count($a) - 1] = '';
                $path = implode('/', $a);
            } else {
                $path = $_SERVER["PHP_SELF"];
            }
            $http = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://');
            return !empty($_SERVER["HTTP_HOST"]) ?
                $http . $_SERVER["HTTP_HOST"] . $path
                : $http . $_SERVER["SERVER_NAME"] . $path;
        }
        /**
         * Calcul le repertoire d'upload en fonction du safe_mode
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
         * Calcule le nom complet du fichier attach&eacute; en fonction du safe_mode, du nom et de la date de
         * revision la page courante.
         * Le nom du fichier "mon fichier.ext" attache ? la page "LaPageWiki"sera :
         *  mon_fichier_datepage_update.ext
         *     update : date de derniere mise a jour du fichier
         *     datepage : date de revision de la page ? laquelle le fichier a ete li&eacute;/mis a jour
         *  Si le fichier n'est pas une image un '_' est ajoute : mon_fichier_datepage_update.ext_
         *  Selon la valeur de safe_mode :
         *  safe_mode = on :     LaPageWiki_mon_fichier_datepage_update.ext_
         *  safe_mode = off:     LaPageWiki/mon_fichier_datepage_update.ext_ avec "LaPageWiki" un sous-repertoire du r&eacute;pertoire upload
         */
        public function GetFullFilename($newName = false)
        {
            $pagedate = $this->convertDate($this->wiki->page['time']);
            //decompose le nom du fichier en nom+extension
            if (preg_match('`^(.*)\.(.*)$`', str_replace(' ', '_', $this->file), $match)) {
                list(, $file['name'], $file['ext']) = $match;
                if (!$this->isPicture() && !$this->isAudio() && !$this->isFreeMindMindMap() && !$this->isWma() && !$this->isFlashvideo()) {
                    $file['ext'] .= '_';
                }

            } else {
                return false;
            }
            //recuperation du chemin d'upload
            $path = $this->GetUploadPath($this->isSafeMode);
            //generation du nom ou recherche de fichier ?
            if ($newName) {
                $full_file_name = $file['name'] . '_' . $pagedate . '_' . $this->getDate() . '.' . $file['ext'];
                if ($this->isSafeMode) {
                    $full_file_name = $path . '/' . $this->wiki->GetPageTag() . '_' . $full_file_name;
                } else {
                    $full_file_name = $path . '/' . $full_file_name;
                }
            } else {
                //recherche du fichier
                if ($this->isSafeMode) {
                    //TODO Recherche dans le cas ou safe_mode=on
                    $searchPattern = '`^' . $this->wiki->GetPageTag() . '_' . $file['name'] . '_\d{14}_\d{14}\.' . $file['ext'] . '$`';
                } else {
                    $searchPattern = '`^' . $file['name'] . '_\d{14}_\d{14}\.' . $file['ext'] . '$`';
                }
                $files = $this->searchFiles($searchPattern, $path);

                $unedate = 0;
                foreach ($files as $file) {
                    //recherche du fichier qui une datepage <= a la date de la page
                    if ($file['datepage'] <= $pagedate) {
                        //puis qui a une dateupload la plus grande
                        if ($file['dateupload'] > $unedate) {
                            $theFile = $file;
                            $unedate = $file['dateupload'];
                        }
                    }
                }
                $full_file_name = '';
                if (isset($theFile) && is_array($theFile)) {
                    $full_file_name = $path . '/' . $theFile['realname'];
                }
            }
            return $full_file_name;
        }
        /**
         * Test si le fichier est une image
         */
        public function isPicture()
        {
            return preg_match("/.(" . $this->attachConfig["ext_images"] . ")$/i", $this->file) == 1;
        }
        /**
         * Test si le fichier est un fichier audio
         */
        public function isAudio()
        {
            return preg_match("/.(" . $this->attachConfig["ext_audio"] . ")$/i", $this->file) == 1;
        }
        /**
         * Test si le fichier est un fichier freemind mind map
         */
        public function isFreeMindMindMap()
        {
            return preg_match("/.(" . $this->attachConfig["ext_freemind"] . ")$/i", $this->file) == 1;
        }
        /**
         * Test si le fichier est un fichier flv Flash video
         */
        public function isFlashvideo()
        {
            return preg_match("/.(" . $this->attachConfig["ext_flashvideo"] . ")$/i", $this->file) == 1;
        }
        /**
         * Test si le fichier est un fichier wma
         */
        public function isWma()
        {
            return preg_match("/.(" . $this->attachConfig["ext_wma"] . ")$/i", $this->file) == 1;
        }

        /**
         * Renvoie la date courante au format utilise par les fichiers
         */
        public function getDate()
        {
            return date('YmdHis');
        }
        /**
         * convertie une date yyyy-mm-dd hh:mm:ss au format yyyymmddhhmmss
         */
        public function convertDate($date)
        {
            $date = str_replace(' ', '', $date);
            $date = str_replace(':', '', $date);
            return str_replace('-', '', $date);
        }
        /**
         * Parse une date au format yyyymmddhhmmss et renvoie un tableau assiatif
         */
        public function parseDate($sDate)
        {
            $pattern = '`^(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})$`';
            $res = '';
            if (preg_match($pattern, $sDate, $m)) {
                //list(,$res['year'],$res['month'],$res['day'],$res['hour'],$res['min'],$res['sec'])=$m;
                $res = $m[1] . '-' . $m[2] . '-' . $m[3] . ' ' . $m[4] . ':' . $m[5] . ':' . $m[6];
            }
            return ($res ? $res : false);
        }
        /**
         * Decode un nom long de fichier
         */
        public function decodeLongFilename($filename)
        {
            $afile = array();
            $afile['realname'] = basename($filename);
            $afile['size'] = filesize($filename);
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
         * tableau associatif contenant les informations sur le fichier
         */
        public function searchFiles($filepattern, $start_dir)
        {
            $files_matched = array();
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
         * Test les parametres passes a l'action
         */
        public function CheckParams()
        {
            //recuperation des parametres necessaire
            $this->file = $this->wiki->GetParameter("attachfile");
            if (empty($this->file)) {
                $this->file = $this->wiki->GetParameter("file");
            }

            $this->desc = $this->wiki->GetParameter("attachdesc");
            if (empty($this->desc)) {
                $this->desc = $this->wiki->GetParameter("desc");
            }

            $this->link = $this->wiki->GetParameter("attachlink"); //url de lien - uniquement si c'est une image
            if (empty($this->link)) {
                $this->link = $this->wiki->GetParameter("link");
            }

            $this->caption = $this->wiki->GetParameter("caption"); //texte de la vignette (au survol)
            $this->legend = $this->wiki->GetParameter("legend"); //texte de la vignette (en dessous)
            $this->nofullimagelink = $this->wiki->GetParameter("nofullimagelink");
            $this->height = $this->wiki->GetParameter('height');
            $this->width = $this->wiki->GetParameter('width');

            //test de validit&eacute; des parametres
            if (empty($this->file)) {
                $this->attachErr = '<div class="alert alert-danger"><strong>' . _t('ATTACH_ACTION_ATTACH') . '</strong> : ' . _t('ATTACH_PARAM_FILE_NOT_FOUND') . '.</div>' . "\n";
            }
            if ($this->isPicture() && empty($this->desc)) {
                $this->attachErr = '<div class="alert alert-danger"><strong>' . _t('ATTACH_ACTION_ATTACH') . '</strong> : ' . _t('ATTACH_PARAM_DESC_REQUIRED') . '.</div>' . "\n";
            }
            if (!empty($this->width) && !ctype_digit($this->width)) {
                $this->attachErr = '<div class="alert alert-danger"><strong>' . _t('ATTACH_ACTION_ATTACH') . '</strong> : ' . _t('ATTACH_PARAM_WIDTH_NOT_NUMERIC') . '.</div>' . "\n";
            }
            if (!empty($this->height) && !ctype_digit($this->height)) {
                $this->attachErr = '<div class="alert alert-danger"><strong>' . _t('ATTACH_ACTION_ATTACH') . '</strong> : ' . _t('ATTACH_PARAM_HEIGHT_NOT_NUMERIC') . '.</div>' . "\n";
            }

            if ($this->wiki->GetParameter("class")) {
                $array_classes = explode(" ", $this->wiki->GetParameter("class"));
                foreach ($array_classes as $c) {
                    $this->classes .= ' ' . trim($c);
                }
            }

            $size = $this->wiki->GetParameter("size");
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
            } elseif (!empty($this->height) && !empty($this->width)) {
                // on ajuste la largeur
                $this->width = $this->height;
            }
        }
        /**
         * Affiche le fichier li&eacute; comme une image
         */
        public function showAsImage($fullFilename)
        {
            // Generation d'une vignette si absente ou si changement de dimension  , TODO : suupprimer ancienne vignette ?

            $image_redimensionnee = 0;
            if (!preg_match("/.(svg)$/i", $this->file) == 1) {
                if ((!empty($this->height)) && (!empty($this->width))) {
                    // Si des parametres width ou height present : redimensionnement
                    if (!file_exists($image_dest = $this->calculer_nom_fichier_vignette($fullFilename, $this->width, $this->height))) {
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
            $img = "<img src=\"" . $this->GetScriptPath() . $img_name . "\" " .
            "alt=\"" . $this->desc . ($this->link ? "\nLien vers: $this->link" : "") . "\" width=\"" . $width . "\" height=\"" . $height . "\" />";
            //test si c'est une image sensible
            if (!empty($this->link)) {
                //c'est une image sensible
                //test si le lien est un lien interwiki
                if (preg_match("/^([A-Z][A-Z,a-z]+)[:]([A-Z,a-z,0-9]*)$/s", $this->link, $matches)) {
                    //modifie $link pour ?tre un lien vers un autre wiki
                    $this->link = $this->wiki->GetInterWikiUrl($matches[1], $matches[2]);
                }
                //calcule du lien
                $output = $this->wiki->Format('[[' . $this->link . " $this->file]]");
                $output = preg_replace("/\>$this->file\</iU", ">$img<", $output); //insertion du tag <img...> dans le lien
            } else {
                if (empty($this->nofullimagelink) or !$this->nofullimagelink) {
                    $output = '<a href="' . $this->GetScriptPath() . $fullFilename . '">' . $img . '</a>';
                } else {
                    $output = $img;
                }
            }
            if (!empty($this->caption)) {
                $output .= '<figcaption>' . $this->caption . '</figcaption>';
            }
            if (!empty($this->legend)) {
                $output .= '<div class="legend">' . $this->legend . '</div>';
            }
            $output = "<figure class=\"$this->classes\">$output</figure>";

            echo $output;
            //$this->showUpdateLink();
        }
        /**
         * Affiche le fichier li&eacute; comme un lien
         */
        public function showAsLink($fullFilename)
        {
            $url = $this->wiki->href("download", $this->wiki->GetPageTag(), "file=$this->file");
            echo '<a href="' . $url . '">' . ($this->desc ? $this->desc : $this->file) . "</a>";
            $this->showUpdateLink();
        }
        // Affiche le fichier liee comme un fichier audio
        public function showAsAudio($fullFilename)
        {
            $output = $this->wiki->format('{{player url="' . str_replace('wakka.php?wiki=', '', $this->wiki->config['base_url']) . $fullFilename . '"}}');
            echo $output;
            $this->showUpdateLink();
        }

        // Affiche le fichier liee comme un fichier mind map  freemind
        public function showAsFreeMindMindMap($fullFilename)
        {
            $output = $this->wiki->format('{{player url="' . str_replace('wakka.php?wiki=', '', $this->wiki->config['base_url']) . $fullFilename . '" ' .
                'height="' . (!empty($height) ? $height : '650px') . '" ' .
                'width="' . (!empty($width) ? $width : '100%') . '"}}');
            echo $output;
            $this->showUpdateLink();
        }

        // Affiche le fichier liee comme un fichier mind map  freemind
        public function showAsWma($fullFilename)
        {

        }

        // Affiche le fichier liee comme une video flash
        public function showAsFlashvideo($fullFilename)
        {
            $output = $this->wiki->format('{{player url="' . str_replace('wakka.php?wiki=', '', $this->wiki->config['base_url']) . $fullFilename . '" ' .
                'height="' . (!empty($height) ? $height : '300px') . '" ' .
                'width="' . (!empty($width) ? $width : '400px') . '"}}');
            echo $output;
            $this->showUpdateLink();
        }

        // End Paste

        /**
         * Affiche le lien de mise a jour
         */
        public function showUpdateLink()
        {
            echo " <a href=\"" .
            $this->wiki->href("upload", $this->wiki->GetPageTag(), "file=$this->file") .
            "\" title='Mise &agrave; jour'>" . $this->attachConfig['update_symbole'] . "</a>";
        }
        /**
         * Affiche un liens comme un fichier inexistant
         */
        public function showFileNotExits()
        {
            echo "<a href=\"" . $this->wiki->href("upload", $this->wiki->GetPageTag(), "file=$this->file") . "\" class=\"btn btn-primary\"><i class=\"glyphicon glyphicon-upload icon-upload icon-white\"></i> " . _t('UPLOAD_FILE') . ' ' . $this->file . "</a>";
        }
        /**
         * Affiche l'attachement
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
            } elseif ($this->isAudio()) {
                $this->showAsAudio($fullFilename);
            } elseif ($this->isFreeMindMindMap()) {
                $this->showAsFreeMindMindMap($fullFilename);
            } elseif ($this->isFlashvideo()) {
                $this->showAsFlashvideo($fullFilename);
            } elseif ($this->isWma()) {
                $this->showAsWma($fullFilename);
            } else {
                $this->showAsLink($fullFilename);
            }
        }
        /******************************************************************************
         *    FONTIONS D'UPLOAD DE FICHIERS
         *******************************************************************************/
        /**
         * Traitement des uploads
         */
        public function doUpload()
        {
            $HasAccessWrite = $this->wiki->HasAccess("write");
            if ($HasAccessWrite) {
                switch ($_SERVER["REQUEST_METHOD"]) {
                    case 'GET':
                        $this->showUploadForm();
                        break;
                    case 'POST':
                        $this->performUpload();
                        break;
                    default:
                        echo "<div class=\"alert alert-error alert-danger\">" . _t('INVALID_REQUEST_METHOD') . "</div>\n";
                }
            } else {
                echo "<div class=\"alert alert-error alert-danger\">" . _t('NO_RIGHT_TO_WRITE_IN_THIS_PAGE') . "</div>\n";
                echo $this->wiki->Format(_t('ATTACH_BACK_TO_PAGE') . " " . $this->wiki->GetPageTag());
            }
        }
        /**
         * Formulaire d'upload
         */
        public function showUploadForm()
        {
            $this->file = $_GET['file'];
            echo "<h3>" . _t('ATTACH_UPLOAD_FORM_FOR_FILE') . " " . $this->file . "</h3>\n";
            echo "<form enctype=\"multipart/form-data\" name=\"frmUpload\" method=\"POST\" action=\"" . $this->wiki->href('upload', $this->wiki->GetPageTag()) . "\">\n"
            . "	<input type=\"hidden\" name=\"wiki\" value=\"" . $this->wiki->GetPageTag() . "/upload\" />\n"
            . "	<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"" . $this->attachConfig['max_file_size'] . "\" />\n"
            . "	<input type=\"hidden\" name=\"file\" value=\"$this->file\" />\n"
            . "	<input type=\"file\" name=\"upFile\" size=\"50\" /><br />\n"
            . "	<input class=\"btn btn-primary\" type=\"submit\" value=\"" . _t("ATTACH_SAVE") . "\" />\n"
                . "</form>\n";
        }
        /**
         * Execute l'upload
         */
        public function performUpload()
        {
            $this->file = $_POST['file'];

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
                        header("Location: " . $this->wiki->href("", $this->wiki->GetPageTag(), ""));
                    } else {
                        echo "<div class=\"alert alert-error alert-danger\">" . _t('ERROR_MOVING_TEMPORARY_FILE') . "</div>\n";
                    }
                    break;
                case 1:
                    echo "<div class=\"alert alert-error alert-danger\">" . _t('ERROR_UPLOAD_MAX_FILESIZE') . "</div>\n";
                    break;
                case 2:
                    echo "<div class=\"alert alert-error alert-danger\">" . _t('ERROR_MAX_FILE_SIZE') . "</div>\n";
                    break;
                case 3:
                    echo "<div class=\"alert alert-error alert-danger\">" . _t('ERROR_PARTIAL_UPLOAD') . "</div>\n";
                    break;
                case 4:
                    echo "<div class=\"alert alert-error alert-danger\">" . _t('ERROR_NO_FILE_UPLOADED') . "</div>\n";
                    break;
            }
            echo $this->wiki->Format(_t('ATTACH_BACK_TO_PAGE') . " " . $this->wiki->GetPageTag());
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
            header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
            header("Content-type: application/force-download");
            header('Pragma: public');
            header("Pragma: no-cache"); // HTTP/1.0
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
            header('Cache-Control: no-store, no-cache, must-revalidate'); // HTTP/1.1
            header('Cache-Control: pre-check=0, post-check=0, max-age=0'); // HTTP/1.1
            header('Content-Transfer-Encoding: none');
            header('Content-Type: application/octet-stream; name="' . $dlFilename . '"'); //This should work for the rest
            header('Content-Type: application/octetstream; name="' . $dlFilename . '"'); //This should work for IE & Opera
            header('Content-Type: application/download; name="' . $dlFilename . '"'); //This should work for IE & Opera
            header('Content-Disposition: attachment; filename="' . $dlFilename . '"');
            header("Content-Description: File Transfer");
            header("Content-length: $size");
            readfile($fullFilename);
        }
        /******************************************************************************
         *    FONTIONS DU FILEMANAGER
         *******************************************************************************/
        public function doFileManager()
        {
            $do = (isset($_GET['do']) && $_GET['do']) ? $_GET['do'] : '';
            switch ($do) {
                case 'restore':
                    $this->fmRestore();
                    $this->fmShow(true);
                    break;
                case 'erase':
                    $this->fmErase();
                    $this->fmShow(true);
                    break;
                case 'del':
                    $this->fmDelete();
                    $this->fmShow();
                    break;
                case 'trash':
                    $this->fmShow(true);
                    break;
                case 'emptytrash':
                    $this->fmEmptyTrash(); //pas de break car apres un emptytrash => retour au gestionnaire
                default:
                    $this->fmShow();
            }
        }
        /**
         * Controlleur du gestionnaire des fichiers, modifie pour utilisation dans une action {{filemanager}}
         */
        public function doFileManagerAction()
        {
            $do = (isset($_GET['do']) && $_GET['do']) ? $_GET['do'] : '';
            switch ($do) {
                case 'restore':
                    $this->fmRestore();
                    $this->fmShowAction(true);
                    break;
                case 'erase':
                    $this->fmErase();
                    $this->fmShowAction(true);
                    break;
                case 'del':
                    $this->fmDelete();
                    $this->fmShowAction();
                    break;
                case 'trash':
                    $this->fmShowAction(true);
                    break;
                case 'emptytrash':
                    $this->fmEmptyTrash(); //pas de break car apres un emptytrash => retour au gestionnaire
                default:
                    $this->fmShowAction();
            }
        }
        /**
         * Return human readable sizes
         *
         * @author      Aidan Lister <aidan@php.net>
         * @version     1.3.0
         * @link        http://aidanlister.com/2004/04/human-readable-file-sizes/
         * @param       int     $size        size in bytes
         * @param       string  $max         maximum unit
         * @param       string  $system      'si' for SI, 'bi' for binary prefixes
         * @param       string  $retstring   return string format
         */
        public function size_readable($size, $max = null, $system = 'si', $retstring = '%01.2f %s')
        {
            // Pick units
            $systems['si']['prefix'] = array('', 'Ko', 'Mo', 'Go', 'To', 'Po');
            $systems['si']['size'] = 1000;
            $systems['bi']['prefix'] = array('B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB');
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
         * Affiche la liste des fichiers, modifiee pour utilisation dans une action {{filemanager}}
         */
        public function fmShowAction($trash = false)
        {

            $method = ($this->wiki->GetMethod() != 'show' ? $this->wiki->GetMethod() : '');
            $output = '<ul id="fmtab' . $this->wiki->tag . '" class="nav nav-tabs">
				<li' . (($trash) ? '' : ' class="active"') . '><a href="' . $this->wiki->href($method, $this->wiki->tag . '#fmtab' . $this->wiki->tag) . '" title="Gestion des fichiers"><i class="glyphicon glyphicon-file icon-file"></i>&nbsp;Gestion des fichiers</a></li>
				<li' . (($trash) ? ' class="active"' : '') . '><a href="' . $this->wiki->href($method, $this->wiki->GetPageTag(), 'do=trash') . '" title="Corbeille"><i class="glyphicon glyphicon-trash icon-trash"></i>&nbsp;Corbeille</a></li>
            </ul>';

            $files = $this->fmGetFiles($trash);

            if (!$files) {
                $output .= '<div class="alert alert-info">Pas de fichiers attach&eacute;s &agrave; la page ' . $this->wiki->Format($this->wiki->tag) . ' pour l\'instant.</div>' . "\n";
            } else {
                // tri du tableau des fichiers
                $files = $this->sortByNameRevFile($files);
                //entete du tableau
                $fmHeadTable = '	<thead>' . "\n" .
                    '		<tr>' . "\n" .
                    '			<td class="fmfilename">Nom du fichier</td>' . "\n" .
                    '			<td class="fmfilesize">Taille</td>' . "\n" .
                    '			<td class="fmfiledate">Date de modification</td>' . "\n" .
                    '			<td class="fmfileactions">&nbsp;</td>' . "\n";
                $fmHeadTable .= '		</tr>' . "\n" .
                    '	</thead>' . "\n";

                //corps du tableau
                $fmBodyTable = '	<tbody>' . "\n";
                $i = 0;
                foreach ($files as $file) {
                    $i++;
                    $color = ($i % 2 ? "tableFMCol1" : "tableFMCol2");
                    //lien de suppression
                    if ($trash) {
                        $url = $this->wiki->href('', $this->wiki->GetPageTag(), 'do=erase&file=' . $file['realname']);
                        $icon = 'glyphicon glyphicon-remove icon-remove';
                    } else {
                        $url = $this->wiki->href('', $this->wiki->GetPageTag(), 'do=del&file=' . $file['realname']);
                        $icon = 'glyphicon glyphicon-trash icon-trash';
                    }
                    $dellink = '<a class="btn btn-mini btn-danger" href="' . $url . '" title="Supprimer"><i class="' . $icon . ' icon-white"></i></a>';
                    //lien de restauration
                    $restlink = '';
                    if ($trash) {
                        $url = $this->wiki->href('', $this->wiki->GetPageTag(), 'do=restore&file=' . $file['realname']);
                        $restlink = '<a class="btn btn-mini btn-success" href="' . $url . '" title="Restaurer"><i class="glyphicon glyphicon-refresh icon-refresh icon-white"></i>&nbsp;Restaurer</a>';
                    }

                    //lien pour downloader le fichier
                    $url = $this->wiki->href("download", $this->wiki->GetPageTag(), "file=" . $file['realname']);
                    $fileinfo = 'Nom r&eacute;el du fichier : ' . $file['realname'];
                    if ($trash) {
                        $fileinfo .= ' - Supprim&eacute; le : ' . $this->parseDate($file['trashdate']);
                    }
                    $dlLink = '<a class="filenamelink" href="' . $url . '" title="' . $fileinfo . '">' . substr($file['name'], 0, 25) . '&hellip;' . '.' . $file['ext'] . "</a>";
                    $fmBodyTable .= '		<tr class="' . $color . '">' . "\n" .

                    '			<td class="fmfilename">' . $dlLink . '</td>' . "\n" .
                    '			<td class="fmfilesize">' . $this->size_readable($file['size']) . '</td>' . "\n" .
                    '			<td class="fmfiledate">' . $this->parseDate($file['dateupload']) . '</td>' . "\n" .
                        '			<td class="fmfileactions">' . $restlink . ' ' . $dellink . '</td>' . "\n";

                    $fmBodyTable .= '		</tr>' . "\n";
                }
                $fmBodyTable .= '	</tbody>' . "\n";
                //affichage
                $output .= '<table class="fmtable table table-condensed table-hover table-striped">' . "\n" . $fmHeadTable . $fmBodyTable . '</table>' . "\n";
                if ($trash) {
                    //Avertissement
                    $output .= '<div class="alert alert-danger"><a href="' . $this->wiki->href($method, $this->wiki->tag, 'do=emptytrash') . '" class="btn btn-danger pull-right"><i class="glyphicon glyphicon-remove icon-remove icon-white"></i>&nbsp;Vider la corbeille</a><strong>Attention :</strong> les fichiers effac&eacute;s &agrave; partir de la corbeille le seront d&eacute;finitivement.<div class="clearfix"></div></div>';
                }
            }
            echo $output;
        }
        /**
         * Affiche la liste des fichiers
         */
        public function fmShow($trash = false)
        {
            $fmTitlePage = $this->wiki->Format("====Gestion des fichiers attach&eacute;s ?  la page " . $this->wiki->tag . "====\n---");
            if ($trash) {
                //Avertissement
                $fmTitlePage .= '<div class="prev_alert">Les fichiers effac&eacute;s sur cette page le sont d&eacute;finitivement</div>';
                //Pied du tableau
                $url = $this->wiki->Link($this->wiki->tag, 'filemanager', 'Gestion des fichiers');
                $fmFootTable = '	<tfoot>' . "\n" .
                    '		<tr>' . "\n" .
                    '			<td colspan="6">' . $url . '</td>' . "\n";
                $url = $this->wiki->Link($this->wiki->tag, 'filemanager&do=emptytrash', 'Vider la corbeille');
                $fmFootTable .= '			<td>' . $url . '</td>' . "\n" .
                    '		</tr>' . "\n" .
                    '	</tfoot>' . "\n";
            } else {
                //pied du tableau
                $url = '<a href="' . $this->wiki->href('filemanager', $this->wiki->GetPageTag(), 'do=trash') . '" title="Corbeille">' . $this->attachConfig['fmTrash_symbole'] . "</a>";
                $fmFootTable = '	<tfoot>' . "\n" .
                    '		<tr>' . "\n" .
                    '			<td colspan="6">' . $url . '</td>' . "\n" .
                    '		</tr>' . "\n" .
                    '	</tfoot>' . "\n";
            }
            //entete du tableau
            $fmHeadTable = '	<thead>' . "\n" .
                '		<tr>' . "\n" .
                '			<td>&nbsp;</td>' . "\n" .
                '			<td>Nom du fichier</td>' . "\n" .
                '			<td>Nom r&eacute;el du fichier</td>' . "\n" .
                '			<td>Taille</td>' . "\n" .
                '			<td>R&eacute;vision de la page</td>' . "\n" .
                '			<td>R&eacute;vison du fichier</td>' . "\n";
            if ($trash) {
                $fmHeadTable .= '			<td>Suppression</td>' . "\n";
            }
            $fmHeadTable .= '		</tr>' . "\n" .
                '	</thead>' . "\n";
            //corps du tableau
            $files = $this->fmGetFiles($trash);
            $files = $this->sortByNameRevFile($files);

            $fmBodyTable = '	<tbody>' . "\n";
            $i = 0;
            foreach ($files as $file) {
                $i++;
                $color = ($i % 2 ? "tableFMCol1" : "tableFMCol2");
                //lien de suppression
                if ($trash) {
                    $url = $this->wiki->href('filemanager', $this->wiki->GetPageTag(), 'do=erase&file=' . $file['realname']);
                } else {
                    $url = $this->wiki->href('filemanager', $this->wiki->GetPageTag(), 'do=del&file=' . $file['realname']);
                }
                $dellink = '<a href="' . $url . '" title="Supprimer">' . $this->attachConfig['fmDelete_symbole'] . "</a>";
                //lien de restauration
                $restlink = '';
                if ($trash) {
                    $url = $this->wiki->href('filemanager', $this->wiki->GetPageTag(), 'do=restore&file=' . $file['realname']);
                    $restlink = '<a href="' . $url . '" title="Restaurer">' . $this->attachConfig['fmRestore_symbole'] . "</a>";
                }

                //lien pour downloader le fichier
                $url = $this->wiki->href("download", $this->wiki->GetPageTag(), "file=" . $file['realname']);
                $dlLink = '<a href="' . $url . '">' . $file['name'] . '.' . $file['ext'] . "</a>";
                $fmBodyTable .= '		<tr class="' . $color . '">' . "\n" .
                '			<td>' . $dellink . ' ' . $restlink . '</td>' . "\n" .
                '			<td>' . $dlLink . '</td>' . "\n" .
                '			<td>' . $file['realname'] . '</td>' . "\n" .
                '			<td>' . $file['size'] . '</td>' . "\n" .
                '			<td>' . $this->parseDate($file['datepage']) . '</td>' . "\n" .
                '			<td>' . $this->parseDate($file['dateupload']) . '</td>' . "\n";
                if ($trash) {
                    $fmBodyTable .= '			<td>' . $this->parseDate($file['trashdate']) . '</td>' . "\n";
                }
                $fmBodyTable .= '		</tr>' . "\n";
            }
            $fmBodyTable .= '	</tbody>' . "\n";
            //pied de la page
            $fmFooterPage = "---\n-----\n[[" . $this->wiki->tag . " " . _t('ATTACH_BACK_TO_PAGE') . " " . $this->wiki->tag . "]]\n";
            //affichage
            echo $fmTitlePage . "\n";
            echo '<table class="tableFM" border="0" cellspacing="0">' . "\n" . $fmHeadTable . $fmFootTable . $fmBodyTable . '</table>' . "\n";
            echo $this->wiki->Format($fmFooterPage);
        }
        /**
         * Renvoie la liste des fichiers
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
         * Vide la corbeille
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
         * Effacement d'un fichier dans la corbeille
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
         * Met le fichier a la corbeille
         */
        public function fmDelete()
        {
            $path = $this->GetUploadPath();
            $filename = $path . '/' . ($_GET['file'] ? $_GET['file'] : '');
            if (file_exists($filename)) {
                $trash = $filename . 'trash' . $this->getDate();
                rename($filename, $trash);
            }
        }
        /**
         * Restauration d'un fichier mis a la corbeille
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
         * Tri tu tableau liste des fichiers par nom puis par date de revision(upload) du fichier, ordre croissant
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
            if ($this->isSafeMode) {
                $file_vignette = $file['path'] . '/' . $this->wiki->GetPageTag() . '_' . $file['name'] . "_vignette_" . $width . '_' . $height . '_' . $file['datepage'] . '_' . $file['dateupload'] . '.' . $file['ext'];
            } else {
                $file_vignette = $file['path'] . '/' . $file['name'] . "_vignette_" . $width . '_' . $height . '_' . $file['datepage'] . '_' . $file['dateupload'] . '.' . $file['ext'];
            }

            return $file_vignette;
        }

        public function redimensionner_image($image_src, $image_dest, $largeur, $hauteur)
        {
            if (!class_exists('imageTransform')) {
                require_once 'tools/attach/libs/class.imagetransform.php';
            }
            $imgTrans = new imageTransform();
            $imgTrans->sourceFile = $image_src;
            $imgTrans->targetFile = $image_dest;
            $imgTrans->resizeToWidth = $largeur;
            $imgTrans->resizeToHeight = $hauteur;
            if (!$imgTrans->resize()) {
                // in case of error, show error code
                return $imgTrans->error;
                // if there were no errors
            } else {
                return $imgTrans->targetFile;
            }
        }
    }

}
