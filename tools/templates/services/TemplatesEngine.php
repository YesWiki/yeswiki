<?php

namespace YesWiki\Templates\Service;

class TemplatesEngine
{
    protected $wiki;
    protected $templates;

    public function __construct($wiki)
    {
        $this->wiki = $wiki;
        $this->templates = [];
    }

    /**
     * @param $file string the file name you want to load
     */
    public function render($tool, $file, $values, $lastModified = false, $semantic_template = null)
    {
        $cacheid = $this->generateCacheId($tool, $file, $lastModified, $semantic_template);
        $this->templates[$cacheid]['cached']   = false;
        $this->templates[$cacheid]['vars']     = array();

        if (!($this->isTemplateCached($cacheid))) {
            foreach ($values as $key => $val) {
                $this->templates[$cacheid]['vars'][$key] = $val;
            }

            extract($this->templates[$cacheid]['vars']); // Extract the vars to local namespace
            ob_start();                       // Start output buffering
            include($this->templates[$cacheid]['file']); // Include the file
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

    protected function generateCacheId($tool, $file, $lastModified, $semantic_template = null)
    {
        $filename = basename($file);
        // On cherche si le template existe dans theme custom
        $templatefile = 'custom/templates/' . $tool . '/templates/' . $filename;
        if (!is_file($templatefile)) {
            // On cherche si le theme existe dans le theme custom de l'outil
            $templatefile = 'custom/themes/tools/' . $tool . '/templates/' . $filename;
            if (!is_file($templatefile)) {
                // On cherche si le theme existe dans le theme courant
                $templatefile = 'themes/tools/' . $tool . '/templates/' . $filename;
                if (!is_file($templatefile)) {
                    // On cherche si le theme existe dans le theme courant
                    $templatefile = 'themes/' . $this->wiki->config["favorite_theme"] . '/tools/' . $tool . '/templates/' . $filename;
                    if (!is_file($templatefile)) {
                        // On cherche si le templates existe dans themes/tools
                        $templatefile = 'templates/' . $tool . '/templates/' . $filename;
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
        }
        $cacheid = 'cache/'.$this->wiki->getPageTag().'-'.$tool.'-'.$filename.($lastModified ? '-last-modified-'.$lastModified : '');
        $this->templates[$cacheid]['file'] = $templatefile;

        return $cacheid;
    }

    /**
     * Test to see whether the currently loaded cacheid has a valid
     * corresponding cache file.
     */
    protected function isCached($cacheid)
    {
        // TODO : make it Work..
        return false;

        if (isset($this->templates[$cacheid]["cached"]) && $this->templates[$cacheid]["cached"]) {
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
            $this->templates[$cacheid]["cached"] = true;
            return true;
        }
    }
}
