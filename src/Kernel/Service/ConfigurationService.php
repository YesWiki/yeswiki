<?php

namespace YesWiki\Kernel\Service;

use YesWiki\Files\Service\LocalFiles;
use YesWiki\Kernel\Entity\ConfigurationFile;

class ConfigurationService
{
    /** Built by hand, before a container exists (the installer), it writes the file; from the container, it writes nothing but the database and `private/.env` (replicas 04). */
    public function __construct(
        private readonly LocalFiles $localFiles = new LocalFiles(),
        private readonly ?WikiSettings $settings = null,
        private readonly ?EnvFile $envFile = null,
    ) {
    }

    /**
     * What a configuration file says once the environment and the wiki's database have had their say, for the wiki's own file;
     * the file alone for any other.
     *
     * @param array<string, mixed> $fromTheFile
     *
     * @return array<string, mixed>
     */
    public function effective(string $file, array $fromTheFile): array
    {
        if (!$this->routes($file)) {
            return $fromTheFile;
        }

        return $this->settings?->over(EnvironmentConfiguration::apply($fromTheFile)) ?? $fromTheFile;
    }

    /** Whether writing that file means writing the database and `private/.env` instead. */
    private function routes(string $file): bool
    {
        if ($this->settings === null || $this->envFile === null) {
            return false;
        }
        if (!\defined('YESWIKI_INSTANCE_DIR')) {
            return false;
        }
        $own = YESWIKI_INSTANCE_DIR . '/yeswiki.config.php';

        $path = fn (string $candidate): string => $this->localFiles->realPath($candidate) ?: $candidate;

        return $path($file) === $path($own) && $path(ConfigurationFileProvider::getConfigFileFromEnv()) === $path($own);
    }

    /** Stores what a write changed: the wiki's settings in its database unless the file already says it, the operator's in `private/.env`, refused on a replica. */
    private function route(ConfigurationFile $config, string $file): bool
    {
        $before = new ConfigurationFile($file, $this);
        $before->load();
        $was = $before->__get('_parameters');
        $now = $config->__get('_parameters');
        $changed = [];
        foreach ($now as $key => $value) {
            if (!array_key_exists($key, $was) || $was[$key] !== $value) {
                $changed[(string)$key] = $value;
            }
        }
        foreach (array_keys($was) as $key) {
            if (!array_key_exists($key, $now)) {
                $changed[(string)$key] = null;
            }
        }
        $operator = array_filter($changed, static fn (string $key): bool => SettingKinds::isOperator($key), ARRAY_FILTER_USE_KEY);
        if ($operator !== [] && Replication::isOn()) {
            throw new \RuntimeException('These settings belong to the operator of a replicated wiki, in its environment: ' . implode(', ', array_keys($operator)));
        }
        $plain = new ConfigurationFile($file);
        $plain->load();
        $base = EnvironmentConfiguration::apply($plain->__get('_parameters'));
        $store = [];
        $forget = [];
        foreach (array_diff_key($changed, $operator) as $key => $value) {
            if ($value === null ? !array_key_exists($key, $base) : (array_key_exists($key, $base) && $base[$key] === $value)) {
                $forget[] = $key;
            } else {
                $store[$key] = $value;
            }
        }
        $this->settings?->store($store, $forget);

        return $this->envFile?->store($operator) ?? false;
    }

    public function getConfiguration(string $filePath): ConfigurationFile
    {
        return new ConfigurationFile($filePath, $this);
    }

    /**
     * Write the config whole or not at all: a full disk or quota leaves the previous file in place.
     *
     * @return bool
     */
    public function write(ConfigurationFile $config, ?string $file = null, string $arrayName = 'yeswikiConfig')
    {
        if (is_null($file)) {
            $file = $this->filePathOf($config);
        }
        if ($this->routes($file)) {
            return $this->route($config, $file);
        }
        $content = $this->getContentToWrite($config, $arrayName);

        if (!$this->localFiles->writeAtomically($file, $content)) {
            return false;
        }

        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($file, true);
        }

        return true;
    }

    /** extract content to write tto config file. */
    public function getContentToWrite(ConfigurationFile $config, string $arrayName = 'yeswikiConfig'): string
    {
        $content = "<?php\n\n\$$arrayName = ";

        $content .= $this->customVarExport($config->__get('_parameters'), true);
        $content .= ";\n";

        return $content;
    }

    /** The path a ConfigurationFile was loaded from. */
    private function filePathOf(ConfigurationFile $config): string
    {
        $path = $config->__get('_file');

        return is_string($path) ? $path : '';
    }

    /**
     * PHP var_export() with short array syntax (square brackets) indented 2 spaces.
     */
    protected function customVarExport(mixed $expression, bool $return = false): ?string
    {
        $expression = $this->sanitizeToScalar($expression);
        $export = var_export($expression, true);
        $patterns = [
            "/array \(/" => '[',
            "/^([ ]*)\)(,?)$/m" => '$1]$2',
            "/=>[ ]?\n[ ]+\[/" => '=> [',
            "/([ ]*)(\'[^\']+\') => ([\[\'])/" => '$1$2 => $3',
        ];
        $export = preg_replace(array_keys($patterns), array_values($patterns), $export);
        if ((bool)$return) {
            return $export;
        }
        echo $export;

        return null;
    }

    /**
     * sanitize $value to keep only arrays, string, bool, null, int, float.
     *
     * @return array<array-key, mixed>|string|bool|int|float|null
     */
    private function sanitizeToScalar(mixed $value)
    {
        if (is_array($value)) {
            return array_map(function ($subValue) {
                return $this->sanitizeToScalar($subValue);
            }, $value);
        } elseif (is_null($value) || is_string($value) || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        } else {
            return (string)$value;
        }
    }
}
