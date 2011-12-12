<?php

if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}

$plugin_output_new = str_replace ('</body>', '<script defer type="text/javascript" src="tools/contact/libs/contact.js"></script>'."\n".'</body>', $plugin_output_new);

?>	
