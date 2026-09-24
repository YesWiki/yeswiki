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

    /** Raw HTML a page or an entry may not carry. `style` and `script` stay allowed while wikis move over from Doryphore. */
    public const DISALLOWED_HTML_TAGS = ['title', 'textarea', 'xmp', 'noembed', 'noframes', 'plaintext'];

    protected ParameterBagInterface $params;
    protected ?Sanitizer $sanitizer;
    private ?\HTMLPurifier $purifier;

    public function __construct(
        ParameterBagInterface $params,
        private readonly Storage $storage,
        private readonly LocalFiles $localFiles,
        private readonly RuntimeConfig $config,
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
     * Clean HTML with $clean, but put back the `<style>` and `<script>` blocks raw HTML may carry, as pages do.
     *
     * @param callable(string): string $clean
     */
    public function keepingEmbeddedBlocks(string $dirtyHtml, callable $clean): string
    {
        $kept = $this->config->getValue('allow_raw_html', true) ? array_diff(['style', 'script'], $this->disallowedTags()) : [];
        if ($kept === []) {
            return $clean($dirtyHtml);
        }

        $blocks = [];
        $marker = 'ywkeptblock' . bin2hex(random_bytes(8));
        $withMarkers = preg_replace_callback(
            '#<(' . implode('|', $kept) . ')\b[^>]*>.*?</\1\s*>#is',
            static function (array $block) use (&$blocks, $marker): string {
                $key = $marker . 'n' . count($blocks) . 'z';
                $blocks[$key] = $block[0];

                return $key;
            },
            $dirtyHtml
        );

        return strtr($clean($withMarkers ?? $dirtyHtml), $blocks);
    }

    /** @return list<string> */
    private function disallowedTags(): array
    {
        $tags = $this->config->getValue('disallowed_html_tags', self::DISALLOWED_HTML_TAGS);

        return array_values(array_map('strval', is_array($tags) ? $tags : self::DISALLOWED_HTML_TAGS));
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
