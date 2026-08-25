<?php

namespace YesWiki\Kernel\Service;

use enshrined\svgSanitize\Sanitizer;
use HTMLPurifier;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Files\Service\LocalFiles;
use YesWiki\Files\Service\Storage;

class HtmlPurifierService
{
    public const HTMLPURIFIER_CACHE_FOLDER = 'cache/HTMLpurifier';

    protected ParameterBagInterface $params;
    protected ?Sanitizer $sanitizer;
    private ?\HTMLPurifier $purifier;

    public function __construct(
        ParameterBagInterface $params,
        private readonly Storage $storage,
        private readonly LocalFiles $localFiles,
    ) {
        $this->params = $params;
        $this->purifier = null;
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

            // HTMLPurifier writes its serialised definitions itself, with PHP's own filesystem
            // functions, so it needs a real directory rather than a Storage path. That is why
            // `cache/` is Runtime and not Public (ADR-0022): a bucket would be a cache it cannot
            // write to.
            $this->storage->makeDirectory(self::HTMLPURIFIER_CACHE_FOLDER);
            $config->set('Cache.SerializerPath', $this->localFiles->realPath($this->storage->absolutePath(self::HTMLPURIFIER_CACHE_FOLDER)));

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
     * @return string|false the sanitized SVG, or false when the SVG could not be parsed
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
        // $filename is a leased local path: every caller comes through
        // `Storage::withLocalTarget()`, because HTMLPurifier::cleanFile wants a filename rather
        // than a stream (ADR-0022 names it among the four libraries that do).
        if (!$this->localFiles->isFile($filename)) {
            return false;
        }
        if (!in_array($extension, ['svg', 'html', 'htm'])) {
            return true;
        }
        $content = $this->localFiles->read($filename);
        if ($content === '') {
            return false;
        }

        $cleaned = $extension === 'svg' ? $this->sanitizeSVG($content) : $this->cleanHTML($content);
        if ($cleaned === false) {
            return false;
        }

        return $this->localFiles->write($filename, $cleaned) ? \strlen($cleaned) : false;
    }
}
