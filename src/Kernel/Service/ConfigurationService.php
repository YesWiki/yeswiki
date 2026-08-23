<?php

namespace YesWiki\Kernel\Service;

use YesWiki\Kernel\Entity\ConfigurationFile;

class ConfigurationService
{
    public function __construct()
    {
    }

    public function getConfiguration(string $filePath): ConfigurationFile
    {
        return new ConfigurationFile($filePath, $this);
    }

    /**
     * write config.
     *
     * @return bool
     */
    public function write(ConfigurationFile $config, ?string $file = null, string $arrayName = 'yeswikiConfig')
    {
        if (is_null($file)) {
            $file = $this->filePathOf($config);
        }
        $content = $this->getContentToWrite($config, $arrayName);

        if (file_put_contents($file, $content) === false) {
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
