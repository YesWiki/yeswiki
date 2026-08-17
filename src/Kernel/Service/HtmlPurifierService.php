<?php

namespace YesWiki\Kernel\Service;

use enshrined\svgSanitize\Sanitizer;
use HTMLPurifier;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class HtmlPurifierService
{
    public const HTMLPURIFIER_CACHE_FOLDER = 'cache/HTMLpurifier';

    protected $params;
    protected $sanitizer;
    private $purifier;
    private $antixss;

    public function __construct(ParameterBagInterface $params)
    {
        $this->params = $params;
        $this->purifier = null;
        $this->antixss = null;
        $this->sanitizer = null;
    }

    /** load a HTMLpurifier if needed configure it then use it to clean HTML. */
    public function cleanHTML(string $dirty_html): string
    {
        if (!$this->params->get('htmlPurifierActivated')) {
            return $dirty_html;
        }
        if (is_null($this->purifier)) {
            $config = \HTMLPurifier_Config::createDefault();

            $config->set('Attr.AllowedFrameTargets', [
                '_blank',
                '_parent',
                '_top',
            ]);

            if (!is_dir(self::HTMLPURIFIER_CACHE_FOLDER)) {
                mkdir(self::HTMLPURIFIER_CACHE_FOLDER, 0777, true);
            }
            $config->set('Cache.SerializerPath', realpath(self::HTMLPURIFIER_CACHE_FOLDER));

            $safeIframeRegexp = $this->params->get('htmlPurifierSafeIframeRegexp');
            if (!empty($safeIframeRegexp)) {
                $config->set('HTML.SafeIframe', true);
                $config->set('URI.SafeIframeRegexp', $safeIframeRegexp);

                $config->set('HTML.DefinitionID', 'yeswiki-iframe-attributes');
                $config->set('HTML.DefinitionRev', 1);
                if ($htmlDefinition = $config->maybeGetRawHTMLDefinition()) {
                    $htmlDefinition->addAttribute('iframe', 'allow', 'Text');
                    $htmlDefinition->addAttribute('iframe', 'referrerpolicy', 'Enum#no-referrer,no-referrer-when-downgrade,origin,origin-when-cross-origin,same-origin,strict-origin,strict-origin-when-cross-origin,unsafe-url');
                    $htmlDefinition->addAttribute('iframe', 'allowfullscreen', 'Bool#allowfullscreen');
                }
            }

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
                return true;
            }
        } else {
            return false;
        }
    }
}
