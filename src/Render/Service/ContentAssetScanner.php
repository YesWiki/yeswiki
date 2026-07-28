<?php

namespace YesWiki\Render\Service;

use YesWiki\Kernel\Service\AssetsManager;

/**
 * Registers the CSS/JS that rendered page content turns out to need.
 *
 * Some client-side libraries are opted into from *content* rather than from code: a page
 * author writes `<div class="mermaid">` or `class="c4-izmir"`, and the corresponding
 * library has to be on the page. Nothing in the request pipeline can know that in advance,
 * so the rendered HTML is inspected once and the matching assets are registered.
 *
 * This replaces `formatters/wakka__.php`, the last file in `formatters/` and the last user
 * of `Performer`'s filename-hook convention (wave-two ticket 06). Behaviour is preserved --
 * still a scan over final output, so it catches markup produced by actions and raw HTML,
 * not just by Markdown -- but it is now a declarative table in a testable service instead
 * of a sequence of ad-hoc `preg_match` blocks in a file whose name meant "run me after the
 * wakka formatter".
 *
 * A CommonMark extension was considered and rejected for this: it would only see nodes the
 * Markdown parser produced, and would silently stop working for content emitted by an
 * action or written as raw HTML -- which is most of what actually uses these classes.
 */
class ContentAssetScanner
{
    /**
     * marker => assets to register when the marker appears in rendered output.
     *
     * `class` is matched as a whole word inside a class attribute, so `mermaid` does not
     * fire on `mermaid-something` and `c4-izmir` does not fire on a longer name.
     *
     * @var array<string, array{css?: list<string>, js?: list<string>, jsInline?: string}>
     */
    private const RULES = [
        'mermaid' => [
            'jsInline' => <<<'JS'
                import mermaid from "./javascripts/vendor/mermaid/mermaid.esm.min.mjs";
                document.addEventListener("DOMContentLoaded", function() {
                    mermaid.initialize({
                        startOnLoad: true,
                        fontFamily: 'inherit',
                        theme: "base",
                        themeCSS: ':root { --mermaid-font-family: inherit;} .titleText, .taskText, .sectionTitle, .grid , .grid .tick text {font-family:inherit;} g.label {color:inherit;}'
                    });
                })
                JS,
        ],
        'c4-izmir' => [
            'css' => ['styles/vendor/izmir/izmir.min.css'],
        ],
    ];

    private AssetsManager $assets;

    /** @var list<string> markers already registered this request */
    private array $seen = [];

    // AssetsManager rather than Wiki: registering an asset is exactly what that service is
    // for, and going through Wiki would add three more $wiki-> call sites to a wave whose
    // whole point is removing them.
    public function __construct(AssetsManager $assets)
    {
        $this->assets = $assets;
    }

    /**
     * Inspect $html and register whatever it needs. Returns $html untouched so it can sit
     * inline in a render pipeline.
     */
    public function scan(string $html): string
    {
        foreach (self::RULES as $marker => $assets) {
            if (in_array($marker, $this->seen, true)) {
                continue; // one registration per request, however many times it appears
            }
            if (!$this->mentions($html, $marker)) {
                continue;
            }
            $this->seen[] = $marker;
            foreach ($assets['css'] ?? [] as $css) {
                $this->assets->AddCSSFile($css);
            }
            foreach ($assets['js'] ?? [] as $js) {
                $this->assets->AddJavascriptFile($js);
            }
            if (isset($assets['jsInline'])) {
                // second arg: emitted as a module, which the mermaid ESM import needs
                $this->assets->AddJavascript($assets['jsInline'], true);
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
     * Markers this scanner knows about. Exposed for tests and for anyone wondering what
     * content can pull an asset in.
     *
     * @return list<string>
     */
    public static function markers(): array
    {
        return array_keys(self::RULES);
    }
}
