<?php

namespace YesWiki\Kernel\Service;

use Psr\Container\ContainerInterface;
use YesWiki\Files\Service\Storage;
use YesWiki\Kernel\Asset\AssetEntry;
use YesWiki\Kernel\Asset\AssetSet;

/**
 * The request-scoped registry of declared assets, and the capture scopes that let a render
 * hand its assets back instead of leaving them in a global for a footer to flush.
 *
 * Ticket 14 replaced `$GLOBALS['css']` / `$GLOBALS['js']`. Those worked for exactly one
 * shape of response -- a whole page ending in `{{linkjavascript}}` -- and silently lost the
 * assets of anything else, which is why the form designer's map preview arrived as markup
 * with no leaflet behind it. Two places had already hand-rolled the missing mechanism rather
 * than fix it: the preview endpoint diffed `strlen($GLOBALS['css'])` across a render, and the
 * text-search action saved and restored `$GLOBALS['js']` to *discard* the assets of the
 * entries it rendered into its results. Both wanted a scope; neither could have one.
 *
 * @see AssetEntry
 * @see AssetSet
 * @see docs/adr/0014-assets-are-declared-by-a-render-not-accumulated-by-a-request.md
 */
class AssetRegistry implements RequestScopedState
{
    // Backward compatibility : in case some extensions were using javascript code previously in
    // tools/templates (and which have been moved elsewhere), we handle it
    protected const BACKWARD_PATH_MAPPING = [
        'tools/templates/libs/vendor/vue/vue.js' => 'javascripts/vendor/vue/vue.js',
        'tools/bazar/libs/vendor/leaflet/leaflet.js' => 'javascripts/vendor/leaflet/leaflet.min.js',
        'tools/bazar/libs/vendor/leaflet/leaflet-providers.js' => 'javascripts/vendor/leaflet-providers/leaflet-providers.js',
        'tools/bazar/libs/vendor/leaflet/leaflet.css' => 'styles/vendor/leaflet/leaflet.css',
        'tools/bazar/libs/vendor/leaflet/markercluster/MarkerCluster.css' => 'styles/vendor/leaflet-markercluster/leaflet-markercluster.css',
        'tools/bazar/libs/vendor/leaflet/markercluster/leaflet.markercluster.js' => 'javascripts/vendor/leaflet-markercluster/leaflet-markercluster.min.js',
        'tools/bazar/libs/vendor/leaflet/fullscreen/Control.FullScreen.css' => 'styles/vendor/leaflet-fullscreen/leaflet-fullscreen.css',
        'tools/bazar/libs/vendor/leaflet/fullscreen/Control.FullScreen.js' => 'javascripts/vendor/leaflet-fullscreen/leaflet-fullscreen.js',
        'tools/bazar/libs/vendor/leaflet/ajax/dist/leaflet.ajax.min.js' => 'javascripts/vendor/leaflet-ajax/leaflet.ajax.min.js',
        'tools/bazar/libs/vendor/leaflet/spiderfier/oms.min.js' => 'javascripts/vendor/leaflet-spiderfier/oms.min.js',
        'tools/bazar/libs/vendor/moment.min.js' => 'javascripts/vendor/moment/moment-with-locales.min.js',
        'tools/templates/libs/vendor/iframeResizer.contentWindow.min.js' => 'javascripts/vendor/iframe-resizer/iframeResizer.contentWindow.min.js',
        'tools/templates/libs/vendor/iframeResizer.min.js' => 'javascripts/vendor/iframe-resizer/iframeResizer.min.js',
        // ticket 12 (templates absorbed into core)
        'tools/templates/libs/vendor/marked/marked.min.js' => 'javascripts/vendor/marked/marked.min.js',
        'tools/templates/libs/vendor/wow.min.js' => 'javascripts/vendor/wow.min.js',
        'tools/templates/libs/vendor/izmir/izmir.min.css' => 'styles/vendor/izmir/izmir.min.css',
        'tools/templates/presentation/styles/animate.css' => 'styles/animate.css',
        'tools/templates/presentation/styles/install.css' => 'styles/install.css',
        'tools/templates/javascripts/change-theme.js' => 'javascripts/change-theme.js',
        'tools/templates/javascripts/template-edit.js' => 'javascripts/template-edit.js',
        'tools/templates/javascripts/reload-gerer-droits.js' => 'javascripts/reload-gerer-droits.js',
    ];

    protected const PRODUCTION_PATH_MAPPING = [
        'javascripts/vendor/vue/vue.js' => 'javascripts/vendor/vue/vue.min.js',
    ];

    protected ContainerInterface $container;

    protected UrlFormatter $urlFormatter;

    /** Everything declared during this request that no scope captured and nothing has emitted. */
    private AssetSet $page;

    /** @var list<AssetSet> open capture scopes, innermost last */
    private array $scopes = [];

