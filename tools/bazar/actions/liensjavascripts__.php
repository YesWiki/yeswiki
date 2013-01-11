<?php

if (!defined("WIKINI_VERSION")) {
            die ("acc&egrave;s direct interdit");
}

if ($this->GetMethod() == "show" || $this->GetMethod() == "bazarframe" || $this->GetMethod() == "edit") {
    echo '	<script type="text/javascript" src="tools/bazar/libs/bazar.js"></script>'."\n";
}
