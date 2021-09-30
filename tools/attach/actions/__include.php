<?php
    if (!defined("WIKINI_VERSION")) {
        die("acc&egrave;s direct interdit");
    }

    $oldpage = $this->GetPageTag();
    $this->CachePage($this->page);
    $this->tag = trim($this->GetParameter('page'));
    $includedPage = $this->GetCachedPage($this->tag);
    $this->setPage(!empty($includedPage) ? $includedPage : $this->LoadPage($this->tag));
