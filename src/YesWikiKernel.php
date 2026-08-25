<?php

namespace YesWiki\Core;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\Kernel;
use YesWiki\Content\Service\FileManager;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\EnvironmentConfiguration;
use YesWiki\Kernel\Service\StringUtilService;

/** Owns the DI container build/compile/cache lifecycle for a Wiki instance. */
class YesWikiKernel extends Kernel
{
    /** Historic Wiki::isCli(). */
    public static function isCli(): bool
    {
        return in_array(php_sapi_name(), ['cli', 'phpdbg'], true);
    }

    private YesWikiRuntime $runtime;

    /** Computed once per process: the kernel asks for the cache directory many times. */
    private ?string $fingerprint = null;

    public function __construct(YesWikiRuntime $runtime, string $environment)
    {
        $this->runtime = $runtime;

        parent::__construct($environment, false);
    }

    public function registerBundles(): iterable
    {
        return [];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
    }

    public function getProjectDir(): string
    {
        return \dirname(__DIR__);
    }

    public function getCacheDir(): string
    {
        return getcwd() . '/cache/container/' . $this->environment . '/' . $this->fingerprint();
    }

    /** What this container was built from, as a short hash: change it and a new one is built. */
    private function fingerprint(): string
    {
        if ($this->fingerprint !== null) {
            return $this->fingerprint;
        }

        $parts = [
            self::VERSION,
            $this->hashOf(ConfigurationFileProvider::getConfigFileFromEnv()),
            $this->hashOf(YESWIKI_INSTANCE_DIR . '/private/.env'),
            $this->hashOf(__DIR__ . '/services.yaml'),
            $this->hashOf($this->getProjectDir() . '/composer.lock'),
        ];

        foreach (array_merge(['YESWIKI_CONFIG_FILE'], array_keys(EnvironmentConfiguration::knownEnvNames())) as $name) {
            $value = getenv($name);
            $parts[] = $name . '=' . ($value === false ? '' : $value);
        }

        foreach (self::extensionDescriptors($this->getProjectDir()) as $descriptor) {
            $parts[] = $descriptor . ':' . $this->hashOf($descriptor);
        }

        return $this->fingerprint = substr(sha1(implode("\n", $parts)), 0, 12);
    }

    private function hashOf(string $path): string
    {
        $contents = @file_get_contents($path);

        return $contents === false ? '' : md5($contents);
    }

    /**
     * Every extension descriptor and config, sorted, from both roots.
     *
     * @return list<string>
     */
    private static function extensionDescriptors(string $projectDir): array
    {
        $found = [];
        foreach ([
            $projectDir . '/extensions/*/desc.xml',
            $projectDir . '/extensions/*/config.yaml',
            YESWIKI_INSTANCE_DIR . '/custom/extensions/*/desc.xml',
            YESWIKI_INSTANCE_DIR . '/custom/extensions/*/config.yaml',
        ] as $pattern) {
            foreach (glob($pattern) ?: [] as $file) {
                $found[] = $file;
            }
        }
        sort($found);

        return $found;
    }

    /** Remove containers built from configurations this wiki is no longer in, keeping one spare. */
    private function pruneOldContainers(): void
    {
        $root = \dirname($this->getCacheDir());
        $keep = basename($this->getCacheDir());

        $entries = @scandir($root);
        if ($entries === false) {
            return;
        }

        $candidates = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === $keep) {
                continue;
            }
            $path = $root . '/' . $entry;
            if (is_dir($path)) {
                $candidates[$path] = (int)@filemtime($path);
            }
        }
        if (\count($candidates) < 2) {
            return;
        }

        asort($candidates);
        array_pop($candidates);

        foreach (array_keys($candidates) as $path) {
            $this->removeTree($path);
        }
    }

    private function removeTree(string $path): void
    {
        foreach ((array)@scandir($path) as $entry) {
            if ($entry === '.' || $entry === '..' || !\is_string($entry)) {
                continue;
            }
            $child = $path . '/' . $entry;
            is_dir($child) ? $this->removeTree($child) : @unlink($child);
        }
        @rmdir($path);
    }

    public function getLogDir(): string
    {
        return getcwd() . '/cache/logs';
    }

    protected function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new YesWikiEventCompilerPass());
        $container->addCompilerPass(new YesWikiPerformableCompilerPass());
        $container->addCompilerPass(new YesWikiRequestScopeCompilerPass());

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__));
        $loader->load('services.yaml');

        $fullDomain = parse_url($this->runtime->config['base_url'] ?? '');
        $container->setParameter('host', $fullDomain['host'] ?? '');
        $container->setParameter('mail_domain', $this->runtime->config['mail_domain']);
        $container->setParameter('max-upload-size', FileManager::uploadMaxSizeFromConfig($this->runtime->config['max_file_size'] ?? null));

        $container->setParameter('cookie_path', $this->runtime->CookiePath);

        foreach ($this->runtime->extensions as $pluginBase) {
            if (file_exists($pluginBase . 'config.yaml')) {
                $extensionLoader = new YamlFileLoader($container, new FileLocator($pluginBase));
                $extensionLoader->load('config.yaml');
            }
        }

        $config = array_replace_recursive($container->getParameterBag()->all(), $this->runtime->config);
        StringUtilService::replaceRecursivelyIndexedArrays($config, $this->runtime->config);

        $config = EnvironmentConfiguration::apply($config ?? []);
        foreach ($config as $key => $value) {
            $container->setParameter($key, $value);
        }

        $this->addInvalidationResources($container);

        $this->pruneOldContainers();
    }

    /**
     * Invalidate the compiled container cache when: an admin setting is saved (yeswiki.config.php is rewritten), an extension is installed/removed (extensions/ or custom/ itself gains or loses an entry), or one is enabled/disabled/reconfigured (its desc.xml/config.yaml is rewritten).
     */
    private function addInvalidationResources(ContainerBuilder $container): void
    {
        $container->addResource(new ConfigFileHashResource(ConfigurationFileProvider::getConfigFileFromEnv()));

        $container->addResource(new ConfigFileHashResource(YESWIKI_INSTANCE_DIR . '/private/.env'));

        $container->addResource(new EnvValuesHashResource(array_merge(
            ['YESWIKI_CONFIG_FILE'],
            array_keys(EnvironmentConfiguration::knownEnvNames())
        )));

        foreach (self::extensionSetResources($this->getProjectDir()) as $resource) {
            $container->addResource($resource);
        }
    }

    /**
     * Resources that change whenever the *set* of extensions changes (tool installed/removed, enabled/disabled).
     *
     * @return FileResource[]
     */
    public static function extensionSetResources(string $projectDir): array
    {
        $resources = [];

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
