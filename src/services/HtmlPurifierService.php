<?php

namespace YesWiki\Core\Service;

use enshrined\svgSanitize\Sanitizer;
use HTMLPurifier;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Wiki;

class HtmlPurifierService
{
    public const HTMLPURIFIER_CACHE_FOLDER = 'cache/HTMLpurifier';

    protected $params;
    protected $sanitizer;
    protected $wiki;
    private $purifier;
    private $antixss;

    public function __construct(Wiki $wiki, ParameterBagInterface $params)
    {
        $this->params = $params;
        $this->wiki = $wiki;
        $this->purifier = null;  // will load HtmlPurifier library
        $this->antixss = null;   // will load antiXSS library
        $this->sanitizer = null; // will load svgSanitize library
    }

    /**
     * load a HTMLpurifier if needed
     * configure it
     *  then use it to clean HTML.
     */
    public function cleanHTML(string $dirty_html): string
    {
        if (!$this->params->get('htmlPurifierActivated')) {
            return $dirty_html;
        }
        if (is_null($this->purifier)) {
            $config = \HTMLPurifier_Config::createDefault();

            // add extra attributes for links in new tab
            $config->set('Attr.AllowedFrameTargets', [
                '_blank',
                '_parent',
                '_top',
            ]);

            // set the cache folder
            // doc : http://htmlpurifier.org/live/configdoc/plain.html#Cache.SerializerPath
            if (!is_dir(self::HTMLPURIFIER_CACHE_FOLDER)) {
                mkdir(self::HTMLPURIFIER_CACHE_FOLDER, 0777, true);
            }
            $config->set('Cache.SerializerPath', realpath(self::HTMLPURIFIER_CACHE_FOLDER));

            $this->purifier = new \HTMLPurifier($config);
        }

        return $this->purifier->purify($dirty_html);
    }

    /**
     * @param string $content of svg
     *
     * @return string $content
     */
    public function sanitizeSVG(string $content)
    {
        if (!$this->params->get('htmlPurifierActivated')) {
            return $content;
        }
        if (is_null($this->sanitizer)) {
            $this->sanitizer = new Sanitizer();
        }

        return $this->sanitizer->sanitize($content);
    }

    /**
     * @param string $filename  path to file
     * @param string $extension file extension
     *
     * @return mixed false if problem or int of filesize
     */
    public function cleanFile(string $filename, string $extension)
    {
        if (file_exists($filename)) {
            if (in_array($extension, ['svg', 'html', 'htm'])) {
                $content = file_get_contents($filename);
                if ($extension === 'svg') {
                    return file_put_contents($filename, $this->sanitizeSVG($content));
                } elseif ($extension === 'html' || $extension === 'htm') {
                    return file_put_contents($filename, $this->cleanHTML($content));
                }
            } else {
                return true; // the file type doesn't need to be cleaned
            }
        } else {
            return false; // TODO : maybe raise an explicit error in case of non-existing file
        }
    }
}
