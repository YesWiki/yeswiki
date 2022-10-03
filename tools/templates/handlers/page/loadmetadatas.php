<?php
/**
* Handler AJAX pour récupérer les meta-données
*/

if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

header('Content-type: application/json; charset=UTF-8');

// on teste si on a le droit d'accés aux meta-données
if ($this->HasAccess("read")) {
    echo json_encode(array('result' => $this->GetMetaDatas($this->GetPageTag())));
} else {
    echo json_encode(array('result' => _t('TEMPLATE_ERROR_NO_ACCESS')));
}
