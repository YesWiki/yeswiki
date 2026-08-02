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
            $file = $config->_file;
        }
        $content = $this->getContentToWrite($config, $arrayName);

        if (file_put_contents($file, $content) === false) {
            return false;
        }

        // The configuration is a PHP file, so opcache caches its *compiled* form and
        // revalidates by comparing mtime for equality. A request that has already included
        // the file and then rewrites it in the same second produces a new mtime equal to
        // the cached one -- so opcache decides nothing changed and keeps serving the old
        // array indefinitely, not just until the next revalidate window.
        //
        // That is what made every admin setting look like it did nothing: md5_file() read
        // the new bytes (so the container cache correctly saw itself as stale and rebuilt),
        // while the include feeding that rebuild returned the stale compiled values, which
        // were then baked into the fresh container and blessed with the new hash.
        //
        // Same class of trap as ConfigFileHashResource guards against for FileResource,
        // one layer down. Invalidating explicitly is the standard remedy for treating a
        // PHP file as a data store.
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($file, true);
        }

        return true;
    }

    /**
     * extract content to write tto config file.
     */
    public function getContentToWrite(ConfigurationFile $config, string $arrayName = 'yeswikiConfig'): string
    {
        $content = "<?php\n\n\$$arrayName = ";

        $content .= $this->customVarExport($config->_parameters, true);
        $content .= ";\n";

        return $content;
    }

    /**
     * PHP var_export() with short array syntax (square brackets) indented 2 spaces.
     * tips : https://www.php.net/manual/en/function.var-export.php#124194
     * NOTE: The only issue is when a string value has `=>\n[`, it will get converted to `=> [`.
     */
    protected function customVarExport($expression, bool $return = false): ?string
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
     */
    private function sanitizeToScalar($value)
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
