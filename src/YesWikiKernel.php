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
use YesWiki\Core\YesWikiRequestScopeCompilerPass;
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

    public function __construct(YesWikiRuntime $runtime, string $environment)
    {
        $this->runtime = $runtime;

        parent::__construct($environment, true);
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

/**
 * Freshness by content hash instead of mtime, for files rewritten programmatically in rapid succession (mtime has 1-second granularity, so a FileResource can miss the second of two same-second writes).
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
 * Freshness by hash of a fixed set of environment variables' current values, for the config overrides honored from the real environment (see EnvironmentConfiguration: private/.env changes are already covered by a ConfigFileHashResource, but injected variables can change with no file changing at all).
 */
class EnvValuesHashResource implements \Symfony\Component\Config\Resource\SelfCheckingResourceInterface
{
    /**
     * @var string[]
     */
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

    /**
     * @param string[] $names
     */
    private static function valuesHash(array $names): string
    {
        $values = [];
        foreach ($names as $name) {
            $values[$name] = getenv($name);
        }

        return md5(serialize($values));
    }
}
