<?php
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}
if (!$this->HasAccess('write')) {
    $page = $this->header().'<div class="alert alert-danger alert-error">'.
      _t('LOGIN_NOT_AUTORIZED_EDIT').'. '._t('LOGIN_PLEASE_REGISTER').'.'.
      '</div>'."\n".
      $this->Format('{{login template="minimal.tpl.html"}}').'<br><br>'.$this->footer();
    exit($page);
}
