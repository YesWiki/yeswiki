<?php

if ($this->HasAccess("read")) {
    if (!$this->page) {
        return;
    } else {
        // affichage de la page formatee
        echo "<div class=\"page\">\n" . $this->Format($this->page["body"], 'wakka', $this->GetPageTag()) . "\n</div>\n";
    }
} else {
    return;
}
