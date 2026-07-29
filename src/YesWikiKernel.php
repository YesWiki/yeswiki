<?php

namespace YesWiki;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\Kernel;
use YesWiki\Content\Service\FileManager;
use YesWiki\Core\YesWikiEventCompilerPass;
use YesWiki\Core\YesWikiPerformableCompilerPass;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\EnvironmentConfiguration;
use YesWiki\Kernel\Service\StringUtilService;

/**
 * Owns the DI container build/compile/cache lifecycle for a Wiki instance.
 *
 * registerBundles() is always empty: YesWiki has no bundle system, extensions
 * register their services/parameters directly (see build()) the same way they
 * always have, via a config.yaml loaded into the shared ContainerBuilder.
 */
class YesWikiKernel extends Kernel
{
    /** Historic Wiki::isCli(). NB: 'cli-server' (php -S) is deliberately NOT considered
     * CLI: it serves real web requests with cookies, REMOTE_ADDR and sendable headers. */
    public static function isCli(): bool
    {
        return in_array(php_sapi_name(), ['cli', 'phpdbg'], true);
    }

    private Wiki $wiki;

    public function __construct(Wiki $wiki, string $environment)
    {
        $this->wiki = $wiki;
        // Symfony's Kernel skips its container-cache freshness check entirely when $debug
        // is false (see Kernel::initializeContainer()) - it assumes prod containers are
        // rebuilt via an explicit deploy/cache-clear step. YesWiki has no such step: site
        // settings, tool install/enable/disable all happen live through the admin UI, so the
        // cache must always be freshness-checked. $debug is therefore always true here;
        // $environment (derived from the config's own debug flag) only segregates the
        // cache directory and enables/disables Kernel's other debug-only behaviors
        // (deprecation collection during a rebuild, verbose dumped container code).
        parent::__construct($environment, true);
    }

    public function registerBundles(): iterable
    {
        return [];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        // no-op: container population happens in build(), there is no external
        // per-environment config file to delegate to a Config\Loader here.
    }

    public function getProjectDir(): string
    {
        return \dirname(__DIR__);
    }

    public function getCacheDir(): string
    {
        // keyed on the instance dir (cwd, YesWiki's convention), NOT getProjectDir(): with a
        // farm several instances share the sources but each has its own config/extensions, so the
        // compiled container must live in the instance's cache/, never in the shared core's
        return getcwd() . '/cache/container/' . $this->environment;
    }

    public function getLogDir(): string
    {
        return getcwd() . '/cache/logs';
    }

