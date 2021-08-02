<?php

class Configuration
{
    private $_parameters = array();
    private $_file = "";

    /**
     * [__construct description]
     * @param [type] $file [description]
     */
    public function __construct($file)
    {
        $this->_file = $file;
    }

    public function __get($name)
    {
        if (isset($this->_parameters[$name])) {
            return $this->_parameters[$name];
        }
        throw new \Exception("Paramètre inconnu Configuration::$name", 1);
    }

    public function __isset($name)
    {
        return isset($this->_parameters[$name]);
    }

    public function __set($name, $value)
    {
        $this->_parameters[$name] = $value;
    }

    public function __unset($name)
    {
        unset($this->_parameters[$name]);
    }

    public function load()
    {
        if (!is_file($this->_file)) {
            return;
        }

        require $this->_file;

        if (isset($wakkaConfig)) {
            $this->_parameters = $wakkaConfig;
        }
    }

    /**
     * écris le fichier de configuration
     * @return [type] [description]
     */
    public function write()
    {
        $content = "<?php\n\$wakkaConfig = ";

        $content .= $this->customVarExport($this->_parameters, true);
        $content .= ";\n";
        file_put_contents($this->_file, $content);
    }

    /**
     * PHP var_export() with short array syntax (square brackets) indented 2 spaces.
     * tips : https://www.php.net/manual/en/function.var-export.php#124194
     * NOTE: The only issue is when a string value has `=>\n[`, it will get converted to `=> [`
     * @param mixed $expression
     * @param bool $return
     * @return null|string
     */
    private function customVarExport($expression, $return=false): ?string
    {
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
}
