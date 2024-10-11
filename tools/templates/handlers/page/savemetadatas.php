<?php
/**
* Handler AJAX pour sauver les meta-données.
*/
if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}

header('Content-type: application/json; charset=UTF-8');

// on teste si on a le droit d'accés aux meta-données
if ($this->HasAccess('write') && $this->HasAccess('read')) {
    // on ajoute les nouvelles meta-données si quelquechose est passé dans le POST meta
    if (isset($_POST['metadatas'])) {
        echo json_encode(['result' => $this->SaveMetaDatas($this->GetPageTag(), $_POST['metadatas'])]);
    } else {
        echo json_encode(['result' => _t('TEMPLATE_ERROR_NO_DATA')]);
    }
} else {
    echo json_encode(['result' => _t('TEMPLATE_ERROR_NO_ACCESS')]);
}
