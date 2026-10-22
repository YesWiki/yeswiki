<?php

namespace YesWiki\Core;

use Psr\Container\ContainerInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\HttpKernel\Kernel;
use YesWiki\Content\Service\FileManager;
use YesWiki\Kernel\Performable\ActionRegistry;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\EnvironmentConfiguration;
use YesWiki\Kernel\Service\LanguageService;
use YesWiki\Kernel\Service\ProgramVersion;
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

    /** What a service implementing each interface is tagged with, for core and for any extension declaring `autoconfigure: true`. */
    private const AUTOCONFIGURED = [
        \YesWiki\Kernel\Performable\RegisteredAction::class => 'yeswiki.action',
        \YesWiki\Kernel\Performable\RegisteredHandler::class => 'yeswiki.handler',
        \YesWiki\Kernel\Component\ProvidesComponents::class => 'yeswiki.component_provider',
        \YesWiki\Content\Entity\SuppliesItems::class => 'yeswiki.item_source',
        \YesWiki\Content\Entity\ContributesEntryFields::class => 'yeswiki.entry_fields',
        \YesWiki\Content\Entity\AppendsToPageView::class => 'yeswiki.page_view_appendix',
        \YesWiki\Content\Entity\ExtendsFormBuilder::class => 'yeswiki.form_builder_extension',
        \YesWiki\Kernel\Health\ProvidesHealthChecks::class => 'yeswiki.health_check_provider',
        \Symfony\Component\EventDispatcher\EventSubscriberInterface::class => 'yeswiki.event_subscriber',
        \YesWiki\Kernel\Service\RequestScopedState::class => 'yeswiki.request_scoped',
    ];

    /** The folders of a module whose classes are services: every class in them is registered, autowired and autoconfigured. */
    private const SERVICE_LAYERS = ['Service', 'Action', 'Handler', 'Controller', 'Api', 'Component'];

    /** The same layers in an extension, named after the folders src/autoload.inc.php maps them to. */
    private const EXTENSION_SERVICE_FOLDERS = ['actions', 'handlers', 'services', 'controllers'];

    /** Services set from outside the container once it exists, see YesWikiRuntime::boot(). */
    private const SYNTHETIC = [
        \Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface::class,
        \Symfony\Component\Security\Csrf\CsrfTokenManager::class,
        YesWikiRuntime::class,
    ];

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
            $this->hashOf(__FILE__),
            $this->hashOf($this->getProjectDir() . '/composer.lock'),
            $this->hashOf($this->getProjectDir() . '/composer.json'),
            $this->hashOf($this->getProjectDir() . '/' . ProgramVersion::CHECKOUT_VERSION_FILE),
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
            $projectDir . '/extensions/*/composer.json',
            $projectDir . '/extensions/*/config.php',
            YESWIKI_INSTANCE_DIR . '/custom/extensions/*/composer.json',
            YESWIKI_INSTANCE_DIR . '/custom/extensions/*/config.php',
        ] as $pattern) {
            foreach (glob($pattern) ?: [] as $file) {
                $found[] = $file;
            }
        }
        sort($found);

        return $found;
    }

    /** Register core and extension services by where their classes live, and the few the kernel wires itself. */
    private function registerServices(ContainerBuilder $container): void
    {
        foreach (self::SYNTHETIC as $id) {
            $container->register($id)->setSynthetic(true)->setPublic(true);
        }
        $container->setAlias(ContainerInterface::class, 'service_container')->setPublic(true);
        $container->register(ActionRegistry::class)->setAutowired(true)->setPublic(true);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__));
        $prototype = (new Definition())->setAutowired(true)->setAutoconfigured(true)->setPublic(true)->setChanges([]);
        $loader->registerClasses($prototype, 'YesWiki\\', '*/{' . implode(',', self::SERVICE_LAYERS) . '}/*');

        foreach ($this->runtime->extensions as $pluginBase) {
            foreach (self::EXTENSION_SERVICE_FOLDERS as $folder) {
                $namespace = self::namespaceOf($pluginBase . $folder);
                if ($namespace !== null) {
                    $loader->registerClasses($prototype, $namespace . '\\', $pluginBase . $folder . '/*');
                }
            }
        }

        $loader->registerAliasesForSinglyImplementedInterfaces();
        $container->getDefinition(LanguageService::class)->setFactory([LanguageService::class, 'getInstance']);
    }

    /** The namespace the PHP files of $directory declare, or null when it has none. */
    private static function namespaceOf(string $directory): ?string
    {
        foreach (glob($directory . '/*.php') ?: [] as $file) {
            if (preg_match('/^namespace\s+([^;\s{]+)/m', (string)file_get_contents($file), $matches)) {
                return $matches[1];
            }
        }

        return null;
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

        foreach (self::AUTOCONFIGURED as $interface => $tag) {
            $container->registerForAutoconfiguration($interface)->addTag($tag);
        }

        $this->registerServices($container);

        $fullDomain = parse_url($this->runtime->config['base_url'] ?? '');
        $container->setParameter('host', $fullDomain['host'] ?? '');
        $container->setParameter('mail_domain', $this->runtime->config['mail_domain']);
        $container->setParameter('max-upload-size', FileManager::uploadMaxSizeFromConfig($this->runtime->config['max_file_size'] ?? null));

        $container->setParameter('cookie_path', $this->runtime->CookiePath);

        foreach ($this->runtime->extensions as $pluginBase) {
            if (file_exists($pluginBase . 'config.php')) {
                foreach (require $pluginBase . 'config.php' as $name => $value) {
                    $container->setParameter($name, $value);
                }
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
     * Invalidate the compiled container cache when: an admin setting is saved (yeswiki.config.php is rewritten), an extension is installed/removed (extensions/ or custom/ itself gains or loses an entry), switched on or off (active_extensions, in that configuration), or reconfigured (its composer.json/config.php is rewritten).
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

        foreach ([$projectDir . '/extensions/*/composer.json', YESWIKI_INSTANCE_DIR . '/custom/extensions/*/composer.json'] as $pattern) {
            foreach (glob($pattern) ?: [] as $manifest) {
                $resources[] = new FileResource($manifest);
            }
        }

        return $resources;
    }
}
