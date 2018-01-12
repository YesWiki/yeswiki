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
        $test = var_export($this->_parameters, true);

        $content .= var_export($this->_parameters, true);
        $content .= ";\n";
        file_put_contents($this->_file, $content);
    }
}
