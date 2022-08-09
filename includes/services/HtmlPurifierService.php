<?php

namespace YesWiki\Core\Service;

use HTMLPurifier;
use HTMLPurifier_Config;
use enshrined\svgSanitize\Sanitizer;
use voku\helper\AntiXSS;
use YesWiki\Core\Service\LinkTracker;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Security\Controller\SecurityController;
use YesWiki\Wiki;

class HtmlPurifierService
{
    protected $params;
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
     *  then use it to clean HTML
     */
    public function cleanHTML(string $dirty_html): string
    {
        if (!$this->params->get('htmlPurifierActivated')) {
            return $dirty_html;
        }
        if (is_null($this->purifier)) {
            $config = HTMLPurifier_Config::createDefault();

            //add extra attributes for links in new tab
            $config->set( 'HTML.Allowed', 'a[href|target]');
            $config->set('Attr.AllowedFrameTargets', array('_blank'));

            $this->purifier = new HTMLPurifier($config);
        }

        return $this->purifier->purify($dirty_html);
    }

    /**
     * load a anti XSS if necessary
     * search for XSS scripts and clean content
     */
    public function cleanXSS(string $dirtyContent): string
    {
        if (!$this->params->get('htmlPurifierActivated')) {
            return $dirtyContent;
        }
        if (is_null($this->antixss)) {
            $this->antiXss = new AntiXSS();
        }
        return $this->antiXss->xss_clean($dirtyContent);
    }

    /**
     * @param string $content of svg
     * @return string $content
     */
    public function sanitizeSVG(string $content): bool|string
    {
        if (!$this->params->get('htmlPurifierActivated')) {
            return $dirtyContent;
        }
        if (is_null($this->sanitizer)) {
            $this->sanitizer = new Sanitizer();
        }

        return $this->sanitizer->sanitize($content);
    }

    /**
     * @param string $content of svg
     * @return mixed false if problem or int of filesize
     */
    public function cleanFile(string $filename, string $extension): bool|int
    {
        if (file_exists($filename)) {
	   if (in_array($extension, ['svg', 'xml', 'html', 'htm'])) {	
		$content = file_get_contents($filename);
                if ($extension === 'svg') {
                    return file_put_contents($filename, $this->sanitizeSVG($content));
                } elseif ($extension === 'xml') {
                    return file_put_contents($filename, $this->cleanXSS($content));
                } elseif ($extension === 'html' || $extension === 'htm' ) {
                    return file_put_contents($filename, $this->cleanHTML($content));
                }
	   } else {
	       return true; // the file type doesn't need to be cleaned
	   }
        } else {
            return false; //TODO : maybe raise an explicit error in case of non-existing file
        }
    }
}
