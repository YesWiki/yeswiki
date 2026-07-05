<?php

namespace YesWiki;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\Kernel;
use YesWiki\Core\Service\ConfigurationFileProvider;
use YesWiki\Core\YesWikiEventCompilerPass;

/**
 * Owns the DI container build/compile/cache lifecycle for a Wiki instance.
 *
 * registerBundles() is always empty: YesWiki has no bundle system, extensions
 * register their services/parameters directly (see build()) the same way they
 * always have, via a config.yaml loaded into the shared ContainerBuilder.
 */
class YesWikiKernel extends Kernel
{
    private Wiki $wiki;

    public function __construct(Wiki $wiki, string $environment)
    {
        $this->wiki = $wiki;
        // Symfony's Kernel skips its container-cache freshness check entirely when $debug
        // is false (see Kernel::initializeContainer()) - it assumes prod containers are
        // rebuilt via an explicit deploy/cache-clear step. YesWiki has no such step: site
        // settings, tool install/enable/disable all happen live through the admin UI, so the
        // cache must always be freshness-checked. $debug is therefore always true here;
        // $environment (derived from the wakka config's own debug flag) only segregates the
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
        // farm several instances share the sources but each has its own config/tools, so the
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

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__));
        $loader->load('services.yaml');

        // same as today's Init::initCoreServices()
        $fullDomain = parse_url($this->wiki->Href());
        $container->setParameter('host', $fullDomain['host'] ?? '');
        $container->setParameter('mail_domain', $this->wiki->config['mail_domain']);
        $container->setParameter('max-upload-size', $this->wiki->file_upload_max_size());

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

        // wakka.config.php takes priority over extensions' own parameter defaults
        $config = array_replace_recursive($container->getParameterBag()->all(), $this->wiki->config);
        $this->wiki->replaceRecursivelyIndexedArrays($config, $this->wiki->config);
        foreach ($config as $key => $value) {
            $container->setParameter($key, $value);
        }

        $this->addInvalidationResources($container);
    }

    /**
     * Invalidate the compiled container cache when: an admin setting is saved (wakka.config.php
     * is rewritten), a tool is installed/removed (tools/ or custom/ itself gains or loses an
     * entry), or a tool is enabled/disabled/reconfigured (its desc.xml/config.yaml is rewritten).
     *
     * Deliberately not a Symfony DirectoryResource: that recurses the entire tree on every
     * freshness check (isFresh() runs every request here, see the constructor), which would be
     * far too expensive against tools/ and custom/. FileResource does a single filemtime() call
     * and works on directories too - editing/adding/removing an immediate child of a directory
     * bumps that directory's own mtime, which is all we need to detect here. Editing an existing
     * config.yaml is already covered for free: YamlFileLoader auto-registers a FileResource for
     * every file it actually parses.
     */
    private function addInvalidationResources(ContainerBuilder $container): void
    {
        $container->addResource(new FileResource(ConfigurationFileProvider::getConfigFileFromEnv()));

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

        // shared tools live in the source tree ($projectDir), custom/ belongs to the instance
        foreach (['tools' => $projectDir, 'custom' => YESWIKI_INSTANCE_DIR] as $extensionsRoot => $rootDir) {
            $dir = $rootDir . '/' . $extensionsRoot;
            if (is_dir($dir)) {
                $resources[] = new FileResource($dir);
            }
        }

        foreach ([$projectDir . '/tools/*/desc.xml', YESWIKI_INSTANCE_DIR . '/custom/tools/*/desc.xml'] as $pattern) {
            foreach (glob($pattern) ?: [] as $descFile) {
                $resources[] = new FileResource($descFile);
            }
        }

        return $resources;
    }
}
