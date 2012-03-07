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

if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}

if (!class_exists('attach')){

class attach {
   var $wiki = '';					//objet wiki courant
   var $attachConfig = array();		//configuration de l'action
   var $file = '';					//nom du fichier
   var $desc = '';					//description du fichier
   var $link = '';					//url de lien (image sensible)
   var $isPicture = 0;				//indique si c'est une image
   var $isAudio = 0;				//indique si c'est un fichier audio
   var $isFreeMindMindMap = 0;		//indique si c'est un fichier mindmap freemind
   var $isWma = 0;					//indique si c'est un fichier wma
   var $classes = '';				//classe pour afficher une image
   var $attachErr = '';				//message d'erreur
   var $pageId = 0;					//identifiant de la page
   var $isSafeMode = false;			//indicateur du safe mode de PHP
   /**
   * Constructeur. Met les valeurs par defaut aux paramètres de configuration
   */
	function attach(&$wiki){
   	$this->wiki = $wiki;
		$this->attachConfig = $this->wiki->GetConfigValue("attach_config");
		if (empty($this->attachConfig["ext_images"])) $this->attachConfig["ext_images"] = "gif|jpeg|png|jpg";
		if (empty($this->attachConfig["ext_audio"])) $this->attachConfig["ext_audio"] = "mp3";
		if (empty($this->attachConfig["ext_wma"])) $this->attachConfig["ext_wma"] = "wma";
		if (empty($this->attachConfig["ext_freemind"])) $this->attachConfig["ext_freemind"] = "mm";
		if (empty($this->attachConfig["ext_flashvideo"])) $this->attachConfig["ext_flashvideo"] = "flv";
		if (empty($this->attachConfig["ext_script"])) $this->attachConfig["ext_script"] = "php|php3|asp|asx|vb|vbs|js";
		if (empty($this->attachConfig['upload_path'])) $this->attachConfig['upload_path'] = 'files';
		if (empty($this->attachConfig['update_symbole'])) $this->attachConfig['update_symbole'] = '';
		if (empty($this->attachConfig['max_file_size'])) $this->attachConfig['max_file_size'] = 1024*8000;	//8000ko max
		if (empty($this->attachConfig['fmDelete_symbole'])) $this->attachConfig['fmDelete_symbole'] = 'Supr';
		if (empty($this->attachConfig['fmRestore_symbole'])) $this->attachConfig['fmRestore_symbole'] = 'Rest';
		if (empty($this->attachConfig['fmTrash_symbole'])) $this->attachConfig['fmTrash_symbole'] = 'Poubelle';
		
		$this->isSafeMode = ini_get("safe_mode");

	}
/******************************************************************************
*	FONCTIONS UTILES
*******************************************************************************/
	/**
	* Création d'une suite de répertoires récursivement
	*/
	function mkdir_recursif ($dir) {
		if (strlen($dir) == 0) return 0;
		if (is_dir($dir)) return 1;
		elseif (dirname($dir) == $dir) return 1;
		return ($this->mkdir_recursif(dirname($dir)) and mkdir($dir,0755));
	}
	/**
	* Renvois le chemin du script
	*/
	function GetScriptPath () {
		if (preg_match("/.(php)$/i",$_SERVER["PHP_SELF"])){
			$a = explode('/',$_SERVER["PHP_SELF"]);
			$a[count($a)-1] = '';
			$path = implode('/',$a);
		}else{
			$path = $_SERVER["PHP_SELF"];
		}
		return !empty($_SERVER["HTTP_HOST"])? 'http://'.$_SERVER["HTTP_HOST"].$path : 'http://'.$_SERVER["SERVER_NAME"].$path ;
	}
	/**
	* Calcul le repertoire d'upload en fonction du safe_mode
	*/
	function GetUploadPath(){
		if ($this->isSafeMode) {
			$path = $this->attachConfig['upload_path'];
		}else{
         $path = $this->attachConfig['upload_path'].'/'.$this->wiki->GetPageTag();
			if (! is_dir($path)) $this->mkdir_recursif($path);
		}
		return $path;
	}
	/**
	* Calcule le nom complet du fichier attaché en fonction du safe_mode, du nom et de la date de
	* revision la page courante.
	* Le nom du fichier "mon fichier.ext" attache à la page "LaPageWiki"sera :
	*  mon_fichier_datepage_update.ext
	*     update : date de derniere mise a jour du fichier
	*     datepage : date de revision de la page à laquelle le fichier a ete lié/mis a jour
	*  Si le fichier n'est pas une image un '_' est ajoute : mon_fichier_datepage_update.ext_
	*  Selon la valeur de safe_mode :
	*  safe_mode = on : 	LaPageWiki_mon_fichier_datepage_update.ext_
	*  safe_mode = off: 	LaPageWiki/mon_fichier_datepage_update.ext_ avec "LaPageWiki" un sous-repertoire du répertoire upload
	*/
	function GetFullFilename($newName = false){
		$pagedate = $this->convertDate($this->wiki->page['time']);
		//decompose le nom du fichier en nom+extension
		if (preg_match('`^(.*)\.(.*)$`', str_replace(' ','_',$this->file), $match)){
			list(,$file['name'],$file['ext'])=$match;
			if(!$this->isPicture() && !$this->isAudio() && !$this->isFreeMindMindMap() && !$this->isWma() && !$this->isFlashvideo()) $file['ext'] .= '_';
		}else{
			return false;
		}
		//recuperation du chemin d'upload
		$path = $this->GetUploadPath($this->isSafeMode);
		//generation du nom ou recherche de fichier ?
		if ($newName){
			$full_file_name = $file['name'].'_'.$pagedate.'_'.$this->getDate().'.'.$file['ext'];
			if($this->isSafeMode){
				$full_file_name = $path.'/'.$this->wiki->GetPageTag().'_'.$full_file_name;
			}else{
				$full_file_name = $path.'/'.$full_file_name;
			}
		}else{
			//recherche du fichier
			if($this->isSafeMode){
				//TODO Recherche dans le cas ou safe_mode=on
				$searchPattern = '`^'.$this->wiki->GetPageTag().'_'.$file['name'].'_\d{14}_\d{14}\.'.$file['ext'].'$`';
			}else{
				$searchPattern = '`^'.$file['name'].'_\d{14}_\d{14}\.'.$file['ext'].'$`';
			}
			$files = $this->searchFiles($searchPattern,$path);

			$unedate = 0;
			foreach ($files as $file){
				//recherche du fichier qui une datepage <= a la date de la page
				if($file['datepage']<=$pagedate){
					//puis qui a une dateupload la plus grande
					if ($file['dateupload']>$unedate){
						$theFile = $file;
						$unedate = $file['dateupload'];
					}
				}
			}
			if (is_array($theFile)){
				$full_file_name = $path.'/'.$theFile['realname'];
			}
		}
		return $full_file_name;
	}
	/**
	* Test si le fichier est une image
	*/
	function isPicture(){
		return preg_match("/.(".$this->attachConfig["ext_images"].")$/i",$this->file)==1;
	}
	/**
	* Test si le fichier est un fichier audio
	*/
	function isAudio(){
		return preg_match("/.(".$this->attachConfig["ext_audio"].")$/i",$this->file)==1;
	}
	/**
	* Test si le fichier est un fichier freemind mind map
	*/
	function isFreeMindMindMap(){
		return preg_match("/.(".$this->attachConfig["ext_freemind"].")$/i",$this->file)==1;
	}
	/**
	* Test si le fichier est un fichier flv Flash video
	*/
	function isFlashvideo(){
		return preg_match("/.(".$this->attachConfig["ext_flashvideo"].")$/i",$this->file)==1;
	}
	/**
	* Test si le fichier est un fichier wma
	*/
	function isWma(){
		return preg_match("/.(".$this->attachConfig["ext_wma"].")$/i",$this->file)==1;
	}

	/**
	* Renvoie la date courante au format utilise par les fichiers
	*/
	function getDate(){
		return date('YmdHis');
	}
	/**
	* convertie une date yyyy-mm-dd hh:mm:ss au format yyyymmddhhmmss
	*/
	function convertDate($date){
		$date = str_replace(' ','', $date);
		$date = str_replace(':','', $date);
		return str_replace('-','', $date);
	}
	/**
	* Parse une date au format yyyymmddhhmmss et renvoie un tableau assiatif
	*/
	function parseDate($sDate){
		$pattern = '`^(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})$`';
		$res = '';
		if (preg_match($pattern, $sDate, $m)){
			//list(,$res['year'],$res['month'],$res['day'],$res['hour'],$res['min'],$res['sec'])=$m;
			$res = $m[1].'-'.$m[2].'-'.$m[3].' '.$m[4].':'.$m[5].':'.$m[6];
		}
		return ($res?$res:false);
	}
	/**
	* Decode un nom long de fichier
	*/
	function decodeLongFilename($filename){
		$afile = array();
		$afile['realname'] = basename($filename);
		$afile['size'] = filesize($filename);
		$afile['path'] = dirname($filename);
		if(preg_match('`^(.*)_(\d{14})_(\d{14})\.(.*)(trash\d{14})?$`', $afile['realname'], $m)){
			$afile['name'] = $m[1];
			//suppression du nom de la page si safe_mode=on
			if ($this->isSafeMode){
				$afile['name'] = preg_replace('`^('.$this->wiki->tag.')_(.*)$`i', '$2', $afile['name']);
			}
			$afile['datepage'] = $m[2];
			$afile['dateupload'] = $m[3];
                $afile['trashdate'] = preg_replace('`(.*)trash(\d{14})`', '$2', $m[4]);
            //suppression de trashxxxxxxxxxxxxxx eventuel
            $afile['ext'] = preg_replace('`^(.*)(trash\d{14})$`', '$1', $m[4]);
            $afile['ext'] = rtrim($afile['ext'],'_');
            //$afile['ext'] = rtrim($m[4],'_');
        }
        return $afile;
    }
    /**
     * Renvois un tableau des fichiers correspondant au pattern. Chaque element du tableau est un
     * tableau associatif contenant les informations sur le fichier
     */
    function searchFiles($filepattern,$start_dir){
        $files_matched = array();
        $start_dir = rtrim($start_dir,'\/');
        $fh = opendir($start_dir);
        while (($file = readdir($fh)) !== false) {
            if (strcmp($file, '.')==0 || strcmp($file, '..')==0 || is_dir($file)) continue;
            if (preg_match($filepattern, $file)){
                $files_matched[] = $this->decodeLongFilename($start_dir.'/'.$file);
            }
        }
        return $files_matched;
    }
    /******************************************************************************
     *	FONCTIONS D'ATTACHEMENTS
     *******************************************************************************/
    /**
     * Test les paramètres passé à l'action
     */
    function CheckParams(){
        //recuperation des parametres necessaire
        $this->file = $this->wiki->GetParameter("attachfile");
        if (empty($this->file)) $this->file = $this->wiki->GetParameter("file");
        $this->desc = $this->wiki->GetParameter("attachdesc");
        if (empty($this->desc)) $this->desc = $this->wiki->GetParameter("desc");
        $this->link = $this->wiki->GetParameter("attachlink");//url de lien - uniquement si c'est une image
        if (empty($this->link)) $this->link = $this->wiki->GetParameter("link");
        //test de validité des parametres
        if (empty($this->file)){
            $this->attachErr = $this->wiki->Format("//action attach : paramètre **file** manquant//---");
        }
        if ($this->isPicture() && empty($this->desc)){
            $this->attachErr .= $this->wiki->Format("//action attach : paramètre **desc** obligatoire pour une image//---");
        }
        if ($this->wiki->GetParameter("class")) {
            $array_classes = explode(" ", $this->wiki->GetParameter("class"));
            foreach ($array_classes as $c) { $this->classes = $this->classes . "attach_" . $c . " "; }
            $this->classes = trim($this->classes);
        }
        $this->height = $this->wiki->GetParameter('height');
        $this->width = $this->wiki->GetParameter('width');
        $size = $this->wiki->GetParameter("size");
      
        switch ($size) {
                case 'small' : 
                    $this->width = 140;
                    $this->height = 140;
                    break;
                case 'medium': 
                     $this->width = 300; 
                     $this->height = 300;
                     break;
                case 'big': 
                     $this->width = 780;
                    $this->height = 780;
                     break;

            }
       if (empty($this->height)) $this->height=$this->width; 
       if (empty($this->width)) $this->width=$this->height; 

    }
    /**
     * Affiche le fichier lié comme une image
     */
    function showAsImage($fullFilename){
        // Generation d'une vignette si absente ou si changement de dimension  , TODO : suupprimer ancienne vignette ?

        $image_redimensionnee=0;
        if ((!empty($this->height)) ||  (!empty($this->width))) { // Si des parametres width ou height present : redimensionnement
            if (!file_exists($image_dest=$this->calculer_nom_fichier_vignette($fullFilename,$this->width,$this->height))) {
                $this->redimensionner_image($fullFilename, $image_dest,$this->width ,$this->height);
            }
            $img_name=$image_dest;
            $image_redimensionnee=1;
        }
        else {
            $img_name=$fullFilename;
        }

        //c'est une image : balise <IMG..../>
        $img =	"<img src=\"".$this->GetScriptPath().$img_name."\" ".
            "alt=\"".$this->desc.($this->link?"\nLien vers: $this->link":"")."\" />";
        //test si c'est une image sensible
        if(!empty($this->link)){
            //c'est une image sensible
            //test si le lien est un lien interwiki
            if (preg_match("/^([A-Z][A-Z,a-z]+)[:]([A-Z,a-z,0-9]*)$/s", $this->link, $matches))
            {  //modifie $link pour être un lien vers un autre wiki
                $this->link = $this->wiki->GetInterWikiUrl($matches[1], $matches[2]);
            }
            //calcule du lien
            $output = $this->wiki->Format('[['.$this->link." $this->file]]");
            $output = eregi_replace(">$this->file<",">$img<",$output);//insertion du tag <img...> dans le lien
        }else{
            if ($image_redimensionnee) {
                $output = '<a href="'.$this->GetScriptPath().$fullFilename.'">'.$img.'</a>';
            }
            else {
                $output = $img;
            }
        }
        $output = ($this->classes?"<span class=\"$this->classes\">$output</span>":$output);
        echo $output;
        $this->showUpdateLink();
    }
    /**
     * Affiche le fichier lié comme un lien
     */
    function showAsLink($fullFilename){
        $url = $this->wiki->href("download",$this->wiki->GetPageTag(),"file=$this->file");
        echo '<a href="'.$url.'">'.($this->desc?$this->desc:$this->file)."</a>";
        $this->showUpdateLink();
    }
    // Affiche le fichier liee comme un fichier audio
    function showAsAudio($fullFilename){
        $output = $this->wiki->format('{{player url="'.str_replace('wakka.php?wiki=', '', $this->wiki->getConfig['base_url']).$fullFilename.'"}}');
        echo $output;
        $this->showUpdateLink();
    }

    // Affiche le fichier liee comme un fichier mind map  freemind
    function showAsFreeMindMindMap($fullFilename){
        $output = $this->wiki->format('{{player url="'.str_replace('wakka.php?wiki=', '', $this->wiki->getConfig['base_url']).$fullFilename.'" '.
                'height="'.(!empty($height) ? $height : '650px').'" '.
                'width="'.(!empty($width) ? $width : '100%').'"}}');
        echo $output;
        $this->showUpdateLink();
    }

    // Affiche le fichier liee comme un fichier mind map  freemind
    function showAsWma($fullFilename){

    }

    // Affiche le fichier liee comme une video flash
    function showAsFlashvideo($fullFilename){
        $output = $this->wiki->format('{{player url="'.str_replace('wakka.php?wiki=', '', $this->wiki->getConfig['base_url']).$fullFilename.'" '.
                'height="'.(!empty($height) ? $height : '300px').'" '.
                'width="'.(!empty($width) ? $width : '400px').'"}}');
        echo $output;
        $this->showUpdateLink();
    }

    // End Paste



    /**
     * Affiche le lien de mise à jour
     */
    function showUpdateLink(){
        echo	" <a href=\"".
            $this->wiki->href("upload",$this->wiki->GetPageTag(),"file=$this->file").
            "\" title='Mise à jour'>".$this->attachConfig['update_symbole']."</a>";
    }
    /**
     * Affiche un liens comme un fichier inexistant
     */
    function showFileNotExits(){
        echo $this->file."<a href=\"".$this->wiki->href("upload",$this->wiki->GetPageTag(),"file=$this->file")."\">?</a>";
    }
    /**
     * Affiche l'attachement
     */
    function doAttach(){
        $this->CheckParams();
        if ($this->attachErr) {
            echo $this->attachErr;
            return;
        }
        $fullFilename = $this->GetFullFilename();
        //test d'existance du fichier
        if((!file_exists($fullFilename))||($fullFilename=='')){
            $this->showFileNotExits();
            return;
        }
        //le fichier existe : affichage en fonction du type
        if($this->isPicture()){
            $this->showAsImage($fullFilename);
        }elseif ($this->isAudio()){
            $this->showAsAudio($fullFilename);
        }elseif ($this->isFreeMindMindMap()){
            $this->showAsFreeMindMindMap($fullFilename);
        }elseif ($this->isFlashvideo()){
            $this->showAsFlashvideo($fullFilename);
        }elseif ($this->isWma()){
            $this->showAsWma($fullFilename);
        }else {
            $this->showAsLink($fullFilename);
        }
    }
    /******************************************************************************
     *	FONTIONS D'UPLOAD DE FICHIERS
     *******************************************************************************/
    /**
     * Traitement des uploads
     */
    function doUpload(){
        $HasAccessWrite=$this->wiki->HasAccess("write");
        if ($HasAccessWrite){
            switch ($_SERVER["REQUEST_METHOD"]) {
                case 'GET' : $this->showUploadForm(); break;
                case 'POST': $this->performUpload(); break;
                default : echo $this->wiki->Format("//Methode de requete invalide//---");
            }
        }else{
            echo $this->wiki->Format("//Vous n'avez pas l'accès en écriture à cette page//---");
            echo $this->wiki->Format("Retour à la page ".$this->wiki->GetPageTag());
        }
    }
    /**
     * Formulaire d'upload
     */
    function showUploadForm(){
        echo $this->wiki->Format("====Formulaire d'envois de fichier====\n---");
        $this->file = $_GET['file'];
        echo 	$this->wiki->Format("**Envois du fichier $this->file :**\n")
            ."<form enctype=\"multipart/form-data\" name=\"frmUpload\" method=\"POST\" action=\"".$_SERVER["PHP_SELF"]."\">\n"
            ."	<input type=\"hidden\" name=\"wiki\" value=\"".$this->wiki->GetPageTag()."/upload\" />\n"
            ."	<input TYPE=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"".$this->attachConfig['max_file_size']."\" />\n"
            ."	<input type=\"hidden\" name=\"file\" value=\"$this->file\" />\n"
            ."	<input type=\"file\" name=\"upFile\" size=\"50\" /><br />\n"
            ."	<input type=\"submit\" value=\"Envoyer\" />\n"
            ."</form>\n";
    }
    /**
     * Execute l'upload
     */
    function performUpload(){
        $this->file = $_POST['file'];

        $destFile = $this->GetFullFilename(true);	//nom du fichier destination
        //test de la taille du fichier recu
        if($_FILES['upFile']['error']==0){
            $size = filesize($_FILES['upFile']['tmp_name']);
            if ($size > $this->attachConfig['max_file_size']){
                $_FILES['upFile']['error']=2;
            }
        }
        switch ($_FILES['upFile']['error']){
            case 0:
                $srcFile = $_FILES['upFile']['tmp_name'];
                if (move_uploaded_file($srcFile,$destFile)){
                    chmod($destFile,0644);
                    header("Location: ".$this->wiki->href("",$this->wiki->GetPageTag(),""));
                }else{
                    echo $this->wiki->Format("//Erreur lors du déplacement du fichier temporaire//---");
                }
                break;
            case 1:
                echo $this->wiki->Format("//Le fichier téléchargé excède la taille de upload_max_filesize, configuré dans le php.ini.//---");
                break;
            case 2:
                echo $this->wiki->Format("//Le fichier téléchargé excède la taille de MAX_FILE_SIZE, qui a été spécifiée dans le formulaire HTML.//---");
                break;
            case 3:
                echo $this->wiki->Format("//Le fichier n'a été que partiellement téléchargé.//---");
                break;
            case 4:
                echo $this->wiki->Format("//Aucun fichier n'a été téléchargé.//---");
                break;
        }
        echo $this->wiki->Format("Retour à la page ".$this->wiki->GetPageTag());
    }
    /******************************************************************************
     *	FUNCTIONS DE DOWNLOAD DE FICHIERS
     *******************************************************************************/
    function doDownload(){
        $this->file = $_GET['file'];
        $fullFilename = $this->GetUploadPath().'/'.basename(realpath($this->file).$this->file);
        //		$fullFilename = $this->GetUploadPath().'/'.$this->file;
        if(!file_exists($fullFilename)){
            $fullFilename = $this->GetFullFilename();
            $dlFilename = $this->file;
            $size = filesize($fullFilename);
        }else{
            $file = $this->decodeLongFilename($fullFilename);
            $size = $file['size'];
            $dlFilename =$file['name'].'.'.$file['ext'];
        }
        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
        header("Content-type: application/force-download");
        header('Pragma: public');
        header("Pragma: no-cache");// HTTP/1.0
        header('Last-Modified: '.gmdate('D, d M Y H:i:s') . ' GMT');
        header('Cache-Control: no-store, no-cache, must-revalidate'); // HTTP/1.1
        header('Cache-Control: pre-check=0, post-check=0, max-age=0'); // HTTP/1.1
        header('Content-Transfer-Encoding: none');
        header('Content-Type: application/octet-stream; name="' . $dlFilename . '"'); //This should work for the rest
        header('Content-Type: application/octetstream; name="' . $dlFilename . '"'); //This should work for IE & Opera
        header('Content-Type: application/download; name="' . $dlFilename . '"'); //This should work for IE & Opera
        header('Content-Disposition: attachment; filename="'.$dlFilename.'"');
        header("Content-Description: File Transfer");
        header("Content-length: $size");
        readfile($fullFilename);
    }
    /******************************************************************************
     *	FONTIONS DU FILEMANAGER
     *******************************************************************************/
    function doFileManager(){
        $do = $_GET['do']?$_GET['do']:'';
        switch ($do){
            case 'restore' :
                $this->fmRestore();
                $this->fmShow(true);
                break;
            case 'erase' :
                $this->fmErase();
                $this->fmShow(true);
                break;
            case 'del' :
                $this->fmDelete();
                $this->fmShow();
                break;
            case 'trash' :
                $this->fmShow(true); break;
            case 'emptytrash' :
                $this->fmEmptyTrash();	//pas de break car apres un emptytrash => retour au gestionnaire
            default :
                $this->fmShow();
        }
    }
    /**
     * Affiche la liste des fichiers
     */
    function fmShow($trash=false){
        $fmTitlePage = $this->wiki->Format("====Gestion des fichiers attachés à  la page ".$this->wiki->tag."====\n---");
        if($trash){
            //Avertissement
            $fmTitlePage .= '<div class="prev_alert">Les fichiers effacés sur cette page le sont définitivement</div>';
            //Pied du tableau
            $url = $this->wiki->Link($this->wiki->tag,'filemanager','Gestion des fichiers');
            $fmFootTable =	'	<tfoot>'."\n".
                '		<tr>'."\n".
                '			<td colspan="6">'.$url.'</td>'."\n";
            $url = $this->wiki->Link($this->wiki->tag,'filemanager&do=emptytrash','Vider la poubelle');
            $fmFootTable.=	'			<td>'.$url.'</td>'."\n".
                '		</tr>'."\n".
                '	</tfoot>'."\n";
        }else{
            //pied du tableau
            $url = '<a href="'.$this->wiki->href('filemanager',$this->wiki->GetPageTag(),'do=trash').'" title="Poubelle">'.$this->attachConfig['fmTrash_symbole']."</a>";
            $fmFootTable =	'	<tfoot>'."\n".
                '		<tr>'."\n".
                '			<td colspan="6">'.$url.'</td>'."\n".
                '		</tr>'."\n".
                '	</tfoot>'."\n";
        }
        //entete du tableau
        $fmHeadTable = '	<thead>'."\n".
            '		<tr>'."\n".
            '			<td>&nbsp;</td>'."\n".
            '			<td>Nom du fichier</td>'."\n".
            '			<td>Nom réel du fichier</td>'."\n".
            '			<td>Taille</td>'."\n".
            '			<td>Révision de la page</td>'."\n".
            '			<td>Révison du fichier</td>'."\n";
        if($trash){
            $fmHeadTable.= '			<td>Suppression</td>'."\n";
        }
        $fmHeadTable.= '		</tr>'."\n".
            '	</thead>'."\n";
        //corps du tableau
        $files = $this->fmGetFiles($trash);
        $files = $this->sortByNameRevFile($files);

        $fmBodyTable =	'	<tbody>'."\n";
        $i = 0;
        foreach ($files as $file){
            $i++;
            $color= ($i%2?"tableFMCol1":"tableFMCol2");
            //lien de suppression
            if ($trash){
                $url = $this->wiki->href('filemanager',$this->wiki->GetPageTag(),'do=erase&file='.$file['realname']);
            }else{
                $url = $this->wiki->href('filemanager',$this->wiki->GetPageTag(),'do=del&file='.$file['realname']);
            }
            $dellink = '<a href="'.$url.'" title="Supprimer">'.$this->attachConfig['fmDelete_symbole']."</a>";
            //lien de restauration
            $restlink = '';
            if ($trash){
                $url = $this->wiki->href('filemanager',$this->wiki->GetPageTag(),'do=restore&file='.$file['realname']);
                $restlink = '<a href="'.$url.'" title="Restaurer">'.$this->attachConfig['fmRestore_symbole']."</a>";
            }

            //lien pour downloader le fichier
            $url = $this->wiki->href("download",$this->wiki->GetPageTag(),"file=".$file['realname']);
            $dlLink = '<a href="'.$url.'">'.$file['name'].'.'.$file['ext']."</a>";
            $fmBodyTable .= 	'		<tr class="'.$color.'">'."\n".
                '			<td>'.$dellink.' '.$restlink.'</td>'."\n".
                '			<td>'.$dlLink.'</td>'."\n".
                '			<td>'.$file['realname'].'</td>'."\n".
                '			<td>'.$file['size'].'</td>'."\n".
                '			<td>'.$this->parseDate($file['datepage']).'</td>'."\n".
                '			<td>'.$this->parseDate($file['dateupload']).'</td>'."\n";
            if($trash){
                $fmBodyTable.= '			<td>'.$this->parseDate($file['trashdate']).'</td>'."\n";
            }
            $fmBodyTable .= 	'		</tr>'."\n";
        }
        $fmBodyTable .= '	</tbody>'."\n";
        //pied de la page
        $fmFooterPage = "---\n-----\n[[".$this->wiki->tag." Retour à la page ".$this->wiki->tag."]]\n";
        //affichage
        echo $fmTitlePage."\n";
        echo '<table class="tableFM" border="0" cellspacing="0">'."\n".$fmHeadTable.$fmFootTable.$fmBodyTable.'</table>'."\n";
        echo $this->wiki->Format($fmFooterPage);
    }
    /**
     * Renvoie la liste des fichiers
     */
    function fmGetFiles($trash=false){
        $path = $this->GetUploadPath();
        if($this->isSafeMode){
            $filePattern = '^'.$this->wiki->GetPageTag().'_.*_\d{14}_\d{14}\..*';
        }else{
            $filePattern = '^.*_\d{14}_\d{14}\..*';
        }
        if($trash){
            $filePattern .= 'trash\d{14}';
        }else{
            $filePattern .= '[^(trash\d{14})]';
        }
        return $this->searchFiles('`'.$filePattern.'$`', $path);
    }
    /**
     * Vide la poubelle
     */
    function fmEmptyTrash(){
        $files = $this->fmGetFiles(true);
        foreach ($files as $file){
            $filename = $file['path'].'/'.$file['realname'];
            if(file_exists($filename)){
                unlink($filename);
            }
        }
    }
    /**
     * Effacement d'un fichier dans la poubelle
     */
    function fmErase(){
        $path = $this->GetUploadPath();
        $filename = $path.'/'.($_GET['file']?$_GET['file']:'');
        if (file_exists($filename)){
            unlink($filename);
        }
    }
    /**
     * Met le fichier a la poubelle
     */
    function fmDelete(){
        $path = $this->GetUploadPath();
        $filename = $path.'/'.($_GET['file']?$_GET['file']:'');
        if (file_exists($filename)){
            $trash = $filename.'trash'.$this->getDate();
            rename($filename, $trash);
        }
    }
    /**
     * Restauration d'un fichier mis a la poubelle
     */
    function fmRestore(){
        $path = $this->GetUploadPath();
        $filename = $path.'/'.($_GET['file']?$_GET['file']:'');
        if (file_exists($filename)){
            $restFile = preg_replace('`^(.*\..*)trash\d{14}$`', '$1', $filename);
            rename($filename, $restFile);
        }
    }
    /**
     * Tri tu tableau liste des fichiers par nom puis par date de revision(upload) du fichier, ordre croissant
     */
    function sortByNameRevFile($files){
        if (!function_exists('ByNameByRevFile')){
            function ByNameByRevFile($f1,$f2){
                $f1Name = $f1['name'].'.'.$f1['ext'];
                $f2Name = $f2['name'].'.'.$f2['ext'];
                $res = strcasecmp($f1Name, $f2Name);
                if($res==0){
                    //si meme nom => compare la revision du fichier
                    $res = strcasecmp($f1['dateupload'], $f2['dateupload']);
                }
                return $res;
            }
        }
        usort($files,'ByNameByRevFile');
        return $files;
    }

    function calculer_nom_fichier_vignette ($fullFilename, $width, $height) {
        $file = $this->decodeLongFilename($fullFilename);
        if($this->isSafeMode){
            $file_vignette = $file['path'].'/'.$this->wiki->GetPageTag().'_'.$file['name']."_vignette_".$width.'_'.$height.'_'.$file['datepage'].'_'.$file['dateupload'].'.'.$file['ext'];
        }else{
            $file_vignette = $file['path'].'/'.$file['name']."_vignette_".$width.'_'.$height.'_'.$file['datepage'].'_'.$file['dateupload'].'.'.$file['ext'];
        }

        return $file_vignette;
    }


    function redimensionner_image($image_src, $image_dest, $largeur, $hauteur) {
        require_once 'tools/attach/libs/class.imagetransform.php';
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
?>