    protected function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new YesWikiEventCompilerPass());
        $container->addCompilerPass(new YesWikiPerformableCompilerPass());

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__));
        $loader->load('services.yaml');

        // computed from raw config, not through wiki/service methods: build() runs while the
        // container is being compiled, before any service (or $wiki->services) exists
        $fullDomain = parse_url($this->wiki->config['base_url'] ?? '');
        $container->setParameter('host', $fullDomain['host'] ?? '');
        $container->setParameter('mail_domain', $this->wiki->config['mail_domain']);
        $container->setParameter('max-upload-size', FileManager::uploadMaxSizeFromConfig($this->wiki->config['max_file_size'] ?? null));
        // derived from base_url by Init pre-boot; a config change that would move it also invalidates this cache
        $container->setParameter('cookie_path', $this->wiki->CookiePath);

        // load every extension's services/parameters into the same container.
        // NB.: the wiki.php/vendor/autoload.php/libs/{key}.api.php includes that used to be
        // interleaved with this loop now run unconditionally on every request, in
        // Wiki::includeExtensionsBootstrapFiles() - build() is skipped entirely on a cache hit,
        // but those includes' global side effects (constants/functions) are needed every request.
        foreach ($this->wiki->extensions as $pluginBase) {
            if (file_exists($pluginBase . 'config.yaml')) {
                $extensionLoader = new YamlFileLoader($container, new FileLocator($pluginBase));
                $extensionLoader->load('config.yaml');
            }
        }

        // yeswiki.config.php takes priority over extensions' own parameter defaults
        $config = array_replace_recursive($container->getParameterBag()->all(), $this->wiki->config);
        StringUtilService::replaceRecursivelyIndexedArrays($config, $this->wiki->config);
        // environment overrides re-applied here (Init::getConfig() already applied them)
        // so they also cover extension parameters, whose defaults only exist at this point
        $config = EnvironmentConfiguration::apply($config ?? []);
        foreach ($config as $key => $value) {
            $container->setParameter($key, $value);
        }

        $this->addInvalidationResources($container);
    }

    /**
     * Invalidate the compiled container cache when: an admin setting is saved (yeswiki.config.php
     * is rewritten), an extension is installed/removed (extensions/ or custom/ itself gains or
     * loses an entry), or one is enabled/disabled/reconfigured (its desc.xml/config.yaml is rewritten).
     *
     * Deliberately not a Symfony DirectoryResource: that recurses the entire tree on every
     * freshness check (isFresh() runs every request here, see the constructor), which would be
     * far too expensive against extensions/ and custom/. FileResource does a single filemtime() call
     * and works on directories too - editing/adding/removing an immediate child of a directory
     * bumps that directory's own mtime, which is all we need to detect here. Editing an existing
     * config.yaml is already covered for free: YamlFileLoader auto-registers a FileResource for
     * every file it actually parses.
     */
    private function addInvalidationResources(ContainerBuilder $container): void
    {
        // content-hashed, NOT a plain FileResource: yeswiki.config.php is written
        // programmatically in rapid succession (e.g. ArchiveService toggling wiki_status
        // around spawning a subprocess), and two writes within the same mtime second would
        // let the second one serve a stale container - parameters like wiki_status must be
        // picked up immediately
        $container->addResource(new ConfigFileHashResource(ConfigurationFileProvider::getConfigFileFromEnv()));

        // private/.env participates in the effective configuration exactly like
        // yeswiki.config.php (see EnvironmentConfiguration), so it needs the same guard;
        // md5_file() on a missing file hashes to '', so creating or deleting the file
        // invalidates too
        $container->addResource(new ConfigFileHashResource(YESWIKI_INSTANCE_DIR . '/private/.env'));

        // the known variables can also come from the real environment (Docker/vhost/CLI)
        // without any file changing: hash their current values so a container built under
        // one environment is not served under another (e.g. a CLI run with DB_PASSWORD set
        // must not poison the cache used by web requests). YESWIKI_CONFIG_FILE is watched
        // too: repointing it swaps the whole effective config while the previously hashed
        // file stays untouched
        $container->addResource(new EnvValuesHashResource(array_merge(
            ['YESWIKI_CONFIG_FILE'],
            array_keys(EnvironmentConfiguration::knownEnvNames())
        )));

        foreach (self::extensionSetResources($this->getProjectDir()) as $resource) {
            $container->addResource($resource);
        }
    }

    /**
     * Resources that change whenever the *set* of extensions changes (tool installed/removed,
     * enabled/disabled). Shared by every cache keyed on that set: the compiled container
     * (see addInvalidationResources()) and the route collection (see Wiki::getRoutes()).
     *
     * @return FileResource[]
     */
    public static function extensionSetResources(string $projectDir): array
    {
        $resources = [];

        // shared extensions live in the source tree ($projectDir), custom/ belongs to the instance
        foreach (['extensions' => $projectDir, 'custom' => YESWIKI_INSTANCE_DIR] as $extensionsRoot => $rootDir) {
            $dir = $rootDir . '/' . $extensionsRoot;
            if (is_dir($dir)) {
                $resources[] = new FileResource($dir);
            }
        }

        foreach ([$projectDir . '/extensions/*/desc.xml', YESWIKI_INSTANCE_DIR . '/custom/extensions/*/desc.xml'] as $pattern) {
            foreach (glob($pattern) ?: [] as $descFile) {
                $resources[] = new FileResource($descFile);
            }
        }

        return $resources;
    }
}

/**
 * Freshness by content hash instead of mtime, for files rewritten programmatically in rapid
 * succession (mtime has 1-second granularity, so a FileResource can miss the second of two
 * same-second writes). The hash is part of __toString() on purpose: SelfCheckingResourceChecker
 * memoizes isFresh() answers process-wide keyed on "resource:timestamp", and unlike
 * ReflectionClassResource (see Wiki::buildRouteCollection()) this keeps the answer fully
 * determined by the key even when several caches hold different snapshots of the same file.
 */
class ConfigFileHashResource implements \Symfony\Component\Config\Resource\SelfCheckingResourceInterface
{
    private string $file;
    private string $hash;

    public function __construct(string $file)
    {
        $this->file = $file;
        $this->hash = (string)@md5_file($file);
    }

    public function isFresh(int $timestamp): bool
    {
        return (string)@md5_file($this->file) === $this->hash;
    }

    public function __toString(): string
    {
        return 'confighash.' . $this->file . '.' . $this->hash;
    }
}

/**
 * Freshness by hash of a fixed set of environment variables' current values, for the
 * config overrides honored from the real environment (see EnvironmentConfiguration:
 * private/.env changes are already covered by a ConfigFileHashResource, but injected
 * variables can change with no file changing at all). isFresh() runs every request:
 * getenv() over a few dozen names is cheap. The hash is part of __toString() for the
 * same SelfCheckingResourceChecker-memoization reason as ConfigFileHashResource.
 */
class EnvValuesHashResource implements \Symfony\Component\Config\Resource\SelfCheckingResourceInterface
{
    /** @var string[] */
    private array $names;
    private string $hash;

    /**
     * @param string[] $names environment variable names to watch
     */
    public function __construct(array $names)
    {
        $this->names = $names;
        $this->hash = self::valuesHash($names);
    }

    public function isFresh(int $timestamp): bool
    {
        return self::valuesHash($this->names) === $this->hash;
    }

    public function __toString(): string
    {
        return 'envvalueshash.' . md5(implode("\0", $this->names)) . '.' . $this->hash;
    }

    private static function valuesHash(array $names): string
    {
        $values = [];
        foreach ($names as $name) {
            $values[$name] = getenv($name);
        }

        return md5(serialize($values));
    }
}
