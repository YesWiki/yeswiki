<?php
function AddCSS($style)
{
    if (!isset($GLOBALS['css'])) {
        $GLOBALS['css'] = '';
    }
    if (!empty($style) && !strpos($GLOBALS['css'], '<style>'."\n".$style.'</style>')) {
        $GLOBALS['css'] .= '  <style>'."\n".$style.'</style>'."\n";
    }
    return;
}

function AddCSSFile($file, $conditionstart = '', $conditionend = '')
{
    if (!isset($GLOBALS['css'])) {
        $GLOBALS['css'] = '';
    }
    if (!strpos($GLOBALS['css'], '<link rel="stylesheet" href="'.$file.'">')
      && (!empty($file) && (file_exists($file) || strpos($file, "http://") === 0 || strpos($file, "https://") === 0))) {
        $GLOBALS['css'] .= '  '.$conditionstart."\n"
            .'    <link rel="stylesheet" href="'.$file.'">'."\n"
            .'  '.$conditionend."\n";
    }
    return;
}

function AddJavascript($script)
{
    if (!isset($GLOBALS['js'])) {
        $GLOBALS['js'] = '';
    }
    if (!empty($script) && !strpos($GLOBALS['js'], '<script>'."\n".$script.'</script>')) {
        $GLOBALS['js'] .= '  <script>'."\n".$script.'</script>'."\n";
    }
    return;
}

function AddJavascriptFile($file)
{
    if (!isset($GLOBALS['js'])) {
        $GLOBALS['js'] = '';
    }
    if (!strpos($GLOBALS['js'], '<script src="'.$file.'"></script>') && !empty($file) && (file_exists($file)|| strpos($file, "http://") === 0 || strpos($file, "https://") === 0)) {
        $GLOBALS['js'] .= '  <script src="'.$file.'"></script>'."\n";
    }
    return;
}

function LoadRecentlyChanged($limit = 50)
{
    $limit= (int) $limit;
    if ($pages = $this->LoadAll("select id, tag, time, user, owner from ".$this->config["table_prefix"]."pages where latest = 'Y' and comment_on =  '' order by time desc limit $limit")) {
        return $pages;
    }
}


function GetMethod()
{
    if ($this->method=='iframe') {
        return 'show';
    } else {
        return Wiki::GetMethod();
    }
}


function GetMetaDatas($pagetag)
{
    $metadatas = $this->GetTripleValue($pagetag, 'http://outils-reseaux.org/_vocabulary/metadata', '', '', '');
    if (!empty($metadatas)) {
        if (YW_CHARSET != 'UTF-8') {
            return array_map('utf8_decode', json_decode($metadatas, true));
        } else {
            return json_decode($metadatas, true);
        }
    } else {
        return false;
    }
}


function SaveMetaDatas($pagetag, $metadatas)
{
    $former_metadatas = $this->GetMetaDatas($pagetag);

    if ($former_metadatas) {
        $metadatas = array_merge($former_metadatas, $metadatas);
        $this->DeleteTriple($pagetag, 'http://outils-reseaux.org/_vocabulary/metadata', null, '', '');
    }
    if (YW_CHARSET != 'UTF-8') {
        $metadatas = json_encode(array_map("utf8_encode", $metadatas));
    } else {
        $metadatas = json_encode($metadatas);
    }
    return $this->InsertTriple($pagetag, 'http://outils-reseaux.org/_vocabulary/metadata', $metadatas, '', '');
}

// templates avec cache
var $template = array(); // toutes les infos sur le template en cours de manipulation

/**
 *
 * @param $file string the file name you want to load
 */
function renderTemplate($tool, $file, $values, $expire = 900)
{
    // On cherche si le theme existe dans le theme courant
    $templatefile = 'themes/'.$this->config["favorite_theme"].'/tools/'.$tool.'/templates/'.$file;
    if (!is_file($templatefile)) {
        // On cherche si le theme existe dans themes/tools
        $templatefile = 'themes/tools/'.$tool.'/templates/'.$file;
        if (!is_file($templatefile)) {
            // On cherche dans les templates du tools
            $templatefile = 'tools/'.$tool.'/presentation/templates/'.$file;
            if (!is_file($templatefile)) {
                // on quitte si aucun template de trouvé
                return '<div class="alert alert-danger">'._t('TEMPLATE_FILE_NOT_FOUND').' : "'.$file.'".</div>';
            }
        }
    }

    // initialisation des variables
    $this->template["file"]     = $templatefile;
    $this->template["cache_id"] = 'cache/'.$tool.'-'.$file.'-'.md5(implode_r('', $values));
    $this->template["expire"]   = $expire;
    $this->template["cached"]   = false;
    $this->template["vars"]     = array();

    if (!($this->isTemplateCached())) {
        foreach ($values as $key => $val) {
            $this->template["vars"][$key] = $val;
        }

        extract($this->template["vars"]); // Extract the vars to local namespace
        ob_start();                       // Start output buffering
        include($this->template["file"]); // Include the file
        $contents = ob_get_contents();    // Get the contents of the buffer
        ob_end_clean();                   // End buffering and discard

        // Write the cache
        if ($fp = @fopen($this->template["cache_id"], 'w')) {
            fwrite($fp, $contents);
            fclose($fp);
        } else {
            die('Unable to write cache.');
        }

        return $contents;
    } else {
        $fp = @fopen($this->template["cache_id"], 'r');
        $contents = fread($fp, filesize($this->template["cache_id"]));
        fclose($fp);
        return $contents;
    }

}

/**
 * Test to see whether the currently loaded cache_id has a valid
 * corresponding cache file.
 */
function isTemplateCached()
{
    if ($this->template["cached"]) {
        return true;
    }

    // Cache file exists?
    if (!file_exists($this->template["cache_id"])) {
        return false;
    }

    // Can get the time of the file?
    if (!($mtime = filemtime($this->template["cache_id"]))) {
        return false;
    }

    // Cache expired?
    if (($mtime + $this->template["expire"]) < time()) {
        @unlink($this->template["cache_id"]);
        return false;
    } else {
        /**
         * Cache the results of this isTemplateCached() call.  Why?  So
         * we don't have to double the overhead for each template.
         * If we didn't cache, it would be hitting the file system
         * twice as much (file_exists() & filemtime() [twice each]).
         */
        $this->template["cached"] = true;
        return true;
    }
}
