<?php
if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}
//attributs du body
$body_attr = ($message = $this->GetMessage()) ? "onload=\"alert('".addslashes($message)."');\" " : "";
echo $body_attr;
?>
