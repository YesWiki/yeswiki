<?php



if ($this->HasAccess("read")) {
    if (!$this->page) {
        return;
    } else {
        header("Content-type: text/plain; charset=".YW_CHARSET);
        // display raw page
        echo _convert($this->page["body"], YW_CHARSET);
    }
} else {
    return;
}