    /** Settled once per request: see assetsVersion(). */
    private ?string $assetsVersion = null;

    public function __construct(ContainerInterface $container, UrlFormatter $urlFormatter)
    {
        $this->urlFormatter = $urlFormatter;
        $this->container = $container;
        $this->page = new AssetSet();
    }

    /**
     * Render $render with a fresh scope, and return what it declared.
     *
     * The scope is isolating and non-propagating: assets registered inside do not reach the
     * page. A fragment is therefore self-contained by construction -- it re-declares leaflet
     * even when the surrounding page already has it, and the browser-side registry is what
     * decides not to load it twice. The server states needs; the client states what it has.
     *
     * Rendering and discarding is *ignoring the return value*, which is how the text-search
     * action expresses "do not drag every matched entry's field libraries onto the results
     * page" -- previously a save/restore pair whose intent had to be inferred.
     *
     * @template T
     *
     * @param callable():T $render
     */
    public function capture(callable $render, mixed &$result = null): AssetSet
    {
        $this->scopes[] = new AssetSet();
        try {
            $result = $render();
        } finally {
            /** @var AssetSet $scope */
            $scope = array_pop($this->scopes);
        }

        return $scope;
    }

    public function addCss(string $style): void
    {
        if ($style === '') {
            return;
        }
        $this->collect(AssetEntry::cssInline($style));
    }

    public function addCssFile(
        string $file,
        string $conditionStart = '',
        string $conditionEnd = '',
        string $attributes = ''
    ): void {
        $url = $this->resolveUrl($file);
        if ($url === null) {
            return;
        }
        $this->collect(AssetEntry::cssFile($url, $conditionStart, $conditionEnd, $attributes));
    }

    /**
     * The URL a file would be served from, for callers that need the address rather than a
     * tag -- a screen handing paths to its JavaScript, say. Null when there is no such file.
     *
     * The same resolution every other method here uses, so a farm instance gets its
     * cache/assets/{version}/ path and not a source-tree one that only exists on the shared
     * code tree.
     */
    public function urlFor(string $file): ?string
    {
        return $this->resolveUrl($file);
    }

    /**
     * A stylesheet link rendered straight to markup rather than registered, for the few
     * callers that place their own tags and need to control the order themselves.
     */
    public function linkCssFile(
        string $file,
        string $conditionStart = '',
        string $conditionEnd = '',
        string $attributes = ''
    ): string {
        $url = $this->resolveUrl($file);
        if ($url === null) {
            return '';
        }

        return AssetEntry::cssFile($url, $conditionStart, $conditionEnd, $attributes)->toHtml();
    }

    public function addJs(string $script, bool $module = false, bool $first = false): void
    {
        if ($script === '') {
            return;
        }
        $this->collect(AssetEntry::jsInline($script, $module, $first));
    }

    public function addJsFile(string $file, bool $first = false, bool $module = false): void
    {
        $url = $this->resolveUrl($file);
        if ($url === null) {
            return;
        }
        $this->collect(AssetEntry::jsFile($url, $first, $module));
    }

    /** Everything declared outside any scope so far. */
    public function pageAssets(): AssetSet
    {
        return $this->page;
    }

    /**
     * Take the matching declared assets out of the registry and hand them to the caller.
     *
     * Draining rather than marking-as-emitted, because that is what the two emission points
     * have always done: {{linkstyle}} emptied $GLOBALS['css'] so that {{linkjavascript}}
     * would pick up whatever the page body declared afterwards. Anything registered again
     * after a drain is emitted again -- which is how a second {{linkjavascript}} in one
     * request still renders the core scripts.
     *
     * Two emission points exist until ticket 15 folds them into the skeleton's head block:
     * the head takes the stylesheets, the foot takes the rest. $filter is how they divide.
     *
     * @param callable(AssetEntry):bool|null $filter
     */
    public function drain(?callable $filter = null): AssetSet
    {
        $taken = new AssetSet();
        $kept = new AssetSet();
        foreach ($this->page->entries() as $entry) {
            if ($filter === null || $filter($entry)) {
                $taken->add($entry);
            } else {
                $kept->add($entry);
            }
        }
        $this->page = $kept;

        return $taken;
    }

    private function collect(AssetEntry $entry): void
    {
        if ($this->scopes !== []) {
            $this->scopes[array_key_last($this->scopes)]->add($entry);

            return;
        }
        $this->page->add($entry);
    }

