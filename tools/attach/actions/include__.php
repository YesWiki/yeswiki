<?php
    if (!defined("WIKINI_VERSION")) {
        die("acc&egrave;s direct interdit");
    }

    $this->tag = $oldpage;
    $includedPage = $this->GetCachedPage($this->tag);
    $this->page = !empty($includedPage) ? $includedPage : $this->LoadPage($this->tag);
