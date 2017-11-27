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
    if (!empty($file) && file_exists($file)) {
        if (!strpos($GLOBALS['css'], '<link rel="stylesheet" href="'.$this->getBaseUrl().'/'.$file.'">')) {
            $GLOBALS['css'] .= '  '.$conditionstart."\n"
              .'  <link rel="stylesheet" href="'.$this->getBaseUrl().'/'.$file.'">'
              ."\n".'  '.$conditionend."\n";
        }
    } elseif (strpos($file, "http://") === 0 || strpos($file, "https://") === 0) {
        if (!strpos($GLOBALS['css'], '<link rel="stylesheet" href="'.$file.'">')) {
            $GLOBALS['css'] .= '  '.$conditionstart."\n"
                .'  <link rel="stylesheet" href="'.$file.'">'."\n"
                .'  '.$conditionend."\n";
        }
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

function AddJavascriptFile($file, $first = false)
{
    if (!isset($GLOBALS['js'])) {
        $GLOBALS['js'] = '';
    }
    if (!empty($file) && file_exists($file)) {
        if (!strpos($GLOBALS['js'], '<script src="'.$this->getBaseUrl().'/'.$file.'"></script>')) {
            if ($first) {
                $GLOBALS['js'] = '  <script src="'.$this->getBaseUrl().'/'.$file.'"></script>'."\n".$GLOBALS['js'];
            } else {
                $GLOBALS['js'] .= '  <script src="'.$this->getBaseUrl().'/'.$file.'"></script>'."\n";
            }
        }
    } elseif (strpos($file, "http://") === 0 || strpos($file, "https://") === 0) {
        if (!strpos($GLOBALS['js'], '<script src="'.$file.'"></script>')) {
            $GLOBALS['js'] .= '  <script src="'.$file.'"></script>'."\n";
        }
    }
    return;
}

function GetMethod()
{
    if ($this->method=='iframe') {
        return 'show';
    } elseif ($this->method=='editiframe') {
        return 'edit';
    } else {
        return \YesWiki\Wiki::GetMethod();
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

/**
 *
 * @param $file string the file name you want to load
 */
function generateCacheId($tool, $file, $lastModified, $extraname = '')
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
                echo '<div class="alert alert-danger">'._t('TEMPLATE_FILE_NOT_FOUND').' : "'.$file.'".</div>';
                exit;
            }
        }
    }
    $cacheid = 'cache/'.$this->getPageTag().'-'.$tool.'-'.$file.($lastModified ? '-last-modified-'.$lastModified : '');
    $this->template[$cacheid]['file'] = $templatefile;

    return $cacheid;
}
/**
 *
 * @param $file string the file name you want to load
 */
function renderTemplate($tool, $file, $values, $lastModified = false, $extraname = '')
{
    $cacheid = $this->generateCacheId($tool, $file, $lastModified);
    $this->template[$cacheid]['cached']   = false;
    $this->template[$cacheid]['vars']     = array();

    if (!($this->isTemplateCached($cacheid))) {

        foreach ($values as $key => $val) {
            $this->template[$cacheid]['vars'][$key] = $val;
        }

        extract($this->template[$cacheid]['vars']); // Extract the vars to local namespace
        ob_start();                       // Start output buffering
        include($this->template[$cacheid]['file']); // Include the file
        $contents = ob_get_contents();    // Get the contents of the buffer
        ob_end_clean();                   // End buffering and discard
        // Write the cache
        if ($fp = @fopen($cacheid, 'w')) {
            fwrite($fp, $contents);
            fclose($fp);
        } else {
            die('Unable to write cache.');
        }

        return $contents;
    } else {
        $fp = @fopen($cacheid, 'r');
        $contents = fread($fp, filesize($cacheid));
        fclose($fp);
        return $contents;
    }

}

/**
 * Test to see whether the currently loaded cacheid has a valid
 * corresponding cache file.
 */
function isTemplateCached($cacheid)
{
    // TODO : make it Work..
    return false;

    if (isset($this->template[$cacheid]["cached"]) && $this->template[$cacheid]["cached"]) {
        return true;
    } elseif (!file_exists($cacheid)) {
        // cache file doesn't exist
        $tab = explode('-last-modified-', $cacheid);
        if (isset($tab[1])) {
          // TODO : find old files and unlink()
        }
        return false;
    } else {
        // cache file doesn't exist
        $this->template[$cacheid]["cached"] = true;
        return true;
    }
}
