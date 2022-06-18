<?php

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Core\Service\AssetsManager;

$this->services->get(AssetsManager::class)->AddJavascript(
    "var fileUploaderConfig = {attach_config:{ext_images:"
        .json_encode(explode("|", $this->services->get(ParameterBagInterface::class)->get("attach_config")["ext_images"]))
        ."}};"
);
$this->services->get(AssetsManager::class)->AddJavascriptFile('tools/attach/libs/fileuploader.js');
