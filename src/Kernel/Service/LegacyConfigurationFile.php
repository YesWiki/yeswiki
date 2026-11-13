<?php

namespace YesWiki\Kernel\Service;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use YesWiki\Core\YesWikiLoader;
use YesWiki\Files\Service\LocalFiles;
use YesWiki\Kernel\Entity\ConfigurationFile;

/** A configuration file from before replicas 20, split into what the wiki decides and what its host does, for the migration that moves it out and for config:export-env. */
#[Exclude]
final class LegacyConfigurationFile
{
    /** What the program knows itself, so it is carried nowhere. */
    public const DERIVED = ['yeswiki_version', 'yeswiki_release'];

    /** @var array<string, mixed> */
    private array $values = [];

    public function __construct(public readonly string $path, private readonly LocalFiles $files = new LocalFiles())
    {
        if ($files->isFile($path)) {
            $file = new ConfigurationFile($path);
            $file->load();
            $values = $file->__get('_parameters');
            $this->values = is_array($values) ? array_filter(self::mapLegacyKeys($values), 'is_string', ARRAY_FILTER_USE_KEY) : [];
        }
    }

    /**
     * Doryphore's names for the settings that were renamed since.
     *
     * @param array<array-key, mixed> $values
     *
     * @return array<array-key, mixed>
     */
    public static function mapLegacyKeys(array $values): array
    {
        foreach (['mysql_host' => 'db_host', 'mysql_database' => 'db_database', 'mysql_user' => 'db_user', 'mysql_password' => 'db_password', 'mysql_port' => 'db_port', 'wakka_name' => 'yeswiki_name'] as $old => $new) {
            if (isset($values[$old]) && !isset($values[$new])) {
                $values[$new] = $values[$old];
            }
            unset($values[$old]);
        }

        return $values;
    }

    public function exists(): bool
    {
        return $this->files->isFile($this->path);
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
