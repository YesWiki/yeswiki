<?php
namespace YesWiki;

class Templates extends \YesWiki\Wiki
{
    public function GetMethod()
    {
        if ($this->method=='iframe') {
            return 'show';
        } elseif ($this->method=='editiframe') {
            return 'edit';
        } else {
            return \YesWiki\Wiki::GetMethod();
        }
    }
    
    /**
     *
     * @param $file string the file name you want to load
     */
    public function generateCacheId($tool, $file, $lastModified, $semantic_template = null)
    {
        $filename = basename($file);
        // On cherche si le theme existe dans theme custom
        $templatefile = 'custom/themes/tools/' . $tool . '/templates/' . $filename;
        if (!is_file($templatefile)) {
            // On cherche si le theme existe dans le theme custom de l'outil
            $templatefile = 'custom/themes/tools/' . $tool . '/templates/' . $filename;
            if (!is_file($templatefile)) {
                // On cherche si le theme existe dans le theme courant
                $templatefile = 'themes/' . $this->config["favorite_theme"] . '/tools/' . $tool . '/templates/' . $filename;
                if (!is_file($templatefile)) {
                    // On cherche si le theme existe dans themes/tools
                    $templatefile = 'themes/tools/' . $tool . '/templates/' . $filename;
                    if (!is_file($templatefile)) {
                        // On cherche dans les templates du tools
                        $templatefile = 'tools/' . $tool . '/presentation/templates/' . $filename;
                        if (!is_file($templatefile)) {
                            $templatefile = 'tools/' . $tool . '/presentation/templates/' . $semantic_template;
                            if (!is_file($templatefile)) {
                                // on quitte si aucun template de trouvé
                                echo '<div class="alert alert-danger">' . _t('TEMPLATE_FILE_NOT_FOUND') . ' : "' . $filename . '".</div>';
                                exit;
                            }
                        }
                    }
                }
            }
        }
        $cacheid = 'cache/'.$this->getPageTag().'-'.$tool.'-'.$filename.($lastModified ? '-last-modified-'.$lastModified : '');
        $this->template[$cacheid]['file'] = $templatefile;

        return $cacheid;
    }
    /**
     *
     * @param $file string the file name you want to load
     */
    public function renderTemplate($tool, $file, $values, $lastModified = false, $semantic_template = null)
    {
        $cacheid = $this->generateCacheId($tool, $file, $lastModified, $semantic_template);
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
    public function isTemplateCached($cacheid)
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
}
