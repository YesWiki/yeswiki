<?php

namespace YesWiki\Render\Service;

use YesWiki\Kernel\Service\AssetRegistry;

/** Registers the CSS/JS that rendered page content turns out to need. */
class ContentAssetScanner
{
    /**
     * marker => assets to register when the marker appears in rendered output.
     *
     * @return array<string, array{css?: list<string>, js?: list<string>, jsInline?: string}>
     */
    private static function rules(): array
    {
        return [
            'mermaid' => [
                'jsInline' => <<<'JS'
                    import mermaid from "./javascripts/vendor/mermaid/mermaid.esm.min.mjs";
                    // ticket 16: ywInitEach, not DOMContentLoaded -- a diagram on a page reached
                    // by an htmx navigation would otherwise never be rendered. startOnLoad is off
                    // because this decides when to run, once per diagram.
                    ywInitEach(".mermaid", function(element) {
                        mermaid.initialize({
                            startOnLoad: false,
                            fontFamily: 'inherit',
                            theme: "base",
                            themeCSS: ':root { --mermaid-font-family: inherit;} .titleText, .taskText, .sectionTitle, .grid , .grid .tick text {font-family:inherit;} g.label {color:inherit;}'
                        });
                        mermaid.run({ nodes: [element] });
                    })
                    JS,
            ],
        ];
    }

    private AssetRegistry $assets;

    /**
     * @var list<string> markers already registered this request
     */
    private array $seen = [];

    public function __construct(AssetRegistry $assets)
    {
        $this->assets = $assets;
    }

    /** Inspect $html and register whatever it needs. */
    public function scan(string $html): string
    {
        foreach (self::rules() as $marker => $assets) {
            if (in_array($marker, $this->seen, true)) {
                continue;
            }
            if (!$this->mentions($html, $marker)) {
                continue;
            }
            $this->seen[] = $marker;
            foreach ($assets['css'] ?? [] as $css) {
                $this->assets->addCssFile($css);
            }
            foreach ($assets['js'] ?? [] as $js) {
                $this->assets->addJsFile($js);
            }
            if (isset($assets['jsInline'])) {
                $this->assets->addJs($assets['jsInline'], true);
            }
        }

        return $html;
    }

    /** Is $marker present as a whole class name in a class attribute? */
    private function mentions(string $html, string $marker): bool
    {
        return (bool)preg_match(
            '/class\s*=\s*(["\'])[^"\']*(?<![-\w])' . preg_quote($marker, '/') . '(?![-\w])[^"\']*\1/i',
            $html
        );
    }

    /**
     * Markers this scanner knows about.
     *
     * @return list<string>
     */
    public static function markers(): array
    {
        return array_keys(self::rules());
    }
}
