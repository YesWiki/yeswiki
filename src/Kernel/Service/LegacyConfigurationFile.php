<?php

namespace YesWiki\Kernel\Service;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use YesWiki\Admin\Service\InstallationService;
use YesWiki\Core\YesWikiLoader;
use YesWiki\Kernel\Entity\ConfigurationFile;

/** A configuration file from before replicas 20, split into what the wiki decides and what its host does, for the migration that moves it out and for config:export-env. */
#[Exclude]
final class LegacyConfigurationFile
{
    /** What the program knows itself, so it is carried nowhere. */
    public const DERIVED = ['yeswiki_version', 'yeswiki_release'];

    /** @var array<string, mixed> */
    private array $values = [];

    public function __construct(public readonly string $path)
    {
        if (is_file($path)) {
            $file = new ConfigurationFile($path);
            $file->load();
            $values = $file->__get('_parameters');
            $this->values = is_array($values) ? array_filter(InstallationService::mapLegacyKeys($values), 'is_string', ARRAY_FILTER_USE_KEY) : [];
        }
    }

    public function exists(): bool
    {
        return is_file($this->path);
    }

    /** @return array<string, mixed> every setting the file states, the old names mapped to the new */
    public function values(): array
    {
        return $this->values;
    }

    /** @return array<string, mixed> the host's settings the file states */
    public function hostSettings(): array
    {
        return array_filter($this->values, static fn (string $key): bool => SettingKinds::isOperator($key) && !in_array($key, self::DERIVED, true), ARRAY_FILTER_USE_KEY);
    }

    /** @return array<string, mixed> the settings the wiki decides */
    public function wikiSettings(): array
    {
        return array_filter($this->values, static fn (string $key): bool => !SettingKinds::isOperator($key) && !in_array($key, self::DERIVED, true), ARRAY_FILTER_USE_KEY);
    }

    /** @return array<string, mixed> the host's settings, less those the environment or that instance's private/.env already state */
    public function hostSettingsToMove(string $instanceDir): array
    {
        $envFile = YesWikiLoader::envFileValues($instanceDir);
        $fromTheFile = array_filter(explode(',', (string)getenv('SYMFONY_DOTENV_VARS')));
        $names = array_flip(EnvironmentConfiguration::knownEnvNames());

        return array_filter($this->values, static function (string $key) use ($envFile, $fromTheFile, $names): bool {
            if (!SettingKinds::isOperator($key) || in_array($key, self::DERIVED, true)) {
                return false;
            }
            $name = $names[$key] ?? strtoupper($key);
            if (array_key_exists($name, $envFile)) {
                return false;
            }

            return getenv($name) === false || in_array($name, $fromTheFile, true);
        }, ARRAY_FILTER_USE_KEY);
    }
}
