<?php

if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}

// valeur de la progressbar
$val = $this->GetParameter('val');
if (empty($val)) {
    $error = ' ' . _t('PROGRESSBAR_REQUIRED_VAL_PARAM');
} elseif (!is_numeric($val) || $val < 0 || $val > 100) {
    $error = ' ' . _t('PROGRESSBAR_ERROR_VAL_PARAM');
}

// classe css supplémentaire pour changer le look
$class = $this->GetParameter('class');
$class = 'progressbar progress ' . $class;

if (isset($error)) {
    echo '<div class="alert alert-danger">
        <strong>Action {{progressbar ..}}</strong> : ' . $error . '
      </div>' . "\n";
} else {
    echo '<div class="' . $class . '">
    <div class="progress-bar" role="progressbar"
    style="width: ' . $val . '%;"
    aria-valuenow="' . $val . '" aria-valuemin="0" aria-valuemax="100"></div>
    </div>' . "\n";
}