    /**
     * A repo-relative path, an absolute URL or a farm-published path, resolved to the URL a
     * browser should fetch -- or null when the file exists nowhere, which is how a
     * registration for a missing asset stays silent rather than emitting a 404.
     */
    private function resolveUrl(string $file): ?string
    {
        if ($file === '') {
            return null;
        }

        $file = $this->mapFilePath($file);

        if (str_starts_with($file, 'http://') || str_starts_with($file, 'https://')) {
            return $file;
        }

        $bucket = $this->bucketUrl($file);
        if ($bucket !== null) {
            return $bucket === '' ? null : $this->busted($bucket, $file);
        }

        if ($this->publishToCache()) {
            $published = AssetPublisher::publishedUrl($file, $this->assetsVersion());
            if ($published === null) {
                return null;
            }

            // no ?v= : the published path already embeds the version
            return $this->urlFormatter->getBaseUrl() . '/' . $published;
        }

        // NB: the source-tree check covers files absent from the instance docroot - the
        // URL still works there thanks to AssetPublisher's direct-path interception
        if (!file_exists($file) && !file_exists(YESWIKI_PROGRAM_DIR . '/' . $file)) {
            return null;
        }

        return $this->busted($this->urlFormatter->getBaseUrl() . '/' . $file, $file);
    }

    /**
     * The URL a Public path has when this instance keeps its own files in a bucket -- `''` when
     * it does and the object is not there, and null when this asset is none of Storage's business.
     */
    private function bucketUrl(string $file): ?string
    {
        if (!str_starts_with($file, 'custom/') || str_starts_with($file, 'custom/extensions/')) {
            return null;
        }
        $storage = $this->container->get(Storage::class);
        if (!$storage->isRemote($file)) {
            return null;
        }

        return $storage->exists($file) ? $storage->url($file) : '';
    }

    /** The release the instance is on, so a browser stops serving the last one's copy of a file. */
    private function busted(string $url, string $file): string
    {
        $config = $this->container->get(RuntimeConfig::class);
        $revision = $config->getValue('yeswiki_release', null);

        // In debug, bust on the file's own mtime instead of the release string. `?v={release}`
        // is right for a released instance and actively wrong on a working copy: the release
        // does not change when a file does, so an edited script keeps being served from cache
        // under an unchanged URL. That is not merely stale -- ticket 14 made ~25 initialisers
        // depend on helpers defined in another file, and a cached copy of *that* file breaks
        // every one of them with a ReferenceError. Caching is worth nothing in debug anyway.
        if ($config->getValue('debug')) {
            $localPath = file_exists($file) ? $file : YESWIKI_PROGRAM_DIR . '/' . $file;
            $mtime = @filemtime($localPath);
            if ($mtime !== false) {
                $revision = $revision . '-' . $mtime;
            }
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url . ($revision ? $separator . 'v=' . $revision : '');
    }

    /**
     * Farm instances (shared sources outside the instance docroot) get their css/js published
     * into the instance's cache/assets/{version}/ folder and referenced from there, so they
     * need no symlinks/aliases to the YesWiki sources (see AssetPublisher). Standalone
     * installs serve the source files directly.
     */
    private function publishToCache(): bool
    {
        return defined('YESWIKI_PROGRAM_DIR') && defined('YESWIKI_INSTANCE_DIR')
            && YESWIKI_PROGRAM_DIR !== YESWIKI_INSTANCE_DIR;
    }

    /**
     * The folder published assets live under, and with it the cache key every browser holds
     * them by -- they are served immutable, so a URL that does not move is a file that never
     * changes again as far as a returning visitor is concerned.
     *
     * The release string alone is that key only for an instance that follows releases. On one
     * following a branch it never moves: the wiki republishes the updated file on disk, keeps
     * offering it at the same URL, and every browser that has been there goes on running the
     * code it cached. A fix that is deployed, correct, and invisible.
     *
     * So the release carries a stamp that AssetPublisher bumps when it finds a published file
     * older than its source, i.e. when the sources were updated. Memoized: within one request
     * every URL must name the same folder, or the imports resolving between them break.
     */
    private function assetsVersion(): string
    {
        if ($this->assetsVersion !== null) {
            return $this->assetsVersion;
        }

        $release = $this->container->get(RuntimeConfig::class)->getValue('yeswiki_release');
        $version = AssetPublisher::sanitizeVersion($release !== '' ? $release : 'dev');
        $stamp = AssetPublisher::publishedStamp();

        return $this->assetsVersion = AssetPublisher::sanitizeVersion(
            $stamp === '' ? $version : $version . '-' . $stamp
        );
    }

    private function mapFilePath(string $file): string
    {
        // Handle backward compatibility
        if (array_key_exists($file, self::BACKWARD_PATH_MAPPING)) {
            $file = self::BACKWARD_PATH_MAPPING[$file];
        }

        // Handle production environment
        if (!$this->container->get(RuntimeConfig::class)->getValue('debug')) {
            if (array_key_exists($file, self::PRODUCTION_PATH_MAPPING)) {
                $file = self::PRODUCTION_PATH_MAPPING[$file];
            }
        }

        return $file;
    }

    public function startNewRequest(): void
    {
        // what a page declared belongs to that page (ticket 14)
        $this->page = new AssetSet();
        $this->scopes = [];
    }
}
