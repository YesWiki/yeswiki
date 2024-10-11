<?php

use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\HtmlPurifierService;

if (!class_exists('qqUploadedFileXhr')) {
    class qqUploadedFileXhr
    {
        /**
         * Save the file to the specified path.
         *
         * @return bool TRUE on success
         */
        public function save($path)
        {
            $input = fopen('php://input', 'r');
            $temp = tmpfile();
            $realSize = stream_copy_to_stream($input, $temp);
            fclose($input);

            if ($realSize != $this->getSize()) {
                return false;
            }

            $target = fopen($path, 'w');
            fseek($temp, 0, SEEK_SET);
            stream_copy_to_stream($temp, $target);
            fclose($target);

            return true;
        }

        public function getName()
        {
            return basename($_GET['qqfile']);
        }

        public function getSize()
        {
            if (isset($_SERVER['CONTENT_LENGTH'])) {
                return (int)$_SERVER['CONTENT_LENGTH'];
            } else {
                throw new Exception('Getting content length is not supported.');
            }
        }
    }
}

if (!class_exists('qqUploadedFileForm')) {
    /**
     * Handle file uploads via regular form post (uses the $_FILES array).
     */
    class qqUploadedFileForm
    {
        /**
         * Save the file to the specified path.
         *
         * @return bool TRUE on success
         */
        public function save($path)
        {
            if (!move_uploaded_file($_FILES['qqfile']['tmp_name'], $path)) {
                return false;
            }

            return true;
        }

        public function getName()
        {
            return $_FILES['qqfile']['name'];
        }

        public function getSize()
        {
            return $_FILES['qqfile']['size'];
        }
    }
}

if (!class_exists('qqFileUploader')) {
    class qqFileUploader
    {
        private $allowedExtensions = [];
        private $sizeLimit = '10000';
        private $file;
        private $hasTempTag;

        public function __construct(array $allowedExtensions = [], $sizeLimit = '10000', $hasTempTag = false)
        {
            $allowedExtensions = array_map('strtolower', $allowedExtensions);

            $this->allowedExtensions = $allowedExtensions;
            $this->sizeLimit = $sizeLimit;

            $this->checkServerSettings();

            if (isset($_GET['qqfile'])) {
                $this->file = new qqUploadedFileXhr();
            } elseif (isset($_FILES['qqfile'])) {
                $this->file = new qqUploadedFileForm();
            } else {
                $this->file = false;
            }
            $this->hasTempTag = $hasTempTag;
        }

        private function checkServerSettings()
        {
            $postSize = $this->toBytes(ini_get('post_max_size'));
            $uploadSize = $this->toBytes(ini_get('upload_max_filesize'));

            /*if ($postSize < $this->sizeLimit || $uploadSize < $this->sizeLimit){
                $size = max(1, $this->sizeLimit / 1024 / 1024) . 'M';
                die("{'error':'La configuration de votre serveur devrait avoir un post_max_size et un upload_max_filesize sup&eacute;rieur &agrave; $size'}");
            }    */
        }

        private function toBytes($str)
        {
            $val = trim($str);
            $val = settype($val, 'integer');
            $l = strlen($str) - 1;
            $last = strtolower($str[$l]);
            switch ($last) {
                case 'g': $val *= 1024;
                    // no break
                case 'm': $val *= 1024;
                    // no break
                case 'k': $val *= 1024;
            }

            return $val;
        }

        /**
         * Returns array('success'=>true) or array('error'=>'error message').
         */
        public function handleUpload($uploadDirectory, $replaceOldFile = false)
        {
            if (!is_writable($uploadDirectory)) {
                return ['error' => _t('ATTACH_HANDLER_AJAXUPLOAD_FOLDER_NOT_READABLE')];
            }

            if (!$this->file) {
                return ['error' => _t('ATTACH_HANDLER_AJAXUPLOAD_NO_FILE')];
            }

            $size = $this->file->getSize();

            if ($size == 0) {
                return ['error' => _t('ATTACH_HANDLER_AJAXUPLOAD_EMPTY_FILE')];
            }

            if ($size > $this->sizeLimit) {
                return ['error' => _t('ATTACH_HANDLER_AJAXUPLOAD_FILE_TOO_LARGE')];
            }

            $pathinfo = pathinfo($this->file->getName());
            $filename = $pathinfo['filename'];
            //$filename = md5(uniqid());
            $ext = strtolower($pathinfo['extension']);

            if ($this->allowedExtensions && !in_array($ext, $this->allowedExtensions)) {
                $these = implode(', ', $this->allowedExtensions);

                return ['error' => str_replace('{ext}', $these, _t('ATTACH_HANDLER_AJAXUPLOAD_AUTHORIZED_EXT'))];
            }

            /*if(!$replaceOldFile){
                /// don't overwrite previous files that were uploaded
                while (file_exists($uploadDirectory . $filename . '.' . $ext)) {
                    $filename .= rand(10, 99);
                }
            }*/

            if ($this->hasTempTag) {
                $previousTag = $GLOBALS['wiki']->tag;
                $previousPage = $GLOBALS['wiki']->page;
                $GLOBALS['wiki']->tag = $_GET['tempTag'];
                $GLOBALS['wiki']->page = [
                    'tag' => $GLOBALS['wiki']->tag,
                    'body' => '{##}',
                    'time' => '',
                    'owner' => '',
                    'user' => '',
                ];
            }

            $attach = new Attach($GLOBALS['wiki']);
            $filename = $attach->sanitizeFilename($filename);
            $GLOBALS['wiki']->setParameter('desc', $filename);
            $GLOBALS['wiki']->setParameter('file', $filename . '.' . $ext);

            // dans le cas d'une nouvelle page, on donne une valeur a la date de création dans le fuseau horaire du serveur (heure SQL)
            if ($this->hasTempTag || !isset($GLOBALS['wiki']->page['time']) || $GLOBALS['wiki']->page['time'] == '') {
                $dbTz = $GLOBALS['wiki']->services->get(DbService::class)->getDbTimeZone();
                $sqlTimeFormat = 'Y-m-d H:i:s';
                $GLOBALS['wiki']->page['time'] = !empty($dbTz) ? (new DateTime())->setTimezone(new DateTimeZone($dbTz))->format($sqlTimeFormat) : date($sqlTimeFormat);
            }

            // on envoi l'attachement en retenant l'affichage du résultat dans un buffer
            ob_start();
            $attach->doAttach();
            $fullfilename = $attach->GetFullFilename(true);
            if ($this->hasTempTag) {
                $GLOBALS['wiki']->tag = $previousTag;
                $GLOBALS['wiki']->page = $previousPage;
            }
            ob_end_clean();

            if ($this->file->save($fullfilename)) {
                chmod($fullfilename, 0664); // fix file permissions to be sure to be able to write on exotic servers configurations
                //TODO : refactor this with attach
                $purifier = $GLOBALS['wiki']->services->get(HtmlPurifierService::class);
                $purifier->cleanFile($fullfilename, $ext);

                return array_map(function ($value) {
                    return mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
                }, ['success' => true, 'filename' => $fullfilename, 'simplefilename' => $filename . '.' . $ext, 'extension' => $ext]);
            } else {
                return array_map(
                    function ($value) {
                        return mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
                    },
                    [
                        'error' => _t('ATTACH_HANDLER_AJAXUPLOAD_ERROR'),
                    ]
                );
            }
        }
    }
}
