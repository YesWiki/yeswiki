<?php

use YesWiki\Bazar\Service\EntryManager;

if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

$entryManager = $this->services->get(EntryManager::class);

if ($this->HasAccess("write") && $this->HasAccess("read") && !$entryManager->isEntry($this->tag) && !isset($this->page["metadatas"]["ebook-title"])) {
    // preview?
    if (isset($_POST["submit"]) && $_POST["submit"] == "Apercu") {
        // Rien
    } else {
        $uploadModal = $this->render('@attach/attach-file-upload-modal.twig', [
            'imageSmallWidth' => $this->config['image-small-width'],
            'imageSmallHeight' => $this->config['image-small-height'],
            'imageMediumWidth' => $this->config['image-medium-width'],
            'imageMediumHeight' => $this->config['image-medium-height'],
            'imageBigWidth' => $this->config['image-big-width'],
            'imageBigHeight' => $this->config['image-big-height'],
        ]);
        $UploadBar = $this->render('@attach/attach-file-uploader-button.twig', []);
        $plugin_output_new = str_replace('<form id="ACEditor"', $UploadBar.$uploadModal.'<form id="ACEditor"', $plugin_output_new);
    }
}
