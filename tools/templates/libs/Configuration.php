<?php

class Configuration
{
    private $parameters = array();
    private $file = "";

    /**
     * [__construct description]
     * @param [type] $file [description]
     */
    public function __construct($file)
    {
        $this->file = $file;
    }

    public function __get($name)
    {
        if (isset($this->parameters[$name])) {
            return $this->parameters[$name];
        }
        throw new \Exception("Paramètre inconnu Configuration::$name", 1);
    }

    public function __isset($name)
    {
        return isset($this->parameters[$name]);
    }

    public function __set($name, $value)
    {
        $this->parameters[$name] = $value;
    }

    public function load()
    {
        if (!is_file($this->file)) {
            return;
        }

        include $this->file;

        if (isset($wakkaConfig)) {
            $this->parameters = $wakkaConfig;
        }
    }

    /**
     * écris le fichier de configuration
     * @return [type] [description]
     */
    public function write()
    {
        $content = "<?php\n\$wakkaConfig = ";
        $test = var_export($this->parameters, true);

        $content .= var_export($this->parameters, true);
        $content .= ";\n";
        file_put_contents($this->file, $content);
    }
}
