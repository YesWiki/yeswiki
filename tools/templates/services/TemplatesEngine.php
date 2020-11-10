<?php

namespace YesWiki\Templates\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Wiki;

class TemplatesEngine
{
    protected $wiki;
    protected $params;
    protected $templates;

    public function __construct(Wiki $wiki, ParameterBagInterface $params)
    {
        $this->wiki = $wiki;
        $this->params = $params;
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
        $paths = [
            'custom/templates/' . $tool . '/templates/' . $filename,
            'custom/templates/' . $tool . '/' . $filename,
            'custom/themes/tools/' . $tool . '/templates/' . $filename,
            'themes/tools/' . $tool . '/templates/' . $filename,
            'themes/' . $this->params->get('favorite_theme') . '/tools/' . $tool . '/templates/' . $filename,
            'templates/' . $tool . '/templates/' . $filename,
            'templates/' . $tool . '/' . $filename,
            'tools/' . $tool . '/presentation/templates/' . $filename,
            'tools/' . $tool . '/presentation/templates/' . $semantic_template
        ];
        $templatefile = null;
        foreach ($paths as $path) {
            if (is_file($path)) {
                $templatefile = $path;
                break;
            }
        }
        // on quitte si aucun template de trouvé
        if (!$templatefile) {
            echo '<div class="alert alert-danger">' . _t('TEMPLATE_FILE_NOT_FOUND') . ' : "' . $filename . '".</div>';
            exit;
        }

        $cacheid = 'cache/'.$this->wiki->getPageTag().'-'.$tool.'-'.$filename.($lastModified ? '-last-modified-'.$lastModified : '');
        $this->template[$cacheid]['file'] = $templatefile;

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
