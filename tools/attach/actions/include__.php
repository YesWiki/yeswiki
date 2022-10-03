<?php
    $this->tag = $oldpage;
    $includedPage = $this->GetCachedPage($this->tag);
    $this->page = !empty($includedPage) ? $includedPage : $this->LoadPage($this->tag);
