<?php
namespace AutoUpdate;

abstract class PackageExt extends Package
{
    const INFOS_FILENAME = "infos.json";

    protected $infos = null;

    public $deleteLink;

    abstract protected function localPath();

    public function __construct($release, $address, $desc, $doc)
    {
        parent::__construct($release, $address, $desc, $doc);
        $this->installed = $this->installed();
        $this->localPath = $this->localPath();
        $this->updateAvailable = $this->updateAvailable();
        $this->deleteLink = '&delete=' . $this->name;
    }

    public function upgrade()
    {
        $desPath = $this->localPath();

        $this->deletePackage();
        mkdir($desPath);

        if ($this->extractionPath === null) {
            throw new \Exception("Le paquet n'a pas été décompressé.", 1);
        }

        $this->copy(
            $this->extractionPath . '/' . $this->name(),
            $desPath
        );

        return true;
    }

    public function upgradeInfos()
    {
        $infos = array(
            "name" => $this->name,
            "release" => (string)$this->release,
        );
        $json = json_encode($infos);
        file_put_contents($this->infosFilePath(), $json);
        // TODO Vérifier que l'action a bien été éxécutée.
        return true;
    }

    public function deletePackage()
    {
        $desPath = $this->localPath();
        if (is_dir($desPath)) {
            $this->delete($desPath);
        }
    }

    protected function getInfos()
    {
        if ($this->infos !== null) {
            return $this->infos;
        }

        $this->infos = array();
        if (is_file($this->infosFilePath())) {
            $json = file_get_contents($this->infosFilePath());
            $this->infos = json_decode($json, true);
        }
        return $this->infos;
    }

    protected function localRelease()
    {
        if ($this->installed()) {
            $infos = $this->getInfos();
            if (isset($infos['release'])) {
                return new Release($infos['release']);
            }
        }
        return new Release(Release::UNKNOW_RELEASE);
    }

    private function installed()
    {
        if (is_dir($this->localPath())) {
            return true;
        }
        return false;
    }

    private function infosFilePath()
    {
        return $this->localPath() . $this::INFOS_FILENAME;
    }
}
