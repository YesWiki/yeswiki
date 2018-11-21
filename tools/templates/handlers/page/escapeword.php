<?php
/**
 * Escape WikiNames in the page with double quotes
 */
if ($this->HasAccess('write')) {
    if (!empty($_GET['word'])) {
        $body = preg_replace(
            '(?!(""|\[\[))('.preg_quote($_GET['word']).')(?!(""|\]\]))/Uu',
            '""$2""',
            $this->page["body"]
        );
        $this->SavePage($this->getPageTag(), $body);
    }
    $this->redirect($this->href());
} else {
    echo '<div class="alert alert-danger">'._t('TEMPLATE_ERROR_NO_ACCESS').'</div>';
}
