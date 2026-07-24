<?php

// valeur de la progressbar
$val = $this->GetParameter('val');
if (empty($val)) {
    $error = ' ' . _t('PROGRESSBAR_REQUIRED_VAL_PARAM');
} elseif (!is_numeric($val) || $val < 0 || $val > 100) {
    $error = ' ' . _t('PROGRESSBAR_ERROR_VAL_PARAM');
}

// classe css supplémentaire pour changer le look
$class = $this->GetParameter('class');
$class = 'yw-progressbar ' . $class;

if (isset($error)) {
    echo '<div class="yw-alert yw-alert--danger">
        <strong>Action {{progressbar ..}}</strong> : ' . $error . '
      </div>' . "\n";
} else {
    echo '<div class="' . $class . '">
    <div class="yw-progressbar__bar" role="progressbar"
    style="width: ' . $val . '%;"
    aria-valuenow="' . $val . '" aria-valuemin="0" aria-valuemax="100"></div>
    </div>' . "\n";
}
