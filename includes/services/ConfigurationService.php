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
     * Write the config whole or not at all: a full disk or quota leaves the previous file in place.
     *
     * @return bool
     */
    public function write(ConfigurationFile $config, ?string $file = null, string $arrayName = 'wakkaConfig')
    {
        if (is_null($file)) {
            $file = $config->_file;
        }
        $written = $this->writeAtomically($file, $this->getContentToWrite($config, $arrayName));
        if ($written && function_exists('opcache_invalidate')) {
            opcache_invalidate($file, true);
        }

        return $written;
    }

    /**
     * Write next to the target and rename over it, so the target is never seen truncated.
     */
    public function writeAtomically(string $file, string $content): bool
    {
        $target = is_link($file) ? (string)realpath($file) : $file;
        if ($target === '') {
            return false;
        }
        if (!is_writable(dirname($target))) {
            return @file_put_contents($target, $content) === strlen($content);
        }
        $temporary = dirname($target) . DIRECTORY_SEPARATOR . '.' . pathinfo($target, PATHINFO_FILENAME) . '.' . bin2hex(random_bytes(6)) . '.php';
        $handle = @fopen($temporary, 'x');
        if ($handle === false) {
            return false;
        }
        $bytes = @fwrite($handle, $content);
        $flushed = @fflush($handle);
        $closed = fclose($handle);
        if ($bytes !== strlen($content) || !$flushed || !$closed) {
            @unlink($temporary);

            return false;
        }
        @chmod($temporary, file_exists($target) ? (fileperms($target) & 0777) : 0644);
        if (!@rename($temporary, $target)) {
            @unlink($temporary);

            return false;
        }

        return true;
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
