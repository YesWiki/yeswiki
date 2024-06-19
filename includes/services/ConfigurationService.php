<?php

namespace YesWiki\Core\Service;

use YesWiki\Core\Entity\ConfigurationFile;

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
    public function write(ConfigurationFile $config, ?string $file = null, string $arrayName = 'wakkaConfig')
    {
        if (is_null($file)) {
            $file = $config->_file;
        }
        $content = $this->getContentToWrite($config, $arrayName);

        return file_put_contents($file, $content) !== false;
    }

    /**
     * extract content to write tto config file.
     */
    public function getContentToWrite(ConfigurationFile $config, string $arrayName = 'wakkaConfig'): string
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
     *
     * @param mixed $expression
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
        } else {
            echo $export;

            return null;
        }
    }

    /**
     * sanitize $value to keep only arrays, string, bool, null, int, float.
     *
     * @param mixed $value
     *
     * @return mixed
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
