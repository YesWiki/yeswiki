<?php

namespace YesWiki\Kernel\Service;

use Psr\Container\ContainerInterface;
use YesWiki\Files\Service\ProgramFiles;
use YesWiki\Files\Service\Storage;
use YesWiki\Kernel\Asset\AssetEntry;
use YesWiki\Kernel\Asset\AssetSet;

/**
 * The request-scoped registry of declared assets, and the capture scopes that let a render hand its assets back instead of leaving them in a global for a footer to flush.
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
        'tools/templates/libs/vendor/iframeResizer.contentWindow.min.js' => 'javascripts/vendor/iframe-resizer/iframeResizer.contentWindow.min.js',
        'tools/templates/libs/vendor/iframeResizer.min.js' => 'javascripts/vendor/iframe-resizer/iframeResizer.min.js',
        // ticket 12 (templates absorbed into core)
        'tools/templates/libs/vendor/wow.min.js' => 'javascripts/yw-core.js',
        'tools/templates/presentation/styles/animate.css' => 'styles/vendor/animate/animate.min.css',
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
     * The URL a file would be served from, for callers that need the address rather than a tag -- a screen handing paths to its JavaScript, say.
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

            return $this->urlFormatter->getBaseUrl() . '/' . $published;
        }

        // An asset is either the wiki's own (`custom/`, which may be in a bucket) or the
        // release's, and both sides have to be asked before this says there is no such file.
        if (!$this->container->get(ProgramFiles::class)->findExists($file, $file)) {
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

        if ($config->getValue('debug')) {
            $programFiles = $this->container->get(ProgramFiles::class);
            $mtime = $programFiles->instanceHas($file)
                ? $this->container->get(Storage::class)->lastModified($file)
                : $programFiles->modifiedAt($file);
            if ($mtime > 0) {
                $revision = $revision . '-' . $mtime;
            }
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url . ($revision ? $separator . 'v=' . $revision : '');
    }

    /**
     * Farm instances (shared sources outside the instance docroot) get their css/js published into the instance's cache/assets/{version}/ folder and referenced from there, so they need no symlinks/aliases to the YesWiki sources (see AssetPublisher).
     */
    private function publishToCache(): bool
    {
        return defined('YESWIKI_PROGRAM_DIR') && defined('YESWIKI_INSTANCE_DIR')
            && YESWIKI_PROGRAM_DIR !== YESWIKI_INSTANCE_DIR;
    }

    /**
     * The folder published assets live under, and with it the cache key every browser holds them by -- they are served immutable, so a URL that does not move is a file that never changes again as far as a returning visitor is concerned.
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
        if (array_key_exists($file, self::BACKWARD_PATH_MAPPING)) {
            $file = self::BACKWARD_PATH_MAPPING[$file];
        }

        if (!$this->container->get(RuntimeConfig::class)->getValue('debug')) {
            if (array_key_exists($file, self::PRODUCTION_PATH_MAPPING)) {
                $file = self::PRODUCTION_PATH_MAPPING[$file];
            }
        }

        return $file;
    }

    public function startNewRequest(): void
    {
        $this->page = new AssetSet();
        $this->scopes = [];
    }
}
