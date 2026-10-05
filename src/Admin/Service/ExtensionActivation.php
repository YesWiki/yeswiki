<?php

namespace YesWiki\Admin\Service;

use YesWiki\Kernel\Entity\ExtensionFolders;
use YesWiki\Kernel\Entity\ExtensionManifest;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\ConfigurationService;

/** Which extensions this Instance runs, kept as `active_extensions` in its configuration (ADR-0029). */
class ExtensionActivation
{
    public const CONFIG_KEY = 'active_extensions';

    public function __construct(private readonly ConfigurationService $configurationService)
    {
    }

    /** @return array<string, ExtensionManifest> every extension this Instance can see, by folder name */
    public function visible(): array
    {
        $manifests = [];
        foreach (ExtensionFolders::visible(YESWIKI_PROGRAM_DIR, YESWIKI_INSTANCE_DIR) as $name => $path) {
            $manifests[$name] = ExtensionManifest::of($name, $path);
        }

        return $manifests;
    }

    /** @return list<string> what the configuration switches on, as written there */
    public function active(): array
    {
        $configuration = $this->configurationService->getConfiguration(ConfigurationFileProvider::getConfigFileFromEnv());
        $configuration->load();
        $listed = $configuration[self::CONFIG_KEY] ?? [];

        return is_array($listed) ? array_values(array_unique(array_map('strval', $listed))) : [];
    }

    public function isActive(string $name): bool
    {
        return in_array($name, $this->active(), true);
    }

    /**
     * Switch $name on, once nothing it needs is missing.
     *
     * @return list<string> why it was not, empty when it was
     */
    public function activate(string $name): array
    {
        $manifest = $this->visible()[$name] ?? null;
        if ($manifest === null) {
            return ["no extension named {$name} here"];
        }
        $active = $this->active();
        if (in_array($name, $active, true)) {
            return [];
        }
        $unmet = $manifest->unmetRequirements($active);
        if ($unmet !== []) {
            return array_map(fn (string $need): string => "{$name} needs {$need}", $unmet);
        }

        return $this->write([...$active, $name]) ? [] : ['the configuration file could not be written'];
    }

    /**
     * Switch $name off, unless another active extension needs it.
     *
     * @return list<string> why it was not, empty when it was
     */
    public function deactivate(string $name, bool $evenIfNeeded = false): array
    {
        $active = $this->active();
        if (!in_array($name, $active, true)) {
            return [];
        }
        $visible = $this->visible();
        $dependants = array_values(array_filter(
            $active,
            fn (string $other): bool => $other !== $name && in_array($name, ($visible[$other] ?? null)?->requiredExtensions() ?? [], true)
        ));
        if ($dependants !== [] && !$evenIfNeeded) {
            return array_map(fn (string $other): string => "{$other} needs {$name}", $dependants);
        }

        return $this->write(array_values(array_diff($active, [$name]))) ? [] : ['the configuration file could not be written'];
    }

    /** @param list<string> $names */
    private function write(array $names): bool
    {
        $configuration = $this->configurationService->getConfiguration(ConfigurationFileProvider::getConfigFileFromEnv());
        $configuration->load();
        sort($names);
        $configuration[self::CONFIG_KEY] = $names;

        return (bool)$this->configurationService->write($configuration);
    }
}
