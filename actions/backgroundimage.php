<?php

// ticket 17: relocated from tools/attach/actions/backgroundimage.php as-is (raw
// filename, not FileManager-tagged -- out of scope for the file-as-Content redesign,
// same as tags.functions.php's afficher_image_attach()).

use YesWiki\Core\Attach;

// Get the action's parameters :

// image's background color
$bgcolor = $this->GetParameter('bgcolor');

// image's filename
$file = $this->GetParameter('file');
if (empty($file) && empty($bgcolor)) {
    echo '<div class="yw-alert yw-alert--danger"><strong>' . _t('ATTACH_ACTION_BACKGROUNDIMAGE') . '</strong> : '
          . _t('ATTACH_PARAM_FILE_OR_BGCOLOR_NOT_FOUND') . '.</div>' . "\n";

    return;
}

if (!empty($file)) {
    $att = new Attach($this);

    // test of image extension
    if (!$att->isPicture($file)) {
        echo '<div class="yw-alert yw-alert--danger"><strong>' . _t('ATTACH_ACTION_BACKGROUNDIMAGE') . '</strong> : '
              . _t('ATTACH_PARAM_FILE_MUST_BE_IMAGE') . '.</div>' . "\n";

        return;
    }
    // image size
    $height = $this->GetParameter('height');
    $width = $this->GetParameter('width');
    if (empty($width)) {
        $width = 1920;
    }

    // recuperation des parametres necessaires
    $att->file = $file;
    $att->desc = 'background image ' . $file;
    $att->height = $height;
    $att->width = $width;
    $fullFilename = $att->GetFullFilename();
}

// container class
$class = $this->GetParameter('class');

// container id
$id = $this->GetParameter('id');

// container data attributes
$data = $this->services->get(YesWiki\Core\Service\TemplateHelperService::class)->getDataParameter();

echo '<div' . (!empty($id) ? ' id="' . $id . '"' : '') . ' class="background-image' . (!empty($class) ? ' ' . $class : '') . '" style="'
    . (!empty($bgcolor) ? 'background-color:' . $bgcolor . '; ' : '')
    . (!empty($height) ? 'height:' . $height . 'px; ' : '')
    . (isset($fullFilename) ? 'background-image:url(' . $this->getBaseUrl() . '/' . $fullFilename . ');' : '') . '"';
if (is_array($data)) {
    foreach ($data as $key => $value) {
        echo ' data-' . $key . '="' . $value . '"';
    }
}
echo '>' . "\n";
$nocontainer = $this->GetParameter('nocontainer');
if (empty($nocontainer)) {
    echo '<div class="yw-container">' . "\n";
} else {
    echo '<div>';
}
// test d'existance du fichier
if (isset($fullFilename) and (!file_exists($fullFilename) or $fullFilename == '')) {
    $att->showFileNotExits();
    // return;
}
