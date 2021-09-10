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
        $content .= $this->customVarExport($this->list, true);
        $content .= ";\n";

        $this->list['yeswiki_release'] = $release;

        if (file_put_contents($file, $content) === false) {
            return false;
        }
        return true;
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
