<?php
namespace AutoUpdate;

class Configuration extends Collection
{
    private $file = null;

    public function __construct($file)
    {
        $this->file = $file;
    }

    public function load()
    {
        include $this->file;
        if (isset($wakkaConfig)) {
            $this->list = $wakkaConfig;
        }
    }

    public function write($file = null, $arrayName = "wakkaConfig")
    {
        if (is_null($file)) {
            $file = $this->file;
        }

        // Bidouille pour eviter l'export de classe par var_export mais
        // uniquement de la valeur
        $release = $this->list['yeswiki_release'];
        $this->list['yeswiki_release'] = (string)$this->list['yeswiki_release'];
        $content = "<?php\n" . "\$$arrayName = ";
        $content .= var_export($this->list, true);
        $content .= ";\n";

        $this->list['yeswiki_release'] = $release;

        if (file_put_contents($file, $content) === false) {
            return false;
        }
        return true;
    }
}
