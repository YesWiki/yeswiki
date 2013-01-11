<?php
// Administration


// Vérification de sécurité
if (!defined("TOOLS_MANAGER"))
{
        die ("acc&egrave;s direct interdit");
}


buffer::str(
' 
Ajouter les lignes suivantes dans le fichier wakka.css pour personnaliser
votre menu de navigation :
<br>
<code>
.page_table {margin: 0px; padding: 0px ; border: none; height: 100%;width: 100%;} 
<br>
.menu_column {background-color: #FFFFCC; vertical-align: top; width: 150px; border: 1px solid #000000;padding:5px;}
<br>
.body_column {vertical-align: top; border: none;padding:5px;}
<br>
</code>
'


);

?>
