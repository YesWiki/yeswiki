<?php


if (!WIKINI_VERSION) {
	die ("acc&egrave;s direct interdit");
}


/**
 * Handle file uploads via XMLHttpRequest
 */
class qqUploadedFileXhr {
    /**
     * Save the file to the specified path
     * @return boolean TRUE on success
     */
    function save($path) {    
        $input = fopen("php://input", "r");
        $temp = tmpfile();
        $realSize = stream_copy_to_stream($input, $temp);
        fclose($input);
        
        if ($realSize != $this->getSize()){            
            return false;
        }
        
        $target = fopen($path, "w");        
        fseek($temp, 0, SEEK_SET);
        stream_copy_to_stream($temp, $target);
        fclose($target);
        
        return true;
    }
    function getName() {
        return $_GET['qqfile'];
    }
    function getSize() {
        if (isset($_SERVER["CONTENT_LENGTH"])){
            return (int)$_SERVER["CONTENT_LENGTH"];            
        } else {
            throw new Exception('Getting content length is not supported.');
        }      
    }   
}

/**
 * Handle file uploads via regular form post (uses the $_FILES array)
 */
class qqUploadedFileForm {  
    /**
     * Save the file to the specified path
     * @return boolean TRUE on success
     */
    function save($path) {
        if(!move_uploaded_file($_FILES['qqfile']['tmp_name'], $path)){
            return false;
        }
        return true;
    }
    function getName() {
        return $_FILES['qqfile']['name'];
    }
    function getSize() {
        return $_FILES['qqfile']['size'];
    }
}

class qqFileUploader {
    private $allowedExtensions = array();
    private $sizeLimit = '10000';
    private $file;

    function __construct(array $allowedExtensions = array(), $sizeLimit = '10000'){        
        $allowedExtensions = array_map("strtolower", $allowedExtensions);
            
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
    }
    
    private function checkServerSettings(){        
        $postSize = $this->toBytes(ini_get('post_max_size'));
        $uploadSize = $this->toBytes(ini_get('upload_max_filesize'));        
        
        /*if ($postSize < $this->sizeLimit || $uploadSize < $this->sizeLimit){
            $size = max(1, $this->sizeLimit / 1024 / 1024) . 'M';             
            die("{'error':'La configuration de votre serveur devrait avoir un post_max_size et un upload_max_filesize sup&eacute;rieur &agrave; $size'}");    
        }    */    
    }
    
    private function toBytes($str){
        $val = trim($str);
        $last = strtolower($str[strlen($str)-1]);
        switch($last) {
            case 'g': $val *= 1024;
            case 'm': $val *= 1024;
            case 'k': $val *= 1024;        
        }
        return $val;
    }
    
    /**
     * Returns array('success'=>true) or array('error'=>'error message')
     */
    function handleUpload($uploadDirectory, $replaceOldFile = FALSE){
        if (!is_writable($uploadDirectory)){
            return array('error' => "Le dossier de t&eacute;l&eacute;chargement n'est pas accessible en &eacute;criture.");
        }
        
        if (!$this->file){
            return array('error' => 'Pas de fichiers envoy&eacute;s.');
        }
        
        $size = $this->file->getSize();
        
        if ($size == 0) {
            return array('error' => 'Le fichier est vide.');
        }
        
        if ($size > $this->sizeLimit) {
            return array('error' => 'Le fichier est trop large.');
        }
        
        $pathinfo = pathinfo($this->file->getName());
        $filename = $pathinfo['filename'];
        //$filename = md5(uniqid());
        $ext = strtolower($pathinfo['extension']);

        if($this->allowedExtensions && !in_array($ext, $this->allowedExtensions)){
            $these = implode(', ', $this->allowedExtensions);
            return array('error' => "Le fichier n'a pas une extension autoris&eacute;e, voici les autoris&eacute;es : ". $these . '.');
        }
        
        /*if(!$replaceOldFile){
            /// don't overwrite previous files that were uploaded
            while (file_exists($uploadDirectory . $filename . '.' . $ext)) {
                $filename .= rand(10, 99);
            }
        }*/
        
        // on enleve les espaces et les accents pour le nom de fichier
        $search = array ('@[éèêëÊË]@i','@[àâäÂÄ]@i','@[îïÎÏ]@i','@[ûùüÛÜ]@i','@[ôöÔÖ]@i','@[ç]@i','@[ ]@i','@[^a-zA-Z0-9_]@');
        $replace = array ('e','a','i','u','o','c','_','');
        $filename = preg_replace($search, $replace, utf8_decode($filename));

        $attach = new Attach($GLOBALS['wiki']);
        $GLOBALS['wiki']->setParameter("desc", $filename);
        $GLOBALS['wiki']->setParameter("file", $filename . '.' . $ext);
        
        // dans le cas d'une nouvelle page, on donne une valeur a la date de création
        if ($GLOBALS['wiki']->page['time'] == '') $GLOBALS['wiki']->page['time'] = date('YmdHis');
        
        // on envoi l'attachement en retenant l'affichage du résultat dans un buffer
        ob_start();
        $attach->doAttach();
        $fullfilename = $attach->GetFullFilename(true);
        ob_end_clean();
        
        if ($this->file->save($fullfilename)){
            return array_map('utf8_encode', array('success'=>true, 'filename'=>$fullfilename, 'simplefilename'=>$filename . '.' . $ext, 'extension'=>$ext));
        } else {
            return array_map('utf8_encode', array('error'=> 'Impossible de sauver le fichier.' .
                "L'upload a &eacute;t&eacute; annul&eacute; ou le serveur a plant&eacute;."));
        }
        
    }    
}

if (!class_exists('attach')){
	include('tools/attach/actions/attach.class.php');
}
$att = new attach($this);

// list of valid extensions, ex. array("jpeg", "xml", "bmp")
$allowedExtensions = array();
// max file size in bytes
$sizeLimit = $att->attachConfig['max_file_size'];

$uploader = new qqFileUploader($allowedExtensions, $sizeLimit);
$result = $uploader->handleUpload($att->attachConfig['upload_path']);


unset($att);

// to pass data through iframe you will need to encode all html tags
echo htmlspecialchars(json_encode($result), ENT_NOQUOTES, YW_CHARSET);
?>